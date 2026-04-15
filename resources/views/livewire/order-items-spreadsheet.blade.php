<div 
    class="space-y-4"
    x-data="{
        updatePayload(payload) {
             const input = document.getElementById('order_items_payload');
                if (input) {
                    input.value = payload;
                    input.dispatchEvent(new Event('input'));
                }
        }
    }"
    @spreadsheet-updated.window="updatePayload($event.detail.payload)"
>
    <!-- Header Actions -->
    <div class="flex flex-wrap items-center justify-between gap-4 p-4 bg-white border border-gray-200 rounded-xl shadow-sm ring-1 ring-gray-950/5">
        <div class="flex flex-wrap items-center gap-3">
            <button wire:click="addItem" type="button" class="inline-flex items-center px-4 py-2 text-sm font-bold text-white bg-primary-600 rounded-lg hover:bg-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all shadow-sm">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                Tambah Baris
            </button>
            
            <button @click="$wire.set('showBulkModal', true)" type="button" class="inline-flex items-center px-3 py-2 text-sm font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:ring-2 focus:ring-primary-500/10 transition-all">
                <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                Bulk Generate
            </button>
            
            <button @click="$wire.set('showPriceModal', true)" type="button" class="inline-flex items-center px-3 py-2 text-sm font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-all">
                <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                Ganti Harga
            </button>
        </div>

        <div class="flex items-center gap-6">
             <div class="text-right">
                <div class="text-[10px] font-bold text-gray-400 uppercase tracking-widest leading-none mb-1">Total Baris</div>
                <div class="text-base font-bold text-gray-900 leading-none">{{ count($items) }}</div>
             </div>
             <button wire:click="clearAll" onclick="return confirm('Apakah Anda yakin ingin menghapus semua item?')" type="button" class="p-2 text-gray-400 hover:text-danger-600 transition-colors" title="Kosongkan Tabel">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
            </button>
        </div>
    </div>

    <!-- Table Container -->
    <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden ring-1 ring-gray-950/5">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 table-fixed">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="w-12 px-3 py-3 text-center text-[10px] font-bold text-gray-500 uppercase tracking-widest">#</th>
                        <th class="w-1/4 px-4 py-3 text-left text-[10px] font-bold text-gray-500 uppercase tracking-widest">Produk / Pemesan</th>
                        <th class="w-24 px-3 py-3 text-left text-[10px] font-bold text-gray-500 uppercase tracking-widest">Size</th>
                        <th class="w-32 px-3 py-3 text-left text-[10px] font-bold text-gray-500 uppercase tracking-widest">Kategori</th>
                        <th class="w-1/4 px-4 py-3 text-left text-[10px] font-bold text-gray-500 uppercase tracking-widest">Bahan</th>
                        <th class="w-24 px-3 py-3 text-center text-[10px] font-bold text-gray-500 uppercase tracking-widest">Specs</th>
                        <th class="w-36 px-4 py-3 text-right text-[10px] font-bold text-gray-500 uppercase tracking-widest">Harga Unit</th>
                        <th class="w-24 px-3 py-3 text-center text-[10px] font-bold text-gray-500 uppercase tracking-widest">Qty</th>
                        <th class="w-44 px-4 py-3 text-right text-[10px] font-bold text-primary-600 uppercase tracking-widest bg-primary-50/30">Subtotal</th>
                        <th class="w-12 px-3 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($items as $index => $item)
                        <tr class="hover:bg-gray-50/50 transition-colors group">
                            <td class="px-3 py-4 text-center text-xs font-medium text-gray-400 bg-gray-50/30 border-r border-gray-100">
                                {{ $index + 1 }}
                            </td>
                            <td class="px-4 py-4">
                                <input 
                                    type="text" 
                                    wire:model.blur="items.{{ $index }}.product_name"
                                    placeholder="Input nama produk..."
                                    class="w-full text-xs border-0 focus:ring-0 bg-transparent p-0 font-medium text-gray-900 placeholder-gray-300"
                                >
                            </td>
                            <td class="px-3 py-4">
                                <select 
                                    wire:model.change="items.{{ $index }}.size"
                                    class="w-full text-xs border-0 focus:ring-0 bg-transparent p-0 text-gray-600 font-medium cursor-pointer"
                                >
                                    @foreach($sizeOptions as $val => $label)
                                        <option value="{{ $val }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="px-3 py-4">
                                <select 
                                    wire:model.change="items.{{ $index }}.production_category"
                                    class="w-full text-[10px] font-bold uppercase tracking-widest border border-gray-100 rounded bg-gray-50 p-1 px-2 text-primary-600 focus:ring-1 focus:ring-primary-500 focus:bg-white transition-all cursor-pointer"
                                >
                                    @foreach($productionCategories as $val => $label)
                                        <option value="{{ $val }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="px-4 py-4">
                                <select 
                                    wire:model.change="items.{{ $index }}.bahan_baju"
                                    class="w-full text-xs border-0 focus:ring-0 bg-transparent p-0 text-gray-600 font-medium cursor-pointer"
                                >
                                    @foreach($materialOptions as $val => $label)
                                        <option value="{{ $val }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="px-3 py-4 text-center">
                                <button 
                                    wire:click="openDetail({{ $index }})"
                                    type="button"
                                    class="inline-flex items-center px-2 py-1 text-[10px] font-bold text-primary-600 bg-primary-50 rounded hover:bg-primary-100 transition-colors border border-primary-100 shadow-sm"
                                >
                                    SPECS
                                </button>
                            </td>
                            <td class="px-4 py-4 text-right">
                                <div class="flex items-center justify-end relative">
                                    <span class="text-[9px] font-bold text-gray-300 mr-1 select-none">RP</span>
                                    <input 
                                        type="number" 
                                        wire:model.blur="items.{{ $index }}.price"
                                        class="w-28 text-sm text-right border-0 focus:ring-1 focus:ring-primary-500 rounded bg-transparent hover:bg-gray-100/50 p-1 font-bold text-gray-900 transition-all"
                                    >
                                </div>
                            </td>
                            <td class="px-3 py-4 text-center">
                                <input 
                                    type="number" 
                                    wire:model.blur="items.{{ $index }}.quantity"
                                    class="w-14 text-sm text-center border-0 focus:ring-1 focus:ring-primary-500 rounded bg-transparent hover:bg-gray-100/50 p-1 font-bold text-gray-900 transition-all"
                                >
                            </td>
                            <td class="px-4 py-4 text-right bg-primary-50/20">
                                 <span class="text-sm font-bold text-primary-600">
                                    {{ number_format($item['price'] * $item['quantity'], 0, ',', '.') }}
                                 </span>
                            </td>
                            <td class="px-3 py-4 text-center">
                                <button 
                                    wire:click="removeItem({{ $index }})"
                                    type="button"
                                    class="text-gray-300 hover:text-danger-500 p-1 transition-colors opacity-0 group-hover:opacity-100"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="px-4 py-12 text-center text-gray-400">
                                <p class="text-xs font-medium uppercase tracking-widest">Belum ada item pesanan.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Estimasi Subtotal -->
    @if($items)
    <div class="flex justify-end pt-2">
        <div class="text-right">
            <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest block mb-1">Estimasi Subtotal</span>
            <div class="text-2xl font-bold text-primary-600 tracking-tight">
                Rp {{ number_format(collect($items)->sum(fn($i) => $i['price'] * $i['quantity']), 0, ',', '.') }}
            </div>
        </div>
    </div>
    @endif

    <!-- State Sync Label -->
    <div class="flex items-center justify-between text-[9px] font-bold uppercase tracking-widest text-gray-300 px-1 mt-6">
        <span>Stateless Spreadsheet Engine v2.5</span>
        <span class="flex items-center gap-1.5 font-bold"><span class="w-1.5 h-1.5 bg-green-500 rounded-full animate-pulse"></span> AUTO-SYNC ACTIVE</span>
    </div>

    <!-- Modals -->

    <!-- Bulk Generate Modal -->
    <div 
        x-show="$wire.showBulkModal" 
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-950/75 backdrop-blur-sm"
        style="display: none;"
    >
        <div 
            @click.away="$wire.set('showBulkModal', false)"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            class="w-full max-w-xl bg-white rounded-xl shadow-2xl overflow-hidden ring-1 ring-gray-950/5"
        >
            <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                <div>
                    <h3 class="text-sm font-bold text-gray-900 uppercase">Bulk Generation</h3>
                    <p class="text-[10px] text-gray-500 font-medium">Input massal secara instan.</p>
                </div>
                <button @click="$wire.set('showBulkModal', false)" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <div class="p-6 space-y-6 max-h-[85vh] overflow-y-auto scrollbar-thin">
                <!-- Group 1: Production -->
                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-1">
                        <label class="text-xs font-bold text-gray-700 ml-1">Katalog Produk / Bahan</label>
                        <select wire:model="bulkMaterial" class="w-full text-xs border-gray-300 rounded-lg focus:border-primary-500 focus:ring-primary-500 shadow-sm h-9">
                            <option value="">-- Pilih Katalog --</option>
                            @foreach($materialOptions as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="space-y-1">
                        <label class="text-xs font-bold text-gray-700 ml-1">Kategori Produksi</label>
                        <select wire:model="bulkCategory" class="w-full text-xs border-gray-300 rounded-lg focus:border-primary-500 focus:ring-primary-500 shadow-sm h-9">
                            @foreach($productionCategories as $val => $label)
                                <option value="{{ $val }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <!-- Group 2: Specs -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    @foreach(['bulkGender' => 'Gender', 'bulkSleeve' => 'Lengan', 'bulkPocket' => 'Saku', 'bulkButtons' => 'Kancing'] as $model => $label)
                        <div class="space-y-1">
                            <label class="text-[11px] font-bold text-gray-600 ml-1">{{ $label }}</label>
                            <select wire:model="{{ $model }}" class="w-full text-xs border-gray-300 rounded-lg focus:border-primary-500 focus:ring-primary-500 shadow-sm h-8">
                                @if($model === 'bulkGender')
                                    <option value="L">L-laki</option>
                                    <option value="P">P-uan</option>
                                @else
                                    @foreach(${$model === 'bulkSleeve' ? 'sleeveOptions' : ($model === 'bulkPocket' ? 'pocketOptions' : 'buttonOptions')} as $val => $optLabel)
                                        <option value="{{ $val }}">{{ $optLabel }}</option>
                                    @endforeach
                                @endif
                            </select>
                        </div>
                    @endforeach
                </div>

                <!-- Tunik -->
                <div class="flex items-center gap-4 p-3 bg-gray-50/50 rounded-lg border border-gray-200">
                    <div class="flex items-center gap-2">
                        <input type="checkbox" wire:model.live="bulkIsTunic" class="w-4 h-4 text-primary-600 border-gray-300 rounded focus:ring-primary-500">
                        <label class="text-xs font-bold text-gray-900">Model Tunik?</label>
                    </div>
                    @if($bulkIsTunic)
                        <div class="flex-1 max-w-[150px] relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-[10px] font-bold text-gray-400">RP</span>
                            <input type="number" wire:model="bulkTunicFee" class="w-full pl-9 py-1 text-xs border-gray-300 rounded-lg h-8 focus:border-primary-500 focus:ring-primary-500">
                        </div>
                    @endif
                </div>

                <!-- Sizes -->
                <div class="space-y-3 pt-2">
                    <h4 class="text-[10px] font-bold text-gray-400 uppercase tracking-wider text-center">Jumlah per Ukuran (Pcs)</h4>
                    <div class="bg-gray-50/50 p-4 border border-gray-200 rounded-xl">
                        <div class="grid grid-cols-4 md:grid-cols-8 gap-2 mb-4">
                            @foreach($sizeOptions as $val => $label)
                                <div class="space-y-1">
                                    <label class="block text-[10px] font-bold text-gray-400 text-center">{{ $label }}</label>
                                    <input type="number" wire:model="bulkSzQty.{{ $val }}" class="w-full text-center text-xs font-bold border-gray-300 rounded-lg h-9 shadow-sm focus:border-primary-500 bg-white">
                                </div>
                            @endforeach
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                             <div class="space-y-1">
                                <label class="text-[11px] font-bold text-gray-900 ml-1">Ukur Badan</label>
                                <input type="number" wire:model="bulkCustomQty" class="w-full text-center text-xs font-bold border-gray-300 rounded-lg h-10 shadow-sm bg-white" placeholder="0">
                            </div>
                            <div class="space-y-1">
                                <label class="text-[11px] font-bold text-gray-900 ml-1">Harga Satuan</label>
                                <div class="relative">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-[10px] font-bold text-gray-400">RP</span>
                                    <input type="number" wire:model="bulkPrice" class="w-full pl-8 pr-3 text-sm font-bold border-gray-300 rounded-lg h-10 shadow-sm bg-white" placeholder="0">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex justify-end gap-3">
                 <button @click="$wire.set('showBulkModal', false)" class="px-4 py-2 text-xs font-bold text-gray-400 uppercase hover:text-gray-600 transition-colors">
                    CANCEL
                </button>
                <button wire:click="generateBulk" class="px-6 py-2 bg-primary-600 text-white text-xs font-bold rounded-lg hover:bg-primary-500 transition-all shadow-md uppercase">
                    GENERATE
                </button>
            </div>
        </div>
    </div>

    <!-- Update Price Modal -->
    <div 
        x-show="$wire.showPriceModal" 
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-950/75 backdrop-blur-sm"
        style="display: none;"
    >
        <div 
            @click.away="$wire.set('showPriceModal', false)"
            class="w-full max-w-sm bg-white rounded-xl shadow-2xl overflow-hidden ring-1 ring-gray-950/5"
        >
            <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50">
                <h3 class="text-sm font-bold text-gray-900 uppercase">Update Harga Masal</h3>
            </div>
            <div class="p-6 space-y-6">
                <div class="space-y-1.5 text-center">
                    <label class="text-xs font-bold text-gray-400 uppercase tracking-wider">Masukkan Harga Satuan Baru</label>
                    <div class="relative">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-xs font-bold text-gray-300">RP</span>
                        <input type="number" wire:model="newBulkPrice" class="w-full pl-10 pr-4 py-3 text-2xl font-bold border-gray-300 rounded-xl focus:border-primary-500 focus:ring-primary-500 shadow-sm text-center" placeholder="0">
                    </div>
                </div>
                <div class="flex flex-col gap-2">
                    <button wire:click="applyBulkPrice" class="w-full py-3 bg-primary-600 text-white text-xs font-bold rounded-lg hover:bg-primary-500 transition-all shadow-md uppercase">
                        APPLY TO ALL
                    </button>
                    <button @click="$wire.set('showPriceModal', false)" class="w-full py-2 text-[10px] font-bold text-gray-400 uppercase tracking-widest hover:text-gray-600 transition-colors">
                        CLOSE
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Specs Modal -->
    <div 
        x-show="$wire.showDetailModal" 
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-950/75 backdrop-blur-sm"
        style="display: none;"
    >
        <div 
            @click.away="$wire.set('showDetailModal', false)"
            class="w-full max-w-lg bg-white rounded-xl shadow-2xl overflow-hidden ring-1 ring-gray-950/5"
        >
            <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50 flex justify-between items-center">
                <div>
                    <h3 class="text-sm font-bold text-gray-900 uppercase">Spesifikasi Detail</h3>
                    <p class="text-[10px] text-gray-500 font-medium tracking-tight">Kustomisasi mendalam per item.</p>
                </div>
                <button @click="$wire.set('showDetailModal', false)" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <div class="p-6 space-y-6 max-h-[85vh] overflow-y-auto scrollbar-thin">
                @if($editingIndex !== null)
                <!-- Garment Model -->
                <div class="grid grid-cols-2 gap-4">
                    @foreach(['gender' => 'Gender', 'sleeve_model' => 'Lengan', 'pocket_model' => 'Saku', 'button_model' => 'Kancing'] as $model => $label)
                        <div class="space-y-1">
                            <label class="text-xs font-bold text-gray-700 ml-1">{{ $label }}</label>
                            <select wire:model="editingItem.{{ $model }}" class="w-full text-xs border-gray-300 rounded-lg focus:border-primary-500 h-9">
                                @if($model === 'gender')
                                    <option value="L">Laki-laki</option>
                                    <option value="P">Perempuan</option>
                                @else
                                    @foreach(${$model === 'sleeve_model' ? 'sleeveOptions' : ($model === 'pocket_model' ? 'pocketOptions' : 'buttonOptions')} as $val => $optLabel)
                                        <option value="{{ $val }}">{{ $optLabel }}</option>
                                    @endforeach
                                @endif
                            </select>
                        </div>
                    @endforeach
                </div>

                <div class="flex items-center gap-4 p-3 bg-gray-50/50 rounded-lg border border-gray-200">
                    <div class="flex items-center gap-2">
                        <input type="checkbox" wire:model.live="editingItem.is_tunic" class="w-4 h-4 text-primary-600 border-gray-300 rounded focus:ring-primary-500">
                        <label class="text-xs font-bold text-gray-900">Pakai Tunik?</label>
                    </div>
                    @if($editingItem['is_tunic'] ?? false)
                        <div class="flex-1 max-w-[150px] relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-[10px] font-bold text-gray-400">RP</span>
                            <input type="number" wire:model="editingItem.tunic_fee" class="w-full pl-9 py-1 text-xs border-gray-300 rounded-lg h-8 focus:border-primary-500">
                        </div>
                    @endif
                </div>

                <!-- Custom Measurements -->
                <div class="space-y-3 pt-2">
                    <h4 class="text-[10px] font-bold text-gray-400 uppercase tracking-wider text-center border-b border-gray-100 pb-1">Ukur Badan (Custom)</h4>
                    <div class="grid grid-cols-3 gap-3">
                        @foreach(['LD' => 'L. Dada', 'PB' => 'P. Baju', 'PL' => 'P. Lengan', 'LB' => 'L. Bahu', 'LP' => 'L. Perut', 'LPh' => 'L. Paha'] as $key => $label)
                            <div class="space-y-1 text-center">
                                <label class="block text-[10px] font-bold text-gray-500 uppercase">{{ $label }}</label>
                                <div class="relative">
                                    <input type="number" wire:model="editingItem.measurements.{{ $key }}" step="0.5" class="w-full text-center text-xs font-bold border-gray-300 rounded-lg h-9 shadow-sm bg-gray-50/10 focus:bg-white pr-5">
                                    <span class="absolute right-2 top-1/2 -translate-y-1/2 text-[8px] font-bold text-gray-300">CM</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>

            <!-- Modal Footer -->
            <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex justify-end gap-3">
                <button @click="$wire.set('showDetailModal', false)" class="px-4 py-2 text-xs font-bold text-gray-400 uppercase hover:text-gray-600">
                    CLOSE
                </button>
                <button wire:click="saveDetail" class="px-6 py-2 bg-primary-600 text-white text-xs font-bold rounded-lg hover:bg-primary-500 transition-all shadow-md uppercase">
                    SAVE CHANGES
                </button>
            </div>
        </div>
    </div>
</div>
