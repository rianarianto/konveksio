<div class="space-y-4" x-data="{
        updatePayload(payload) {
             const input = document.getElementById('order_items_payload');
                if (input) {
                    input.value = payload;
                    input.dispatchEvent(new Event('input'));
                }
        }
    }" @spreadsheet-updated.window="updatePayload($event.detail.payload)">
    <!-- Header Actions -->
    <!-- Toolbar Utama -->
    <div class="p-5 bg-white rounded-2xl shadow-sm border border-gray-200 space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <!-- Left Side: Basic Add & Bulk -->
            <div class="flex flex-wrap items-center gap-2">
                <x-filament::button 
                    @click="$dispatch('open-modal', { id: 'bulk-modal' })" 
                    icon="heroicon-m-squares-plus" 
                    color="primary">
                    Bulk Generate
                </x-filament::button>

                <div class="h-8 w-px bg-gray-200 mx-1"></div>

                <!-- Recipient Toggle -->
                <x-filament::button 
                    @click="if ($wire.hasNames && $wire.useRecipientNames) { if (confirm('Mematikan mode nama akan menggabungkan item identik dan menghapus nama penenerima yang sudah diketik. Lanjut?')) { $wire.toggleUseRecipientNames() } } else { $wire.toggleUseRecipientNames() }"
                    icon="heroicon-m-users" 
                    color="gray"
                    outlined>
                    {{ $useRecipientNames ? 'Mode: Pakai Nama' : 'Mode: Gabung Qty' }}
                </x-filament::button>
            </div>

            <!-- Right Side: Analytics & Quick Actions -->
            <div class="flex items-center gap-4">
                <div class="px-4 py-2 bg-gray-50 rounded-xl border border-gray-100 flex items-center gap-3">
                    <div class="text-right">
                        <p class="text-[9px] font-bold text-gray-400 uppercase tracking-widest leading-none mb-1">Items</p>
                        <p class="text-sm font-black text-gray-900 leading-none">{{ count($items) }}</p>
                    </div>
                    @if(count($selectedItems) > 0)
                        <div class="h-8 w-px bg-gray-200"></div>
                        <div class="text-right">
                            <p class="text-[9px] font-bold text-primary-500 uppercase tracking-widest leading-none mb-1">Terpilih</p>
                            <p class="text-sm font-black text-primary-600 leading-none">{{ count($selectedItems) }}</p>
                        </div>
                    @endif
                </div>

                <button wire:click="clearAll" onclick="return confirm('Hapus semua item?')" type="button"
                    class="p-2.5 text-gray-400 hover:text-danger-600 hover:bg-danger-50 rounded-xl transition-all">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Filter & Search Bar -->
        <div class="flex flex-wrap items-center gap-3 pt-2">
            <div class="flex-1 max-w-sm">
                <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass">
                    <x-filament::input
                        type="text"
                        wire:model.live="searchQuery"
                        placeholder="Cari nama, produk atau warna..."
                    />
                </x-filament::input.wrapper>
            </div>

            <div>
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="filterCategory">
                        <option value="">Semua Kategori</option>
                        @foreach($productionCategories as $val => $label)
                            <option value="{{ $val }}">{{ $label }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>

            @if(count($selectedItems) > 0)
                <div class="flex items-center gap-2 animate-in fade-in slide-in-from-left-2 duration-300">
                    <div class="h-6 w-px bg-gray-200 mx-1"></div>
                    <x-filament::button 
                        @click="$dispatch('open-modal', { id: 'bulk-edit-modal' })" 
                        icon="heroicon-m-pencil-square" 
                        size="sm">
                        Edit Massal ({{ count($selectedItems) }})
                    </x-filament::button>
                    
                    <x-filament::button 
                        wire:click="bulkDelete" 
                        wire:confirm="Hapus item terpilih secara permanen?" 
                        color="danger" 
                        size="sm">
                        Hapus Terpilih
                    </x-filament::button>
                </div>
            @endif
        </div>
    </div>

    <!-- Table Container -->
    <div class="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden min-h-[400px]">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 table-fixed">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="w-12 px-3 py-3 text-center">
                            <input type="checkbox" 
                                @click="$wire.toggleSelectAll(JSON.stringify(@js($displayIds)))"
                                {{ count(array_intersect($selectedItems, $displayIds)) === count($displayIds) && count($displayIds) > 0 ? 'checked' : '' }}
                                class="rounded border-gray-300 text-primary-600 focus:ring-primary-500 cursor-pointer">
                        </th>
                        @if($useRecipientNames)
                        <th class="w-40 px-4 py-3 text-left text-[10px] font-bold text-gray-400 uppercase tracking-widest">
                            Penerima</th>
                        @endif
                        <th class="w-32 px-3 py-3 text-left text-[10px] font-bold text-gray-400 uppercase tracking-widest">
                            Size</th>
                        <th class="w-48 px-4 py-3 text-left text-[10px] font-bold text-gray-400 uppercase tracking-widest">
                            Bahan</th>
                        <th class="w-20 px-3 py-3 text-left text-[10px] font-bold text-gray-400 uppercase tracking-widest">
                            Gender</th>
                        <th class="w-28 px-3 py-3 text-left text-[10px] font-bold text-gray-400 uppercase tracking-widest">
                            Lengan</th>
                        <th class="w-28 px-3 py-3 text-left text-[10px] font-bold text-gray-400 uppercase tracking-widest">
                            Saku</th>
                        <th class="w-28 px-3 py-3 text-left text-[10px] font-bold text-gray-400 uppercase tracking-widest">
                            Kancing</th>
                        <th class="w-32 px-3 py-3 text-center text-[10px] font-bold text-gray-400 uppercase tracking-widest">
                            Req. Tambahan</th>
                        <th class="w-28 px-4 py-3 text-right text-[10px] font-bold text-gray-400 uppercase tracking-widest">
                            Harga Unit</th>
                        <th class="w-12 px-3 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    @forelse($groupedItems as $category => $products)
                        @foreach($products as $productName => $genders)
                            @php 
                                $catLabel = $productionCategories[$category] ?? $category;
                                $group1Key = md5($category . $productName);
                                $isCollapsed1 = in_array($group1Key, $collapsedGroups);
                                $productTotalQty = collect($genders)->flatten(1)->sum('quantity');
                            @endphp
                            
                            <!-- Tier 1: Category - Product Name -->
                            <tr wire:click="toggleGroup('{{ $group1Key }}')" 
                                class="border-y border-gray-200 cursor-pointer hover:bg-gray-200 transition-colors">
                                <td colspan="{{ $useRecipientNames ? '11' : '10' }}" 
                                    style="background-color: #f3f4f6 !important;"
                                    class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <svg class="w-4 h-4 text-gray-500 transition-transform {{ $isCollapsed1 ? '-rotate-90' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                        </svg>
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs font-black uppercase tracking-widest text-gray-700">
                                                {{ $catLabel }} — {{ $productName }}
                                            </span>
                                            <span class="text-[10px] font-bold text-gray-400 bg-gray-200/50 px-2 py-0.5 rounded">
                                                — {{ $productTotalQty }} PCS
                                            </span>
                                        </div>
                                    </div>
                                </td>
                            </tr>

                            @if(!$isCollapsed1)
                                @foreach($genders as $gender => $categoryItems)
                                    @php
                                        $group2Key = md5($category . $productName . $gender);
                                        $isCollapsed2 = in_array($group2Key, $collapsedGroups);
                                        $genderLabel = $gender === 'P' ? 'PEREMPUAN' : 'LAKI-LAKI';
                                        $genderColorClass = $gender === 'P' ? '!bg-rose-50 !border-rose-100' : '!bg-blue-50 !border-blue-100';
                                        $genderTextClass = $gender === 'P' ? 'text-rose-600' : 'text-blue-600';
                                        $genderIconClass = $gender === 'P' ? 'bg-rose-500 text-white' : 'bg-blue-500 text-white';
                                    @endphp

                                    <!-- Tier 2: Gender Grouping -->
                                    <tr wire:click="toggleGroup('{{ $group2Key }}')" 
                                        class="border-y cursor-pointer hover:opacity-95 transition-all">
                                        <td colspan="{{ $useRecipientNames ? '10' : '9' }}" 
                                            style="background-color: {{ $gender === 'P' ? '#fff1f2' : '#eff6ff' }} !important; padding-left: 40px !important; padding-right: 16px !important; padding-top: 10px !important; padding-bottom: 10px !important;"
                                            class="border-none">
                                            <div class="flex items-center gap-4">
                                                <svg class="w-3 h-3 transition-transform {{ $genderTextClass }} {{ $isCollapsed2 ? '-rotate-90' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                                </svg>
                                                <div class="flex items-center gap-2">
                                                    <span class="text-[11px] font-black tracking-widest {{ $genderTextClass }}">
                                                        {{ $genderLabel }}
                                                    </span>
                                                    <span class="text-[10px] font-bold opacity-60 {{ $genderTextClass }}">
                                                        — {{ collect($categoryItems)->sum('quantity') }} PCS
                                                    </span>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>

                                    @if(!$isCollapsed2)
                                        @foreach($categoryItems as $itemIndex => $item)
                                        @php
                                            $actualIndex = collect($items)->search(fn($i) => $i['id'] === $item['id']);
                                        @endphp
                                        <tr class="hover:bg-gray-50/50 transition-colors group {{ in_array($item['id'], $selectedItems) ? 'bg-primary-50/30' : '' }}">
                                            <td class="px-3 py-4 text-center">
                                                <input type="checkbox" wire:model.live="selectedItems" value="{{ $item['id'] }}"
                                                    class="rounded border-gray-300 text-primary-600 focus:ring-primary-500 cursor-pointer">
                                            </td>
                                            
                                            @if($useRecipientNames)
                                            <td class="px-4 py-4">
                                                <input type="text" wire:model.blur="items.{{ $actualIndex }}.person_name"
                                                    placeholder="Nama Penerima..."
                                                    class="w-full text-xs font-bold text-gray-950 bg-white border border-gray-200 rounded-md px-2 py-1.5 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 transition-colors shadow-sm placeholder-gray-300">
                                            </td>
                                            @endif

                                            <td class="px-3 py-4">
                                                @if($item['production_category'] === 'jasa')
                                                    <div class="text-center text-gray-300 font-bold">-</div>
                                                @else
                                                    <div class="flex items-center gap-2">
                                                        <select wire:model.live="items.{{ $actualIndex }}.size"
                                                            class="w-full text-xs font-semibold text-gray-700 bg-white border border-gray-200 rounded-md pl-2 pr-8 py-1.5 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 transition-colors shadow-sm cursor-pointer">
                                                            @foreach($sizeOptions as $val => $label)
                                                                <option value="{{ $val }}">{{ $label === 'Custom' ? 'Custom' : $label }}</option>
                                                            @endforeach
                                                            @if(!array_key_exists('Custom', $sizeOptions))
                                                                <option value="Custom">Custom</option>
                                                            @endif
                                                        </select>
                                                        @if(($item['size'] ?? '') === 'Custom')
                                                            <button wire:click="openDetail({{ $actualIndex }})" @click="$dispatch('open-modal', { id: 'detail-modal' })" type="button" class="flex items-center justify-center p-1.5 bg-white border border-primary-200 shadow-sm rounded-md text-primary-600 hover:bg-primary-50 hover:border-primary-300 transition-all shrink-0" title="Set Ukuran Badan">
                                                                <x-heroicon-m-pencil-square class="w-4 h-4 shrink-0" />
                                                            </button>
                                                        @endif
                                                    </div>
                                                @endif
                                            </td>

                                            <td class="px-4 py-4">
                                                @if($item['production_category'] === 'produksi')
                                                    <div class="flex items-center gap-2">
                                                        @php
                                                            $selectedMatId = $items[$actualIndex]['bahan_baju'] ?? null;
                                                            $selectedMat = $materialOptions[$selectedMatId] ?? null;
                                                        @endphp
                                                        @if($selectedMat && !empty($selectedMat['color_code']))
                                                            <div class="w-2.5 h-2.5 rounded-full border border-gray-200 shrink-0 shadow-sm"
                                                                style="background-color: {{ $selectedMat['color_code'] }};"></div>
                                                        @endif
                                                        <select wire:model.live="items.{{ $actualIndex }}.bahan_baju"
                                                            class="w-full text-xs font-semibold text-gray-700 bg-white border border-gray-200 rounded-md pl-2 pr-8 py-1.5 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 transition-colors shadow-sm cursor-pointer">
                                                            <option value="">-- Pilih --</option>
                                                            @foreach($materialOptions as $id => $mat)
                                                                <option value="{{ $id }}">{{ $mat['name'] }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                @else
                                                    <div class="text-xs text-gray-300 italic text-center font-bold">N/A</div>
                                                @endif
                                            </td>

                                            <td class="px-3 py-4">
                                                @if($item['production_category'] === 'produksi')
                                                    <select wire:model.live="items.{{ $actualIndex }}.gender" class="w-full text-xs font-semibold text-gray-700 bg-white border border-gray-200 rounded-md pl-2 pr-8 py-1.5 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 transition-colors shadow-sm cursor-pointer">
                                                        <option value="L">L</option>
                                                        <option value="P">P</option>
                                                    </select>
                                                @else
                                                    <div class="text-center text-gray-300 font-bold">-</div>
                                                @endif
                                            </td>
                                            <td class="px-3 py-4">
                                                @if($item['production_category'] === 'produksi')
                                                    <select wire:model.live="items.{{ $actualIndex }}.sleeve_model" class="w-full text-xs font-semibold text-gray-700 bg-white border border-gray-200 rounded-md pl-2 pr-8 py-1.5 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 transition-colors shadow-sm cursor-pointer">
                                                        @foreach($sleeveOptions as $val => $label) <option value="{{ $val }}">{{ $label }}</option> @endforeach
                                                    </select>
                                                @else
                                                    <div class="text-center text-gray-300 font-bold">-</div>
                                                @endif
                                            </td>
                                            <td class="px-3 py-4">
                                                @if($item['production_category'] === 'produksi')
                                                    <select wire:model.live="items.{{ $actualIndex }}.pocket_model" class="w-full text-xs font-semibold text-gray-700 bg-white border border-gray-200 rounded-md pl-2 pr-8 py-1.5 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 transition-colors shadow-sm cursor-pointer">
                                                        @foreach($pocketOptions as $val => $label) <option value="{{ $val }}">{{ $label }}</option> @endforeach
                                                    </select>
                                                @else
                                                    <div class="text-center text-gray-300 font-bold">-</div>
                                                @endif
                                            </td>
                                            <td class="px-3 py-4">
                                                @if($item['production_category'] === 'produksi')
                                                    <select wire:model.live="items.{{ $actualIndex }}.button_model" class="w-full text-xs font-semibold text-gray-700 bg-white border border-gray-200 rounded-md pl-2 pr-8 py-1.5 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 transition-colors shadow-sm cursor-pointer">
                                                        @foreach($buttonOptions as $val => $label) <option value="{{ $val }}">{{ $label }}</option> @endforeach
                                                    </select>
                                                @else
                                                    <div class="text-center text-gray-300 font-bold">-</div>
                                                @endif
                                            </td>

                                            <td class="px-3 py-4 text-center">
                                                @if($item['production_category'] === 'produksi')
                                                    <button wire:click="openDetail({{ $actualIndex }})" @click="$dispatch('open-modal', { id: 'req-modal' })" type="button" class="inline-flex items-center justify-center gap-1 px-2.5 py-1.5 rounded-md text-[10px] font-bold border transition-colors shadow-sm {{ ($item['is_tunic'] ?? false) ? 'bg-indigo-50 text-indigo-700 border-indigo-200 hover:bg-indigo-100' : 'bg-white text-gray-500 border-gray-200 hover:border-indigo-400 hover:text-indigo-600' }}">
                                                        @if($item['is_tunic'] ?? false)
                                                            <span>✨ Tunik</span>
                                                        @else
                                                            <x-heroicon-m-plus class="w-3 h-3" />
                                                            <span>Req</span>
                                                        @endif
                                                    </button>
                                                @else
                                                    <div class="text-center text-gray-300 font-bold">-</div>
                                                @endif
                                            </td>

                                            <td class="px-4 py-4 text-right">
                                                <div class="flex items-center justify-end">
                                                    <input type="number" wire:model.blur="items.{{ $actualIndex }}.price"
                                                        class="w-24 text-sm font-bold text-gray-950 bg-white border border-gray-200 rounded-md px-2 py-1.5 text-right focus:border-primary-500 focus:ring-1 focus:ring-primary-500 transition-colors shadow-sm">
                                                </div>
                                            </td>

                                            <td class="px-3 py-4 text-center">
                                                <button wire:click="removeItem({{ $actualIndex }})" type="button"
                                                    class="text-gray-300 hover:text-danger-500 p-1 transition-colors opacity-0 group-hover:opacity-100">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                    </svg>
                                                </button>
                                            </td>
                                        </tr>
                                        @endforeach
                                    @endif
                                @endforeach
                            @endif
                        @endforeach
                    @empty
                        <tr>
                            <td colspan="{{ $useRecipientNames ? '10' : '9' }}" class="px-4 py-20 text-center">
                                <div class="flex flex-col items-center justify-center space-y-3">
                                    <div class="p-4 bg-gray-50 rounded-full">
                                        <svg class="w-8 h-8 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                                        </svg>
                                    </div>
                                    <p class="text-sm font-bold text-gray-400 uppercase tracking-widest">Tabel Kosong</p>
                                    <p class="text-xs text-gray-400">Silakan tambah item atau ubah filter pencarian Anda.</p>
                                </div>
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
                <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest block mb-1">Estimasi
                    Subtotal</span>
                <div class="text-2xl font-bold text-primary-600 tracking-tight">
                    Rp {{ number_format(collect($items)->sum(fn($i) => $i['price'] * $i['quantity']), 0, ',', '.') }}
                </div>
            </div>
        </div>
    @endif

    <!-- State Sync Label -->
    <div
        class="flex items-center justify-between text-[9px] font-bold uppercase tracking-widest text-gray-300 px-1 mt-6">
        <span>Stateless Spreadsheet Engine v2.5</span>
        <span class="flex items-center gap-1.5 font-bold"><span
                class="w-1.5 h-1.5 bg-green-500 rounded-full animate-pulse"></span> AUTO-SYNC ACTIVE</span>
    </div>

    <!-- Modals -->

    <!-- Bulk Generate Modal -->
    <x-filament::modal id="bulk-modal" width="2xl" alignment="center">
        <x-slot name="heading">
            Tambah Data Massal
        </x-slot>
        <x-slot name="description">
            Gunakan bagian ini untuk memasukkan banyak item sekaligus ke dalam tabel di bawah.
        </x-slot>

        <div class="space-y-6 py-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                <div class="space-y-2">
                    <label class="text-sm font-medium leading-6 text-gray-950">Kategori Pesanan</label>
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="bulkCategory">
                            @foreach($productionCategories as $val => $label)
                                <option value="{{ $val }}">{{ $label }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </div>

                @if($bulkCategory === 'produksi')
                    <div class="space-y-6">
                        <!-- Katalog Bahan (Native Filament Select) -->
                        <div class="space-y-2">
                            <label class="text-sm font-medium leading-6 text-gray-950">Katalog Bahan</label>
                            <x-filament::input.wrapper shadow>
                                <x-filament::input.select wire:model.live="bulkMaterial">
                                    <option value="">-- Pilih Material --</option>
                                    @foreach($baseMaterialOptions as $id => $name)
                                        <option value="{{ $id }}">{{ $name }}</option>
                                    @endforeach
                                </x-filament::input.select>
                            </x-filament::input.wrapper>
                        </div>

                        <!-- Pilihan Warna (High Performance Color Pills) -->
                        <div class="space-y-3">
                            <label class="text-sm font-medium leading-6 text-gray-950">
                                Pilihan Warna dari Katalog
                            </label>

                            @if(!$bulkMaterial)
                                <div
                                    class="text-[11px] text-gray-400 italic bg-gray-50/50 p-4 rounded-xl border border-dashed border-gray-200 text-center">
                                    Pilih bahan terlebih dahulu untuk memunculkan pil warna...
                                </div>
                            @elseif(count($bulkVariantOptions) === 0)
                                <div
                                    class="text-[11px] text-amber-500 italic bg-amber-50 p-4 rounded-xl border border-dashed border-amber-200 text-center">
                                    Data warna belum tersedia untuk bahan ini.
                                </div>
                            @else
                                <div class="flex flex-wrap gap-2 max-h-[180px] overflow-y-auto p-1.5 custom-scrollbar"
                                    x-data="{ activeId: @entangle('bulkVariant') }">
                                    @foreach($bulkVariantOptions as $v)
                                        <button type="button" @click="activeId = '{{ $v['id'] }}'"
                                            wire:click="$set('bulkVariant', '{{ $v['id'] }}')"
                                            class="inline-flex items-center gap-1 px-4 py-2 rounded-full border transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2"
                                            :class="String(activeId) === '{{ $v['id'] }}' ? 'bg-primary-600 border-primary-600 border-2 shadow-md shadow-primary-200' : 'bg-white border-gray-300 text-gray-700 shadow-sm hover:border-primary-400 hover:shadow-md hover:-translate-y-0.5'">

                                            <span class="h-3.5 w-3.5 rounded-full border border-black/10 shadow-inner"
                                                :class="String(activeId) === '{{ $v['id'] }}' ? 'ring-1 ring-white/60' : ''"
                                                style="background-color: {{ $v['color_code'] ?: '#f3f4f6' }}">
                                            </span>

                                            <span class="text-[12px] font-bold tracking-tight whitespace-nowrap">
                                                {{ !empty($v['color_name']) ? $v['color_name'] : 'Tanpa Nama' }}
                                            </span>
                                        </button>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                @elseif($bulkCategory === 'non_produksi')
                    <div class="space-y-2">
                        <label class="text-sm font-medium leading-6 text-gray-950">Katalog Baju Jadi</label>
                        <x-filament::input.wrapper>
                            <x-filament::input.select wire:model="bulkProduct">
                                <option value="">-- Pilih Produk --</option>
                                @foreach($productOptions as $id => $label)
                                    <option value="{{ $id }}">{!! $label !!}</option>
                                @endforeach
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
                    </div>
                @endif
            </div>

            @if($bulkCategory !== 'produksi' && $bulkCategory !== 'jasa')
                <div class="space-y-2">
                    <label class="text-sm font-medium leading-6 text-gray-950">Warna Pesanan</label>
                    <x-filament::input.wrapper>
                        <x-filament::input type="text" wire:model="bulkColor" placeholder="Ketik warna (misal: Hitam/Navy)"
                            list="color-suggestions" />
                    </x-filament::input.wrapper>
                </div>
            @endif

            @if($bulkCategory === 'produksi')
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    @foreach(['bulkGender' => 'Gender', 'bulkSleeve' => 'Lengan', 'bulkPocket' => 'Saku', 'bulkButtons' => 'Kancing'] as $model => $label)
                        <div class="space-y-2">
                            <label class="text-sm font-medium leading-6 text-gray-950">{{ $label }}</label>
                            <x-filament::input.wrapper>
                                <x-filament::input.select wire:model="{{ $model }}">
                                    @if($model === 'bulkGender')
                                        <option value="L">Laki-laki</option>
                                        <option value="P">Perempuan</option>
                                    @else
                                        @foreach(${$model === 'bulkSleeve' ? 'sleeveOptions' : ($model === 'bulkPocket' ? 'pocketOptions' : 'buttonOptions')} as $val => $optLabel)
                                            <option value="{{ $val }}">{{ $optLabel }}</option>
                                        @endforeach
                                    @endif
                                </x-filament::input.select>
                            </x-filament::input.wrapper>
                        </div>
                    @endforeach
                </div>

                <div class="p-4 rounded-xl border border-gray-200 bg-gray-50 flex items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <x-filament::input.checkbox wire:model.live="bulkIsTunic" id="bulkIsTunic" />
                        <label for="bulkIsTunic" class="text-sm font-medium text-gray-700">Model Tunik?</label>
                    </div>
                    @if($bulkIsTunic)
                        <div class="flex-1 max-w-[160px]">
                            <x-filament::input.wrapper prefix="Rp">
                                <x-filament::input type="number" wire:model="bulkTunicFee" placeholder="Fee" />
                            </x-filament::input.wrapper>
                        </div>
                    @endif
                </div>
            @endif

            <!-- SEKSI 1: UKURAN STANDAR -->
            @if($bulkCategory !== 'jasa')
                <div class="space-y-3 bg-gray-50/50 p-4 rounded-xl border border-gray-100">
                    <div class="flex items-center justify-between mb-2">
                        <label class="text-xs font-bold text-gray-400 uppercase tracking-widest">Bagian A: Stok Ukuran
                            Standar</label>
                        <span class="text-[10px] text-gray-400 italic">Isi qty untuk S, M, L...</span>
                    </div>
                    <div class="grid grid-cols-4 sm:grid-cols-6 md:grid-cols-8 gap-2">
                        @foreach($sizeOptions as $val => $label)
                            <div class="flex flex-col border border-gray-200 rounded-lg overflow-hidden bg-white shadow-sm">
                                <div class="bg-gray-100/50 py-1 text-center border-b border-gray-100">
                                    <span class="text-[10px] font-bold text-gray-500 uppercase">{{ $label }}</span>
                                </div>
                                <input type="number" wire:model="bulkSzQty.{{ $val }}"
                                    class="w-full text-center border-none focus:ring-1 focus:ring-primary-500 text-sm py-2 bg-transparent font-semibold"
                                    placeholder="0">
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- SEKSI 2: UKUR BADAN / CUSTOM -->
                <div class="space-y-3 p-4 rounded-xl border border-primary-100 bg-primary-50/10">
                    <div class="flex items-center justify-between">
                        <div>
                            <label class="text-xs font-bold text-primary-600 uppercase tracking-widest">Bagian B: Pesanan
                                Khusus (Ukur Badan)</label>
                            <p class="text-[10px] text-gray-400 mt-0.5 whitespace-nowrap">Input nama dan ukuran spesifik
                                orang di sini.</p>
                        </div>
                        <x-filament::button wire:click="addBulkPerson" color="primary" size="xs" icon="heroicon-m-plus"
                            class="rounded-full shadow-sm">
                            Tambah Orang
                        </x-filament::button>
                    </div>

                    @if(count($bulkCustomPeople) > 0)
                        <div class="border border-gray-200 rounded-lg bg-white overflow-hidden shadow-sm">
                            <div class="max-h-[220px] overflow-y-auto custom-scrollbar">
                                <table class="min-w-full divide-y divide-gray-100">
                                    <thead class="bg-gray-50 sticky top-0 z-10">
                                        <tr class="divide-x divide-gray-100">
                                            <th class="px-3 py-2 text-left text-[9px] font-black text-gray-400 uppercase">Nama /
                                                Penerima</th>
                                            <th
                                                class="px-2 py-2 text-center text-[9px] font-black text-gray-400 uppercase w-16">
                                                LD</th>
                                            <th
                                                class="px-2 py-2 text-center text-[9px] font-black text-gray-400 uppercase w-16">
                                                PB</th>
                                            <th
                                                class="px-2 py-2 text-center text-[9px] font-black text-gray-400 uppercase w-16">
                                                PL</th>
                                            <th class="px-3 py-2 text-right text-[9px] font-black text-gray-400 uppercase w-28">
                                                Harga</th>
                                            <th class="px-2 py-2 w-8"></th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-50">
                                        @foreach($bulkCustomPeople as $index => $person)
                                            <tr class="divide-x divide-gray-50 hover:bg-gray-50/50 transition-colors">
                                                <td class="px-2 py-1">
                                                    <input type="text" wire:model="bulkCustomPeople.{{ $index }}.name"
                                                        placeholder="Nama..."
                                                        class="w-full text-xs border-0 focus:ring-0 p-1 font-bold bg-transparent">
                                                </td>
                                                <td class="px-1 py-1">
                                                    <input type="number" wire:model="bulkCustomPeople.{{ $index }}.LD"
                                                        placeholder="0"
                                                        class="w-full text-xs text-center border-0 focus:ring-0 p-1 bg-transparent">
                                                </td>
                                                <td class="px-1 py-1">
                                                    <input type="number" wire:model="bulkCustomPeople.{{ $index }}.PB"
                                                        placeholder="0"
                                                        class="w-full text-xs text-center border-0 focus:ring-0 p-1 bg-transparent">
                                                </td>
                                                <td class="px-1 py-1">
                                                    <input type="number" wire:model="bulkCustomPeople.{{ $index }}.PL"
                                                        placeholder="0"
                                                        class="w-full text-xs text-center border-0 focus:ring-0 p-1 bg-transparent">
                                                </td>
                                                <td class="px-1 py-1 text-right">
                                                    <input type="number" wire:model="bulkCustomPeople.{{ $index }}.price"
                                                        placeholder="Default"
                                                        class="w-full text-xs text-right border-0 focus:ring-0 p-1 font-bold text-primary-600 bg-transparent">
                                                </td>
                                                <td class="px-1 py-1 text-center">
                                                    <button type="button" wire:click="removeBulkPerson({{ $index }})"
                                                        class="text-gray-300 hover:text-danger-500">
                                                        <x-heroicon-m-x-mark class="w-4 h-4" />
                                                    </button>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @else
                        <div
                            class="py-6 border-2 border-dashed border-gray-100 rounded-xl flex flex-col items-center justify-center bg-gray-50/30">
                            <x-heroicon-o-users class="w-8 h-8 text-gray-200 mb-2" />
                            <p class="text-[10px] font-bold text-gray-300 uppercase tracking-widest">Belum ada data ukur badan
                            </p>
                        </div>
                    @endif
                </div>
            @endif

            <!-- HARGA GLOBAL DAN SUMMARY -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-2">
                <div class="space-y-1">
                    <label class="text-[10px] font-bold text-gray-400 uppercase tracking-widest ml-1">Harga Satuan
                        (Toko)</label>
                    <x-filament::input.wrapper prefix="Rp" class="shadow-sm">
                        <x-filament::input type="number" wire:model="bulkPrice" class="font-bold" />
                    </x-filament::input.wrapper>
                    <p class="text-[9px] text-gray-400 mt-1 italic ml-1">*Digunakan jika harga di list orang dikosongkan
                    </p>
                </div>

                @if($bulkCategory === 'jasa')
                    <div class="space-y-1">
                        <label class="text-[10px] font-bold text-gray-400 uppercase tracking-widest ml-1">Total
                            Quantity</label>
                        <x-filament::input.wrapper suffix="PCS" class="shadow-sm">
                            <x-filament::input type="number" wire:model="bulkCustomQty" class="font-bold" />
                        </x-filament::input.wrapper>
                    </div>
                @endif
            </div>
        </div>

        <x-slot name="footer">
            <div class="flex justify-end gap-3">
                <x-filament::button color="gray" @click="$dispatch('close-modal', { id: 'bulk-modal' })">
                    Discard
                </x-filament::button>
                <x-filament::button wire:click="generateBulk" @click="$dispatch('close-modal', { id: 'bulk-modal' })">
                    Generate Items
                </x-filament::button>
            </div>
        </x-slot>
    </x-filament::modal>




    <!-- Update Price Modal -->
    <x-filament::modal id="price-modal" width="md" alignment="center">
        <x-slot name="heading">
            Bulk Pricing Update
        </x-slot>
        <x-slot name="description">
            Update global rates for all items
        </x-slot>

        <div class="space-y-4 py-4">
            <div class="space-y-2">
                <label class="text-sm font-medium leading-6 text-gray-950">Target Unit Price</label>
                <x-filament::input.wrapper prefix="Rp">
                    <x-filament::input type="number" wire:model="newBulkPrice" placeholder="0" />
                </x-filament::input.wrapper>
            </div>
        </div>

        <x-slot name="footer">
            <div class="flex flex-col gap-3">
                <x-filament::button wire:click="applyBulkPrice" @click="$dispatch('close-modal', { id: 'price-modal' })"
                    class="w-full">
                    Apply to All Items
                </x-filament::button>
                <x-filament::button color="gray" @click="$dispatch('close-modal', { id: 'price-modal' })"
                    class="w-full">
                    Maybe later
                </x-filament::button>
            </div>
        </x-slot>
    </x-filament::modal>





    <!-- Request Tambahan Modal -->
    <x-filament::modal id="req-modal" width="2xl" alignment="start">
        <x-slot name="heading">
            Request Tambahan
        </x-slot>
        <x-slot name="description">
            Permintaan ekstra pada pesanan
        </x-slot>

        @if($editingIndex !== null)
            <div class="space-y-6 py-4">
                <!-- Tunic Toggle -->
                <div class="p-4 rounded-xl border border-gray-200 bg-gray-50 flex items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <x-filament::input.checkbox wire:model.live="editingItem.is_tunic" id="editIsTunicReq" />
                        <label for="editIsTunicReq" class="text-sm font-medium text-gray-700">Pakai Tunik?</label>
                    </div>
                    @if($editingItem['is_tunic'] ?? false)
                        <div class="flex-1 max-w-[160px]">
                            <x-filament::input.wrapper prefix="Rp">
                                <x-filament::input type="number" wire:model="editingItem.tunic_fee" />
                            </x-filament::input.wrapper>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        <x-slot name="footer">
            <div class="flex justify-end gap-3">
                <x-filament::button color="gray" @click="$dispatch('close-modal', { id: 'req-modal' })">
                    Tutup
                </x-filament::button>
                <x-filament::button wire:click="saveDetail" @click="$dispatch('close-modal', { id: 'req-modal' })">
                    Simpan
                </x-filament::button>
            </div>
        </x-slot>
    </x-filament::modal>

    <!-- Specs Modal -->
    <x-filament::modal id="detail-modal" width="2xl" alignment="start">
        <x-slot name="heading">
            Detail Ukuran Badan
        </x-slot>
        <x-slot name="description">
            Sesuaikan ukuran custom individu
        </x-slot>

        @if($editingIndex !== null)
            <div class="space-y-6 py-4">
                <!-- Custom Measurements -->
                <div class="space-y-4">
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                        @foreach(['LD' => 'L. Dada', 'PB' => 'P. Baju', 'PL' => 'P. Lengan', 'LB' => 'L. Bahu', 'LP' => 'L. Perut', 'LPh' => 'L. Paha'] as $key => $label)
                            <div class="space-y-1">
                                <label class="text-[11px] font-medium text-gray-500">{{ $label }}</label>
                                <x-filament::input.wrapper suffix="CM">
                                    <x-filament::input type="number" wire:model="editingItem.measurements.{{ $key }}" step="0.5"
                                        class="text-center" />
                                </x-filament::input.wrapper>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        <x-slot name="footer">
            <div class="flex justify-end gap-3">
                <x-filament::button color="gray" @click="$dispatch('close-modal', { id: 'detail-modal' })">
                    Tutup
                </x-filament::button>
                <x-filament::button wire:click="saveDetail" @click="$dispatch('close-modal', { id: 'detail-modal' })">
                    Simpan
                </x-filament::button>
            </div>
        </x-slot>
    </x-filament::modal>

    {{-- Color Suggestions Datalist --}}
    <!-- Bulk Edit Modal -->
    <x-filament::modal id="bulk-edit-modal" width="2xl">
        <x-slot name="heading">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-primary-50 rounded-lg">
                    <svg class="w-5 h-5 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                    </svg>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-gray-900">Edit Massal ({{ count($selectedItems) }} Item)</h3>
                    <p class="text-xs text-gray-500 font-medium mt-0.5">Pilih kolom yang ingin diubah secara bersamaan.</p>
                </div>
            </div>
        </x-slot>

        <div class="grid grid-cols-1 gap-6 py-4">
            <!-- Product Name -->
            <div class="flex items-start gap-4 p-4 rounded-xl border border-gray-100 bg-gray-50/30">
                <div class="pt-1.5">
                    <x-filament::input.checkbox wire:model.live="bulkEditData.apply_product_name" />
                </div>
                <div class="flex-1 space-y-2">
                    <label class="text-[10px] font-bold uppercase tracking-widest text-gray-400">Nama Produk</label>
                    <x-filament::input.wrapper>
                        <x-filament::input type="text" wire:model="bulkEditData.product_name" 
                            :disabled="!$bulkEditData['apply_product_name']" />
                    </x-filament::input.wrapper>
                </div>
            </div>

            <!-- Category & Size Group -->
            <div class="grid grid-cols-2 gap-4">
                <div class="flex items-start gap-3 p-4 rounded-xl border border-gray-100 bg-gray-50/30">
                    <div class="pt-1.5">
                        <x-filament::input.checkbox wire:model.live="bulkEditData.apply_production_category" />
                    </div>
                    <div class="flex-1 space-y-2">
                        <label class="text-[10px] font-bold uppercase tracking-widest text-gray-400">Kategori</label>
                        <x-filament::input.wrapper>
                            <x-filament::input.select wire:model="bulkEditData.production_category" :disabled="!$bulkEditData['apply_production_category']">
                                @foreach($productionCategories as $val => $label)
                                    <option value="{{ $val }}">{{ $label }}</option>
                                @endforeach
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
                    </div>
                </div>

                <div class="flex items-start gap-3 p-4 rounded-xl border border-gray-100 bg-gray-50/30">
                    <div class="pt-1.5">
                        <x-filament::input.checkbox wire:model.live="bulkEditData.apply_size" />
                    </div>
                    <div class="flex-1 space-y-2">
                        <label class="text-[10px] font-bold uppercase tracking-widest text-gray-400">Ukuran</label>
                        <x-filament::input.wrapper>
                            <x-filament::input.select wire:model="bulkEditData.size" :disabled="!$bulkEditData['apply_size']">
                                @foreach($sizeOptions as $val => $label)
                                    <option value="{{ $val }}">{{ $label }}</option>
                                @endforeach
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
                    </div>
                </div>
            </div>

            <!-- Material -->
            <div class="grid grid-cols-1 gap-4">
                <div class="flex items-start gap-3 p-4 rounded-xl border border-gray-100 bg-gray-50/30">
                    <div class="pt-1.5">
                        <x-filament::input.checkbox wire:model.live="bulkEditData.apply_bahan_baju" />
                    </div>
                    <div class="flex-1 space-y-2">
                        <label class="text-[10px] font-bold uppercase tracking-widest text-gray-400">Bahan</label>
                        <x-filament::input.wrapper>
                            <x-filament::input.select wire:model="bulkEditData.bahan_baju" :disabled="!$bulkEditData['apply_bahan_baju']">
                                <option value="">-- Pilih --</option>
                                @foreach($materialOptions as $id => $mat)
                                    <option value="{{ $id }}">{{ $mat['name'] }}</option>
                                @endforeach
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
                    </div>
                </div>
            </div>

            <!-- Price & Quantity -->
            <div class="grid grid-cols-2 gap-4">
                <div class="flex items-start gap-3 p-4 rounded-xl border border-gray-100 bg-gray-50/30">
                    <div class="pt-1.5">
                        <x-filament::input.checkbox wire:model.live="bulkEditData.apply_price" />
                    </div>
                    <div class="flex-1 space-y-2">
                        <label class="text-[10px] font-bold uppercase tracking-widest text-gray-400">Harga Satuan</label>
                        <x-filament::input.wrapper>
                            <x-filament::input type="number" wire:model="bulkEditData.price" :disabled="!$bulkEditData['apply_price']" />
                        </x-filament::input.wrapper>
                    </div>
                </div>

                <div class="flex items-start gap-3 p-4 rounded-xl border border-gray-100 bg-gray-50/30">
                    <div class="pt-1.5">
                        <x-filament::input.checkbox wire:model.live="bulkEditData.apply_quantity" />
                    </div>
                    <div class="flex-1 space-y-2">
                        <label class="text-[10px] font-bold uppercase tracking-widest text-gray-400">Jumlah (Qty)</label>
                        <x-filament::input.wrapper>
                            <x-filament::input type="number" wire:model="bulkEditData.quantity" :disabled="!$bulkEditData['apply_quantity']" />
                        </x-filament::input.wrapper>
                    </div>
                </div>
            </div>
        </div>

        <x-slot name="footer">
            <div class="flex justify-end gap-3">
                <x-filament::button color="gray" @click="isOpen = false">
                    Batal
                </x-filament::button>
                <x-filament::button wire:click="applyBulkEdit" color="primary">
                    Simpan Perubahan
                </x-filament::button>
            </div>
        </x-slot>
    </x-filament::modal>
</div>