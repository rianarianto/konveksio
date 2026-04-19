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
    public array $undoStack = [];
    public ?int $orderId = null;
    
    // Options for selects
    public array $materialOptions = [];
    public array $sizeOptions = [];
    // Form fields for Bulk Generate
    public $bulkMaterial;
    public $bulkVariant;
    public $bulkProduct;
    public $bulkColor;
    public array $baseMaterialOptions = [];
    public array $bulkVariantOptions = [];
    public $bulkCategory = 'produksi';
    public $bulkPrice;
    public $bulkCustomQty;
    public array $bulkCustomPeople = []; // [ ['name' => '', 'LD' => '', 'PB' => '', 'price' => 0] ]
    public array $bulkSzQty = []; // [ 'S' => 0, 'M' => 0, ... ]
    public array $productOptions = [];
    
    // New garment attributes for bulk generate
    public $bulkGender = 'L';
    public $bulkSleeve = 'pendek';
    public $bulkPocket = 'tanpa_saku';
    public $bulkButtons = 'biasa';
    public $bulkIsTunic = false;
    public $bulkTunicFee;

    // UI States
    public bool $showBulkModal = false;
    public bool $showPriceModal = false;
    public bool $showDetailModal = false;
    public bool $showBulkEditModal = false;
    public $newBulkPrice;
    public $editingIndex = null;
    public array $editingItem = [];

    // Advanced UI States
    public bool $useRecipientNames = true;
    public array $selectedItems = []; // Array of UUIDs
    public array $collapsedGroups = []; // Array of keys like 'category_product_gender'
    public $searchQuery = '';
    public $filterCategory = '';
    public array $bulkEditData = [
        'apply_size' => false, 'size' => 'M',
        'apply_bahan_baju' => false, 'bahan_baju' => null,
        'apply_gender' => false, 'gender' => 'L',
        'apply_sleeve_model' => false, 'sleeve_model' => 'pendek',
        'apply_pocket_model' => false, 'pocket_model' => 'tanpa_saku',
        'apply_button_model' => false, 'button_model' => 'biasa',
        'apply_is_tunic' => false, 'is_tunic' => false, 'tunic_fee' => 0,
        'apply_price' => false, 'price' => 0,
    ];

    public array $productionCategories = [
        'produksi' => 'Konveksi',
        'non_produksi' => 'Baju Jadi',
        'jasa' => 'Jasa',
    ];

    public array $sleeveOptions = ['pendek' => 'Pendek', 'panjang' => 'Panjang', '3/4' => '3/4'];
    public array $pocketOptions = ['tanpa_saku' => 'Tanpa Saku', 'tempel' => 'Tempel', 'bobok' => 'Bobok', 'double' => 'Double Saku'];
    public array $buttonOptions = ['biasa' => 'Biasa', 'snap' => 'Snap/Tertutup'];
    public array $commonColors = ['Hitam', 'Putih', 'Navy', 'Maroon', 'Merah', 'Abu Muda', 'Abu Tua', 'Hijau Botol', 'Kuning', 'Cokelat'];

    public function getHasNamesProperty()
    {
        return collect($this->items)->contains(fn($item) => !empty($item['person_name']));
    }

    public function mount($items = [], $orderId = null)
    {
        $this->items = $items ?: [];
        $this->orderId = $orderId;
        
        // Pre-fetch options to keep the view clean
        $tenantId = \Filament\Facades\Filament::getTenant()?->id;
        $this->materialOptions = \App\Models\MaterialVariant::join('materials', 'material_variants.material_id', '=', 'materials.id')
            ->when($tenantId, fn($q) => $q->where('materials.shop_id', $tenantId))
            ->select('material_variants.id', 'materials.name', 'material_variants.color_name', 'material_variants.color_code')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->id => [
                    'id' => $item->id,
                    'name' => $item->name . ($item->color_name ? " - " . $item->color_name : ""),
                    'color_code' => $item->color_code
                ]];
            })
            ->toArray();
        $this->baseMaterialOptions = \App\Models\Material::where('shop_id', $tenantId)
            ->pluck('name', 'id')
            ->toArray();

        $this->productOptions = \App\Filament\Resources\Orders\OrderResource::getSupplierProductOptions();
        $this->sizeOptions = \App\Filament\Resources\Orders\OrderResource::getStoreSizeOptions();
        
        foreach ($this->sizeOptions as $key => $label) {
            $this->bulkSzQty[$key] = null;
        }
    }

    public function updatedBulkMaterial($value)
    {
        if (!$value) {
            $this->bulkVariantOptions = [];
            $this->bulkVariant = null;
            $this->bulkColor = null;
            return;
        }

        $this->bulkVariantOptions = \App\Models\MaterialVariant::where('material_id', $value)
            ->get(['id', 'color_name', 'color_code'])
            ->toArray();

        $this->bulkVariant = null;
        $this->bulkColor = null;
    }

    public function updatedBulkVariant($value)
    {
        if ($value) {
            $variant = collect($this->bulkVariantOptions)->firstWhere('id', (int)$value);
            $this->bulkColor = $variant['color_name'] ?? '';
        }
    }

    public function addItem()
    {
        $this->items[] = [
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'product_name' => '',
            'person_name' => '',
            'production_category' => 'produksi',
            'size' => 'M', 
            'bahan_baju' => array_key_first($this->materialOptions),
            'warna' => '',
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
        $this->pushUndo();
        unset($this->items[$index]);
        $this->items = array_values($this->items);
        $this->updatedItems();
    }

    public function updateItem($index, $key, $value)
    {
        $this->pushUndo();
        $this->items[$index][$key] = $value;
        $this->updatedItems();
    }

    public function clearAll()
    {
        $this->pushUndo();
        $this->items = [];
        $this->updatedItems();
    }

    /**
     * Bulk Generate Items
     */
    public function generateBulk()
    {
        $this->pushUndo();
        $materialName = $this->baseMaterialOptions[$this->bulkMaterial] ?? '';
        $bahanId = ($this->bulkCategory === 'produksi') ? ($this->bulkVariant ?: null) : null;
        
        $price = (int) $this->bulkPrice;
        $generatedCount = 0;

        // Determine actual product name
        if ($this->bulkCategory === 'non_produksi') {
            $productName = strip_tags($this->productOptions[$this->bulkProduct] ?? 'Baju Jadi');
        } else {
            $productName = $materialName ?: '';
        }

        // Generate normally sized items
        foreach ($this->bulkSzQty as $sizeKey => $qty) {
            $qty = (int) $qty;
            if ($qty > 0) {
                for ($i = 0; $i < $qty; $i++) {
                    $this->items[] = [
                        'id' => (string) \Illuminate\Support\Str::uuid(),
                        'product_name' => $productName,
                        'person_name' => '',
                        'production_category' => $this->bulkCategory,
                        'size' => $sizeKey,
                        'bahan_baju' => $bahanId,
                        'warna' => $this->bulkColor ?: '',
                        'price' => $price,
                        'quantity' => 1,
                        // Apply bulk garment specs (only for Konveksi)
                        'gender' => ($this->bulkCategory === 'produksi') ? $this->bulkGender : 'L',
                        'sleeve_model' => ($this->bulkCategory === 'produksi') ? $this->bulkSleeve : 'pendek',
                        'pocket_model' => ($this->bulkCategory === 'produksi') ? $this->bulkPocket : 'tanpa_saku',
                        'button_model' => ($this->bulkCategory === 'produksi') ? $this->bulkButtons : 'biasa',
                        'is_tunic' => ($this->bulkCategory === 'produksi') ? (bool)$this->bulkIsTunic : false,
                        'tunic_fee' => ($this->bulkCategory === 'produksi') ? (int)$this->bulkTunicFee : 0,
                        'measurements' => [
                            'LD' => null, 'PB' => null, 'PL' => null, 'LB' => null, 'LP' => null, 'LPh' => null
                        ],
                    ];
                    $generatedCount++;
                }
            }
        }

        // Generate Custom (Ukur Badan) sized items from the list
        foreach ($this->bulkCustomPeople as $person) {
            if (empty($person['name']) && empty($person['LD'])) continue;
            
            $this->items[] = [
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'product_name' => $productName,
                'person_name' => $person['name'] ?? '',
                'production_category' => $this->bulkCategory, // Use dynamic category
                'size' => ($this->bulkCategory === 'jasa') ? '-' : 'Custom',
                'bahan_baju' => $bahanId,
                'warna' => ($this->bulkCategory === 'jasa') ? '' : ($this->bulkColor ?: ''),
                'price' => (int) ($person['price'] ?: $price),
                'quantity' => 1,
                // Apply bulk garment specs
                'gender' => $this->bulkGender,
                'sleeve_model' => $this->bulkSleeve,
                'pocket_model' => $this->bulkPocket,
                'button_model' => $this->bulkButtons,
                'is_tunic' => (bool)$this->bulkIsTunic,
                'tunic_fee' => (int)$this->bulkTunicFee,
                'measurements' => [
                    'LD' => $person['LD'] ?? null, 
                    'PB' => $person['PB'] ?? null, 
                    'PL' => $person['PL'] ?? null, 
                    'LB' => null, 'LP' => null, 'LPh' => null
                ],
            ];
            $generatedCount++;
        }
        
        // Handle Legacy bulkCustomQty / Jasa Qty
        $legacyQty = (int) $this->bulkCustomQty;
        if ($legacyQty > 0) {
            for ($i = 0; $i < $legacyQty; $i++) {
                $this->items[] = [
                    'id' => (string) \Illuminate\Support\Str::uuid(),
                    'product_name' => ($this->bulkCategory === 'jasa') ? 'Jasa Produksi' : $productName,
                    'person_name' => '',
                    'production_category' => $this->bulkCategory, // Use dynamic category
                    'size' => ($this->bulkCategory === 'jasa') ? '-' : 'Custom',
                    'bahan_baju' => $bahanId,
                    'warna' => ($this->bulkCategory === 'jasa') ? '' : ($this->bulkColor ?: ''),
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

        foreach ($this->sizeOptions as $key => $label) {
            $this->bulkSzQty[$key] = null;
        }
        $this->bulkCustomQty = null;
        $this->bulkCustomPeople = [];
        $this->bulkPrice = null;
        $this->bulkMaterial = null;
        $this->bulkProduct = null;
        $this->bulkColor = null;
        $this->bulkCategory = 'produksi';
        $this->bulkGender = 'L';
        $this->bulkSleeve = 'pendek';
        $this->bulkPocket = 'tanpa_saku';
        $this->bulkButtons = 'biasa';
        $this->bulkIsTunic = false;
        $this->bulkTunicFee = null;

        $this->showBulkModal = false;
        $this->updatedItems();
        Notification::make()->title($generatedCount . ' item berhasil digenerate massal')->success()->send();
    }

    public function applyBulkPrice()
    {
        $this->pushUndo();
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
        
        // Calculate subtotal for sidebar refresh
        $subtotal = 0;
        foreach($this->items as $item) {
            $subtotal += (float)($item['price'] ?? 0) * (int)($item['quantity'] ?? 0);
        }
        
        $this->dispatch('refreshOrderSummary', ['subtotal' => $subtotal]);
    }

    /**
     * Advanced Spreadsheet Features
     */
    public function toggleUseRecipientNames()
    {
        // Check if there are names that will be lost
        $hasNames = collect($this->items)->contains(fn($item) => !empty($item['person_name']));
        
        if ($this->useRecipientNames && $hasNames) {
            // This will be handled by browser confirmation in the view
            // If the view confirms, it will call this with a param or we handle it here
            $this->useRecipientNames = false;
        } else {
            $this->useRecipientNames = !$this->useRecipientNames;
        }

        $this->updatedItems();
    }

    public function toggleSelectAll($currentIds)
    {
        $currentIds = json_decode($currentIds, true);
        if (count(array_intersect($this->selectedItems, $currentIds)) === count($currentIds)) {
            $this->selectedItems = array_diff($this->selectedItems, $currentIds);
        } else {
            $this->selectedItems = array_unique(array_merge($this->selectedItems, $currentIds));
        }
    }

    public function bulkDelete()
    {
        $this->pushUndo();
        $this->items = collect($this->items)
            ->reject(fn($item) => in_array($item['id'], $this->selectedItems))
            ->values()
            ->toArray();
        
        $this->selectedItems = [];
        $this->updatedItems();
        Notification::make()->title('Item terpilih berhasil dihapus')->success()->send();
    }

    public function applyBulkEdit()
    {
        $this->pushUndo();
        foreach ($this->items as &$item) {
            if (in_array($item['id'], $this->selectedItems)) {
                if ($this->bulkEditData['apply_size']) $item['size'] = $this->bulkEditData['size'];
                if ($this->bulkEditData['apply_bahan_baju']) $item['bahan_baju'] = $this->bulkEditData['bahan_baju'];
                if ($this->bulkEditData['apply_gender']) $item['gender'] = $this->bulkEditData['gender'];
                if ($this->bulkEditData['apply_sleeve_model']) $item['sleeve_model'] = $this->bulkEditData['sleeve_model'];
                if ($this->bulkEditData['apply_pocket_model']) $item['pocket_model'] = $this->bulkEditData['pocket_model'];
                if ($this->bulkEditData['apply_button_model']) $item['button_model'] = $this->bulkEditData['button_model'];
                if ($this->bulkEditData['apply_is_tunic']) {
                    $item['is_tunic'] = (bool)$this->bulkEditData['is_tunic'];
                    $item['tunic_fee'] = (int)$this->bulkEditData['tunic_fee'];
                }
                if ($this->bulkEditData['apply_price']) $item['price'] = (int)$this->bulkEditData['price'];
            }
        }

        $this->showBulkEditModal = false;
        $this->selectedItems = [];
        $this->updatedItems();
        Notification::make()->title('Update massal berhasil diterapkan')->success()->send();
    }

    protected function getItemFingerprint($item)
    {
        // Identity excluding UI state and person name
        return md5(json_encode([
            $item['product_name'] ?? '',
            $item['production_category'] ?? '',
            $item['size'] ?? '',
            $item['bahan_baju'] ?? '',
            $item['warna'] ?? '',
            $item['price'] ?? 0,
            $item['gender'] ?? '',
            $item['sleeve_model'] ?? '',
            $item['pocket_model'] ?? '',
            $item['button_model'] ?? '',
            $item['is_tunic'] ?? '',
            $item['measurements'] ?? [],
        ]));
    }

    public function toggleGroup($groupKey)
    {
        if (in_array($groupKey, $this->collapsedGroups)) {
            $this->collapsedGroups = array_diff($this->collapsedGroups, [$groupKey]);
        } else {
            $this->collapsedGroups[] = $groupKey;
        }
    }

    /**
     * Undo History
     */
    protected function pushUndo()
    {
        $this->undoStack[] = $this->items;
        // Keep max 20 snapshots to avoid memory bloat
        if (count($this->undoStack) > 20) {
            array_shift($this->undoStack);
        }
    }

    public function undo()
    {
        if (empty($this->undoStack)) {
            Notification::make()->title('Tidak ada perubahan untuk di-undo')->warning()->send();
            return;
        }

        $this->items = array_pop($this->undoStack);
        $this->updatedItems();
        Notification::make()->title('Undo berhasil — perubahan terakhir dibatalkan')->success()->send();
    }

    public function render()
    {
        $displayItems = collect($this->items);

        // 1. Filtering
        if ($this->filterCategory) {
            $displayItems = $displayItems->where('production_category', $this->filterCategory);
        }

        if ($this->searchQuery) {
            $q = strtolower($this->searchQuery);
            $displayItems = $displayItems->filter(fn($item) => 
                str_contains(strtolower($item['product_name'] ?? ''), $q) || 
                str_contains(strtolower($item['person_name'] ?? ''), $q) ||
                str_contains(strtolower($item['warna'] ?? ''), $q)
            );
        }

        // 2. Smart Merging (Identity Fusion)
        if (!$this->useRecipientNames) {
            $merged = [];
            foreach ($displayItems as $item) {
                $hash = $this->getItemFingerprint($item);
                if (isset($merged[$hash])) {
                    $merged[$hash]['quantity'] += $item['quantity'];
                } else {
                    $merged[$hash] = $item;
                    $merged[$hash]['person_name'] = ''; // Clear names if merged
                }
            }
            $displayItems = collect(array_values($merged));
        }

        // 3. Multi-level Grouping (Tier 1: Category -> Tier 2: Product Name -> Tier 3: Gender)
        // Ensure keys are consistent even if empty
        $displayItems = $displayItems->map(function($item) {
            $item['group_product'] = !empty($item['product_name']) ? $item['product_name'] : 'Tanpa Produk';
            $item['group_gender'] = $item['gender'] ?? 'L';
            return $item;
        });

        $groupedItems = $displayItems->groupBy([
            'production_category',
            'group_product',
            'group_gender'
        ]);

        return view('livewire.order-items-spreadsheet', [
            'groupedItems' => $groupedItems,
            'displayIds' => $displayItems->pluck('id')->toArray()
        ]);
    }

    public function addBulkPerson()
    {
        $this->bulkCustomPeople[] = [
            'name' => '',
            'LD' => '',
            'PB' => '',
            'PL' => '',
            'price' => null,
        ];
    }

    public function removeBulkPerson($index)
    {
        unset($this->bulkCustomPeople[$index]);
        $this->bulkCustomPeople = array_values($this->bulkCustomPeople);
    }
}
