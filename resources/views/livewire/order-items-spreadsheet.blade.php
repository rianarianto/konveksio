<div 
    class="space-y-6"
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
    <div class="flex flex-wrap items-center justify-between gap-4 p-5 bg-white border border-gray-200 rounded-2xl shadow-[0_8px_30px_rgb(0,0,0,0.04)] ring-1 ring-gray-900/5">
        <div class="flex flex-wrap items-center gap-3">
            <button wire:click="addItem" type="button" class="group relative inline-flex items-center px-5 py-2.5 text-sm font-bold text-white bg-gradient-to-br from-indigo-600 to-indigo-700 rounded-xl hover:from-indigo-500 hover:to-indigo-600 transition-all shadow-[0_4px_14px_0_rgba(79,70,229,0.39)] hover:shadow-indigo-500/50 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                <svg class="w-5 h-5 mr-2 -ml-1 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                Tambah Baris
            </button>
            
            <button @click="$wire.set('showBulkModal', true)" type="button" class="group relative inline-flex items-center px-5 py-2.5 text-sm font-bold text-white bg-gradient-to-br from-purple-600 to-purple-700 rounded-xl hover:from-purple-500 hover:to-purple-600 transition-all shadow-[0_4px_14px_0_rgba(147,51,234,0.39)] hover:shadow-purple-500/50 focus:ring-2 focus:ring-purple-500 focus:ring-offset-2">
                <svg class="w-5 h-5 mr-2 -ml-1 group-hover:rotate-12 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                Bulk Generate
            </button>
            
            <button @click="$wire.set('showPriceModal', true)" type="button" class="inline-flex items-center px-5 py-2.5 text-sm font-bold text-orange-700 bg-orange-50 border border-orange-200 rounded-xl hover:bg-orange-100 hover:border-orange-300 transition-all shadow-sm">
                <svg class="w-5 h-5 mr-2 -ml-1 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                Ganti Semua Harga
            </button>
        </div>

        <div class="flex items-center gap-4">
             <div class="text-right">
                <div class="text-[10px] font-bold text-gray-400 uppercase tracking-widest leading-none mb-1">Total Item</div>
                <div class="text-lg font-black text-gray-900 leading-none">{{ count($items) }}</div>
             </div>
             <button wire:click="clearAll" onclick="return confirm('Apakah Anda yakin ingin menghapus semua item?')" type="button" class="p-2.5 text-red-500 hover:bg-red-50 hover:text-red-600 rounded-xl transition-all border border-transparent hover:border-red-100" title="Kosongkan Tabel">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
            </button>
        </div>
    </div>

    <!-- Table Container -->
    <div class="border border-gray-200 rounded-2xl shadow-[0_8px_30px_rgb(0,0,0,0.02)] bg-white overflow-hidden ring-1 ring-gray-900/5">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200/60 table-fixed">
                <thead class="bg-[#fcfdfd] border-b border-gray-200">
                    <tr>
                        <th class="w-14 px-4 py-4 text-center text-[10px] font-black text-gray-400 uppercase tracking-[0.1em]">#</th>
                        <th class="w-1/4 px-4 py-4 text-left text-[11px] font-black text-gray-500 uppercase tracking-[0.1em]">Produk / Pemesan</th>
                        <th class="w-24 px-4 py-4 text-left text-[11px] font-black text-gray-500 uppercase tracking-[0.1em]">Size</th>
                        <th class="w-32 px-4 py-4 text-left text-[11px] font-black text-gray-500 uppercase tracking-[0.1em]">Kategori</th>
                        <th class="w-1/4 px-4 py-4 text-left text-[11px] font-black text-gray-500 uppercase tracking-[0.1em]">Bahan</th>
                        <th class="w-40 px-4 py-4 text-right text-[11px] font-black text-gray-500 uppercase tracking-[0.1em]">Harga Unit</th>
                        <th class="w-24 px-4 py-4 text-center text-[11px] font-black text-gray-500 uppercase tracking-[0.1em]">Qty</th>
                        <th class="w-44 px-4 py-4 text-right text-[11px] font-black text-indigo-600 uppercase tracking-[0.1em] opacity-80">Subtotal</th>
                        <th class="w-14 px-4 py-4"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100/80">
                    @forelse($items as $index => $item)
                        <tr class="hover:bg-indigo-50/20 transition-all duration-150 ease-in-out group">
                            <td class="px-4 py-4 text-center text-xs font-bold text-gray-300 group-hover:text-indigo-300">
                                {{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }}
                            </td>
                            <td class="px-4 py-4">
                                <input 
                                    type="text" 
                                    wire:model.blur="items.{{ $index }}.product_name"
                                    placeholder="Input nama produk..."
                                    class="w-full text-sm border-0 focus:ring-0 focus:bg-white bg-transparent p-0 font-bold text-gray-800 placeholder-gray-300 transition-all"
                                >
                            </td>
                            <td class="px-4 py-4">
                                <select 
                                    wire:model.change="items.{{ $index }}.size"
                                    class="w-full text-sm border-0 focus:ring-0 bg-transparent p-0 text-gray-600 font-medium cursor-pointer hover:text-indigo-600"
                                >
                                    @foreach($sizeOptions as $val => $label)
                                        <option value="{{ $val }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="px-4 py-4">
                                <select 
                                    wire:model.change="items.{{ $index }}.production_category"
                                    class="w-full text-[11px] font-black uppercase tracking-wider border border-transparent hover:border-indigo-100 rounded-lg bg-indigo-50/50 p-1.5 px-3 text-indigo-600 focus:ring-2 focus:ring-indigo-500/20 focus:bg-white transition-all cursor-pointer ring-inset"
                                >
                                    @foreach($productionCategories as $val => $label)
                                        <option value="{{ $val }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="px-4 py-4">
                                <select 
                                    wire:model.change="items.{{ $index }}.bahan_baju"
                                    class="w-full text-sm border-0 focus:ring-0 bg-transparent p-0 text-gray-600 font-medium cursor-pointer hover:text-indigo-600"
                                >
                                    @foreach($materialOptions as $val => $label)
                                        <option value="{{ $val }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="px-4 py-4 text-right">
                                <div class="flex items-center justify-end relative">
                                    <span class="text-[10px] font-black text-gray-300 mr-1.5 align-middle">RP</span>
                                    <input 
                                        type="number" 
                                        wire:model.blur="items.{{ $index }}.price"
                                        class="w-32 text-sm text-right border-0 focus:ring-2 focus:ring-indigo-100 rounded-lg bg-transparent hover:bg-gray-50 p-1.5 font-black text-gray-900 transition-all"
                                    >
                                </div>
                            </td>
                            <td class="px-4 py-4 text-center">
                                <input 
                                    type="number" 
                                    wire:model.blur="items.{{ $index }}.quantity"
                                    class="w-16 text-sm text-center border-0 focus:ring-2 focus:ring-indigo-100 rounded-lg bg-transparent hover:bg-gray-50 p-1.5 font-black text-gray-900 transition-all"
                                >
                            </td>
                            <td class="px-4 py-4 text-right">
                                 <span class="text-sm font-black text-indigo-600 tracking-tight">
                                    {{ number_format($item['price'] * $item['quantity'], 0, ',', '.') }}
                                 </span>
                            </td>
                            <td class="px-4 py-4 text-center">
                                <button 
                                    wire:click="removeItem({{ $index }})"
                                    type="button"
                                    class="text-gray-200 hover:text-red-500 bg-transparent hover:bg-red-50 p-2 rounded-xl transition-all opacity-0 group-hover:opacity-100"
                                    title="Hapus Baris"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-20 text-center bg-gray-50/30">
                                <div class="flex flex-col items-center justify-center space-y-3">
                                    <div class="p-4 bg-white rounded-full shadow-sm ring-1 ring-gray-100">
                                        <svg class="w-10 h-10 text-gray-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                                    </div>
                                    <div class="text-sm font-bold text-gray-400">Belum ada item pesanan</div>
                                    <p class="text-[11px] text-gray-300 uppercase tracking-widest font-black">Gunakan tombol di atas untuk memulai</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($items)
    <div class="flex justify-end pr-8">
        <div class="text-right">
            <span class="text-[10px] font-black text-gray-400 uppercase tracking-[0.2em] mb-1 block">Estimasi Subtotal</span>
            <div class="text-3xl font-black text-transparent bg-clip-text bg-gradient-to-r from-indigo-600 to-indigo-800 tracking-tighter">
                Rp {{ number_format(collect($items)->sum(fn($i) => $i['price'] * $i['quantity']), 0, ',', '.') }}
            </div>
        </div>
    </div>
    @endif

    <!-- State Sync Label -->
    <div class="mt-4 flex items-center justify-between text-[10px] font-black uppercase tracking-[0.2em] text-gray-300">
        <span>Stateless Spreadsheet v2.0</span>
        <span class="flex items-center gap-1.5"><span class="w-1.5 h-1.5 bg-green-500 rounded-full animate-pulse"></span> Auto-Sync Active</span>
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
        class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-md"
        style="display: none;"
    >
        <div 
            @click.away="$wire.set('showBulkModal', false)"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 scale-95 translate-y-4"
            x-transition:enter-end="opacity-100 scale-100 translate-y-0"
            class="w-full max-w-xl bg-white rounded-[2.5rem] shadow-[0_25px_50px_-12px_rgba(0,0,0,0.25)] overflow-hidden border border-gray-100"
        >
            <div class="px-10 py-8 bg-gradient-to-br from-purple-600 to-indigo-700 flex justify-between items-center relative overflow-hidden">
                <div class="absolute top-0 right-0 -mt-4 -mr-4 w-32 h-32 bg-white/10 rounded-full blur-3xl"></div>
                <div class="relative">
                    <h3 class="text-xl font-black text-white uppercase tracking-wider mb-1">Bulk Generate</h3>
                    <p class="text-indigo-100 text-xs font-bold opacity-80 uppercase tracking-widest">Tambah banyak item sekaligus</p>
                </div>
                <button @click="$wire.set('showBulkModal', false)" class="relative p-2 text-white/70 hover:text-white hover:bg-white/10 rounded-full transition-all">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <div class="p-10 space-y-8">
                <div class="grid grid-cols-2 gap-8 text-left">
                    <div class="space-y-2">
                        <label class="block text-[11px] font-black text-gray-400 uppercase tracking-widest ml-1">Katalog Bahan / Produk</label>
                        <select wire:model="bulkMaterial" class="w-full text-sm font-bold border-gray-100 bg-gray-50/50 rounded-2xl p-4 focus:ring-purple-500 focus:border-purple-500 transition-all">
                            <option value="">-- Pilih Katalog --</option>
                            @foreach($materialOptions as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="space-y-2">
                        <label class="block text-[11px] font-black text-gray-400 uppercase tracking-widest ml-1">Kategori Produksi</label>
                        <select wire:model="bulkCategory" class="w-full text-sm font-bold border-gray-100 bg-gray-50/50 rounded-2xl p-4 focus:ring-purple-500 focus:border-purple-500 transition-all">
                            @foreach($productionCategories as $val => $label)
                                <option value="{{ $val }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="space-y-4">
                    <label class="block text-[11px] font-black text-gray-400 uppercase tracking-widest ml-1">Pilih Ukuran & Variasi</label>
                    <div class="grid grid-cols-5 gap-3">
                        @foreach($sizeOptions as $val => $label)
                            <label class="group relative flex items-center justify-center p-4 border-2 rounded-2xl cursor-pointer transition-all duration-200 {{ in_array($val, $bulkSizes) ? 'bg-purple-50 border-purple-500 shadow-sm' : 'bg-white border-gray-100 hover:border-purple-200' }}">
                                <input type="checkbox" wire:model="bulkSizes" value="{{ $val }}" class="hidden">
                                <span class="text-sm font-black {{ in_array($val, $bulkSizes) ? 'text-purple-600' : 'text-gray-400 group-hover:text-gray-600' }}">{{ $label }}</span>
                                @if(in_array($val, $bulkSizes))
                                    <div class="absolute -top-1.5 -right-1.5 w-4 h-4 bg-purple-500 rounded-full flex items-center justify-center ring-2 ring-white">
                                        <svg class="w-2.5 h-2.5 text-white" fill="currentColor" viewBox="0 0 20 20"><path d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"></path></svg>
                                    </div>
                                @endif
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="flex items-center gap-8 pt-4 border-t border-gray-50">
                    <div class="flex-1 space-y-2">
                        <label class="block text-[11px] font-black text-gray-400 uppercase tracking-widest ml-1">Qty per Ukuran</label>
                        <div class="flex items-center">
                            <input type="number" wire:model="bulkQty" class="w-full text-center text-xl font-black border-gray-100 bg-gray-50/50 rounded-2xl p-4 focus:ring-purple-500 focus:border-purple-500">
                        </div>
                    </div>
                    <div class="flex-[2] flex items-end">
                        <button wire:click="generateBulk" type="button" class="w-full group relative inline-flex items-center justify-center px-8 py-5 text-base font-black text-white bg-gradient-to-r from-purple-600 to-indigo-600 rounded-2xl hover:from-purple-500 hover:to-indigo-500 transition-all shadow-[0_10px_20px_-5px_rgba(124,58,237,0.4)] hover:shadow-purple-500/50">
                            GENERATE SEKARANG
                            <svg class="w-5 h-5 ml-2 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M13 7l5 5m0 0l-5 5m5-5H6"></path></svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bulk Price Modal -->
    <div 
        x-show="$wire.showPriceModal" 
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-md"
        style="display: none;"
    >
        <div 
            @click.away="$wire.set('showPriceModal', false)"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            class="w-full max-w-md bg-white rounded-[2.5rem] shadow-2xl overflow-hidden border border-gray-100"
        >
            <div class="px-8 py-6 bg-gradient-to-br from-orange-500 to-red-600 flex justify-between items-center">
                <h3 class="text-lg font-black text-white uppercase tracking-widest">Update Harga</h3>
                <button @click="$wire.set('showPriceModal', false)" class="text-white/70 hover:text-white p-2 hover:bg-white/10 rounded-full transition-all">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            <div class="p-10 text-center">
                <div class="w-16 h-16 bg-orange-50 rounded-2xl flex items-center justify-center mx-auto mb-6 ring-1 ring-orange-100">
                    <svg class="w-8 h-8 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
                <p class="text-sm text-gray-500 mb-6 font-bold leading-relaxed px-4 text-center">Ini akan mengubah harga <span class="text-orange-600 font-extrabold underline decoration-2 underline-offset-4">{{ count($items) }} item</span> dalam tabel menjadi:</p>
                <div class="relative mb-8">
                    <span class="absolute left-6 top-1/2 -translate-y-1/2 text-gray-300 font-black text-lg">RP</span>
                    <input type="number" wire:model="newBulkPrice" class="w-full pl-16 pr-8 py-5 text-3xl font-black border-gray-100 bg-gray-50/50 rounded-3xl focus:ring-orange-500 focus:border-orange-500 transition-all text-center tracking-tighter shadow-inner">
                </div>
                <button wire:click="applyBulkPrice" type="button" class="w-full py-5 text-base font-black text-white bg-gradient-to-r from-orange-500 to-red-600 rounded-2xl hover:from-orange-400 hover:to-red-500 transition-all shadow-lg hover:shadow-orange-500/50">
                    TERAPKAN HARGA BARU
                </button>
            </div>
        </div>
    </div>
</div>
