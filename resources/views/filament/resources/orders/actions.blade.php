@php
    $editUrl = \App\Filament\Resources\Orders\OrderResource::getUrl('edit', ['record' => $record]);
    $receiptUrl = route('orders.receipt', ['order' => $record]);
@endphp

<div style="display:flex; justify-content:flex-end; width:100%;">
    <div x-data="{ open: false }" style="position:relative; display:inline-block; text-align:left;">
        <button @click.stop="open = !open"
            style="padding:6px; background:#f3f4f6; color:#4b5563; border-radius:10px; border:1px solid #e5e7eb; cursor:pointer; transition:all 0.2s; display:flex; align-items:center; justify-content:center;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="1" />
                <circle cx="12" cy="5" r="1" />
                <circle cx="12" cy="19" r="1" />
            </svg>
        </button>

        <div x-show="open" x-cloak @click.away="open = false" x-transition:enter="transition ease-out duration-100"
            x-transition:enter-start="transform opacity-0 scale-95"
            x-transition:enter-end="transform opacity-100 scale-100" x-transition:leave="transition ease-in duration-75"
            x-transition:leave-start="transform opacity-100 scale-100"
            x-transition:leave-end="transform opacity-0 scale-95"
            style="position:absolute; right:0; top:calc(100% + 5px); z-index:1000; width:165px; background:white; border-radius:12px; border:1px solid #e5e7eb; box-shadow:0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05); padding:6px;">

            {{-- Detail --}}
            <a href="{{ $editUrl }}"
                style="display:flex; align-items:center; gap:8px; padding:6px 8px; color:#9333ea; font-size:12px; font-weight:600; text-decoration:none; border-radius:8px; transition:all 0.2s;"
                onmouseover="this.style.background='#f5f3ff'" onmouseout="this.style.background='transparent'">
                <div
                    style="width:22px; height:22px; background:#f5f3ff; border-radius:6px; display:flex; align-items:center; justify-content:center;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                        stroke-linecap="round" stroke-linejoin="round">
                        <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z" />
                        <circle cx="12" cy="12" r="3" />
                    </svg>
                </div>
                Lihat Detail
            </a>

            {{-- Kuitansi --}}
            <a href="{{ $receiptUrl }}" target="_blank"
                style="display:flex; align-items:center; gap:8px; padding:6px 8px; color:#16a34a; font-size:12px; font-weight:600; text-decoration:none; border-radius:8px; transition:all 0.2s;"
                onmouseover="this.style.background='#f0fdf4'" onmouseout="this.style.background='transparent'">
                <div
                    style="width:22px; height:22px; background:#f0fdf4; border-radius:6px; display:flex; align-items:center; justify-content:center;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                        stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                        <polyline points="14 2 14 8 20 8" />
                        <line x1="12" y1="18" x2="12" y2="12" />
                        <polyline points="9 15 12 18 15 15" />
                    </svg>
                </div>
                Kuitansi
            </a>

            {{-- Edit --}}
            <a href="{{ $editUrl }}"
                style="display:flex; align-items:center; gap:8px; padding:6px 8px; color:#7c3aed; font-size:12px; font-weight:600; text-decoration:none; border-radius:8px; transition:all 0.2s;"
                onmouseover="this.style.background='#f5f3ff'" onmouseout="this.style.background='transparent'">
                <div
                    style="width:22px; height:22px; background:#f5f3ff; border-radius:6px; display:flex; align-items:center; justify-content:center;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                        stroke-linecap="round" stroke-linejoin="round">
                        <path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z" />
                    </svg>
                </div>
                Edit
            </a>

            {{-- Retur Barang --}}
            <button x-on:click.stop="open = false; $wire.mountTableAction('create_return', '{{ $record->id }}')"
                style="width:100%; display:flex; align-items:center; gap:8px; padding:6px 8px; color:#ea580c; font-size:12px; font-weight:600; background:none; border:none; cursor:pointer; border-radius:8px; transition:all 0.2s; text-align:left;"
                onmouseover="this.style.background='#fff7ed'" onmouseout="this.style.background='transparent'">
                <div
                    style="width:22px; height:22px; background:#fff7ed; border-radius:6px; display:flex; align-items:center; justify-content:center;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                        stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 14 4 9l5-5" />
                        <path d="M4 9h12a5 5 0 0 1 0 10H7" />
                    </svg>
                </div>
                Retur Barang
            </button>

            {{-- Delete --}}
            @if(auth()->user()->role === 'owner')
                <button
                    x-on:click.stop="if(confirm('Apakah Anda yakin ingin menghapus pesanan ini?')) { $wire.mountTableAction('delete', '{{ $record->id }}') }"
                    style="width:100%; display:flex; align-items:center; gap:8px; padding:6px 8px; color:#ef4444; font-size:12px; font-weight:600; background:none; border:none; cursor:pointer; border-radius:8px; transition:all 0.2s; text-align:left;"
                    onmouseover="this.style.background='#fef2f2'" onmouseout="this.style.background='transparent'">
                    <div
                        style="width:22px; height:22px; background:#fef2f2; border-radius:6px; display:flex; align-items:center; justify-content:center;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                            stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="3 6 5 6 21 6" />
                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
                            <line x1="10" y1="11" x2="10" y2="17" />
                            <line x1="14" y1="11" x2="14" y2="17" />
                        </svg>
                    </div>
                    Delete
                </button>
            @endif
        </div>
    </div>
</div>