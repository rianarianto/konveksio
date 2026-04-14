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
    public $bulkQty = 1;
    public $bulkCategory = 'produksi';
    public $bulkSizes = [];
    public $showBulkModal = false;
    public $newBulkPrice = 0;
    public $showPriceModal = false;
    public array $productionCategories = [
        'produksi' => 'Produksi',
        'custom' => 'Custom',
        'non_produksi' => 'Non-Produksi',
        'jasa' => 'Jasa',
    ];

    public function mount($items = [], $orderId = null)
    {
        $this->items = $items ?: [];
        $this->orderId = $orderId;
        
        // Pre-fetch options to keep the view clean
        $this->materialOptions = Material::pluck('name', 'id')->toArray();
        $this->sizeOptions = \App\Filament\Resources\Orders\OrderResource::getStoreSizeOptions();
    }

    public function addItem()
    {
        $this->items[] = [
            'id' => null,
            'product_name' => '',
            'production_category' => 'produksi',
            'size' => 'M', // Default
            'bahan_baju' => array_key_first($this->materialOptions),
            'price' => 0,
            'quantity' => 1,
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
        if (!$this->bulkMaterial || empty($this->bulkSizes)) {
            Notification::make()->title('Pilih bahan dan size terlebih dahulu')->danger()->send();
            return;
        }

        $material = Material::find($this->bulkMaterial);
        if (!$material) return;

        foreach ($this->bulkSizes as $size) {
            $this->items[] = [
                'id' => null,
                'product_name' => $material->name,
                'production_category' => $this->bulkCategory,
                'size' => $size,
                'bahan_baju' => $this->bulkMaterial,
                'price' => $material->price ?? 0,
                'quantity' => (int) $this->bulkQty,
            ];
        }
        
        $this->showBulkModal = false;
        $this->updatedItems();
        Notification::make()->title('Item berhasil digenerate massal')->success()->send();
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
