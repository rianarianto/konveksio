<?php

namespace App\Livewire;

use App\Models\Material;
use App\Models\Order;
use App\Models\Product;
use Livewire\Component;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;

class OrderItemsSpreadsheet extends Component
{
    public array $items = [];
    public ?int $orderId = null;
    
    // Options for selects
    public array $materialOptions = [];
    public array $sizeOptions = [];
    // Form fields for Bulk Generate
    public $bulkMaterial;
    public $bulkCategory = 'produksi';
    public $bulkPrice = 0;
    public $bulkCustomQty = 0;
    public array $bulkSzQty = []; // [ 'S' => 0, 'M' => 0, ... ]
    
    // New garment attributes for bulk generate
    public $bulkGender = 'L';
    public $bulkSleeve = 'pendek';
    public $bulkPocket = 'tanpa_saku';
    public $bulkButtons = 'biasa';
    public $bulkIsTunic = false;
    public $bulkTunicFee = 0;

    // UI States
    public bool $showBulkModal = false;
    public bool $showPriceModal = false;
    public bool $showDetailModal = false;
    public $newBulkPrice = 0;
    public $editingIndex = null;
    public array $editingItem = [];

    public array $productionCategories = [
        'produksi' => 'Produksi',
        'custom' => 'Custom',
        'non_produksi' => 'Non-Produksi',
        'jasa' => 'Jasa',
    ];

    public array $sleeveOptions = ['pendek' => 'Pendek', 'panjang' => 'Panjang', '3/4' => '3/4'];
    public array $pocketOptions = ['tanpa_saku' => 'Tanpa Saku', 'tempel' => 'Tempel', 'bobok' => 'Bobok', 'double' => 'Double Saku'];
    public array $buttonOptions = ['biasa' => 'Biasa', 'snap' => 'Snap/Tertutup'];

    public function mount($items = [], $orderId = null)
    {
        $this->items = $items ?: [];
        $this->orderId = $orderId;
        
        // Pre-fetch options to keep the view clean
        $this->materialOptions = Material::pluck('name', 'id')->toArray();
        $this->sizeOptions = \App\Filament\Resources\Orders\OrderResource::getStoreSizeOptions();
        
        // Initialize bulkSzQty
        foreach ($this->sizeOptions as $key => $label) {
            $this->bulkSzQty[$key] = 0;
        }
    }

    public function addItem()
    {
        $this->items[] = [
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'product_name' => '',
            'production_category' => 'produksi',
            'size' => 'M', 
            'bahan_baju' => array_key_first($this->materialOptions),
            'price' => 0,
            'quantity' => 1,
            // Garment Specs
            'gender' => 'L',
            'sleeve_model' => 'pendek',
            'pocket_model' => 'tanpa_saku',
            'button_model' => 'biasa',
            'is_tunic' => false,
            'tunic_fee' => 0,
            'measurements' => [
                'LD' => null, 'PB' => null, 'PL' => null, 'LB' => null, 'LP' => null, 'LPh' => null
            ],
        ];
        
        $this->updatedItems();
    }

    public function removeItem($index)
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
        $this->updatedItems();
    }

    public function updateItem($index, $key, $value)
    {
        $this->items[$index][$key] = $value;
        $this->updatedItems();
    }

    public function clearAll()
    {
        $this->items = [];
        $this->updatedItems();
    }

    /**
     * Bulk Generate Items
     */
    public function generateBulk()
    {
        $material = Material::find($this->bulkMaterial);
        $materialName = $material ? $material->name : '';
        $bahanId = $this->bulkMaterial ?: null;
        $price = (int) $this->bulkPrice;

        $generatedCount = 0;

        // Generate normally sized items
        foreach ($this->bulkSzQty as $sizeKey => $qty) {
            $qty = (int) $qty;
            if ($qty > 0) {
                for ($i = 0; $i < $qty; $i++) {
                    $this->items[] = [
                        'id' => (string) \Illuminate\Support\Str::uuid(),
                        'product_name' => $materialName,
                        'production_category' => $this->bulkCategory,
                        'size' => $sizeKey,
                        'bahan_baju' => $bahanId,
                        'price' => $price,
                        'quantity' => 1,
                        // Apply bulk garment specs
                        'gender' => $this->bulkGender,
                        'sleeve_model' => $this->bulkSleeve,
                        'pocket_model' => $this->bulkPocket,
                        'button_model' => $this->bulkButtons,
                        'is_tunic' => (bool)$this->bulkIsTunic,
                        'tunic_fee' => (int)$this->bulkTunicFee,
                        'measurements' => [
                            'LD' => null, 'PB' => null, 'PL' => null, 'LB' => null, 'LP' => null, 'LPh' => null
                        ],
                    ];
                    $generatedCount++;
                }
            }
        }

        // Generate Custom (Ukur Badan) sized items
        $customQty = (int) $this->bulkCustomQty;
        if ($customQty > 0) {
            for ($i = 0; $i < $customQty; $i++) {
                $this->items[] = [
                    'id' => (string) \Illuminate\Support\Str::uuid(),
                    'product_name' => $materialName,
                    'production_category' => 'custom',
                    'size' => 'Custom',
                    'bahan_baju' => $bahanId,
                    'price' => $price,
                    'quantity' => 1,
                    // Apply bulk garment specs
                    'gender' => $this->bulkGender,
                    'sleeve_model' => $this->bulkSleeve,
                    'pocket_model' => $this->bulkPocket,
                    'button_model' => $this->bulkButtons,
                    'is_tunic' => (bool)$this->bulkIsTunic,
                    'tunic_fee' => (int)$this->bulkTunicFee,
                    'measurements' => [
                        'LD' => null, 'PB' => null, 'PL' => null, 'LB' => null, 'LP' => null, 'LPh' => null
                    ],
                ];
                $generatedCount++;
            }
        }
        
        if ($generatedCount === 0) {
            Notification::make()->title('Isi setidaknya satu Qty yang valid')->warning()->send();
            return;
        }

        // Reset Qty fields and modal
        foreach ($this->sizeOptions as $key => $label) {
            $this->bulkSzQty[$key] = 0;
        }
        $this->bulkCustomQty = 0;
        $this->bulkPrice = 0;
        $this->bulkMaterial = null;
        $this->bulkCategory = 'produksi';
        $this->bulkGender = 'L';
        $this->bulkSleeve = 'pendek';
        $this->bulkPocket = 'tanpa_saku';
        $this->bulkButtons = 'biasa';
        $this->bulkIsTunic = false;
        $this->bulkTunicFee = 0;

        $this->showBulkModal = false;
        $this->updatedItems();
        Notification::make()->title($generatedCount . ' item berhasil digenerate massal')->success()->send();
    }

    public function applyBulkPrice()
    {
        foreach ($this->items as &$item) {
            $item['price'] = $this->newBulkPrice;
        }
        $this->showPriceModal = false;
        $this->updatedItems();
        Notification::make()->title('Harga semua item berhasil diupdate')->success()->send();
    }

    /**
     * Item Detail Logic
     */
    public function openDetail($index)
    {
        $this->editingIndex = $index;
        $this->editingItem = $this->items[$index];
        $this->showDetailModal = true;
    }

    public function saveDetail()
    {
        $this->items[$this->editingIndex] = $this->editingItem;
        $this->showDetailModal = false;
        $this->editingIndex = null;
        $this->updatedItems();
        Notification::make()->title('Uraian item berhasil disimpan')->success()->send();
    }

    public function updatedItems()
    {
        // Emit event to sync with Filament hidden field
        $this->dispatch('spreadsheetUpdated', ['payload' => json_encode($this->items)]);
        
        // Calculate subtotal for sidebar refresh (if needed, or parent listens to spreadsheetUpdated)
        $subtotal = 0;
        foreach($this->items as $item) {
            $subtotal += ($item['price'] ?? 0) * ($item['quantity'] ?? 0);
        }
        
        $this->dispatch('refreshOrderSummary', ['subtotal' => $subtotal]);
    }

    public function render()
    {
        return view('livewire.order-items-spreadsheet');
    }
}
