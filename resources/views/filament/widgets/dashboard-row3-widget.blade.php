<x-filament-widgets::widget>
    <div wire:poll.10s
        style="background:white; border-radius:20px; box-shadow:0 1px 3px rgba(0,0,0,0.07); border:1px solid #e5e7eb; overflow:hidden; font-family:inherit;">
        {{-- HEADER SECTION --}}
        <div style="padding:24px 24px 16px;">
            <h2 style="font-size:20px; font-weight:600; color:#1f2937; margin-bottom:20px;">
                {{ auth()->user()->role === 'owner' ? 'PANTAUAN PRIORITAS OWNER' : 'Daftar Pesanan Cepat' }}
            </h2>

            <div style="display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:16px;">
                {{-- Add Button --}}
                <a href="{{ \App\Filament\Resources\Orders\OrderResource::getUrl('create') }}"
                    style="display:inline-flex; align-items:center; gap:8px; background:#7c3aed; color:white; padding:10px 20px; border-radius:12px; font-weight:500; font-size:14px; text-decoration:none; transition:background 0.2s;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M5 12h14" />
                        <path d="M12 5v14" />
                    </svg>
                    Tambah Pesanan
                </a>

                {{-- Filters --}}
                <div style="display:flex; flex-wrap:wrap; align-items:center; gap:12px;">
                    {{-- Search --}}
                    <div style="position:relative;">
                        <div style="position:absolute; top:50%; left:12px; transform:translateY(-50%); color:#666666;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <circle cx="11" cy="11" r="8" />
                                <path d="m21 21-4.3-4.3" />
                            </svg>
                        </div>
                        <input wire:model.live.debounce.500ms="search" type="text" placeholder="Cari"
                            style="background:#f9fafb; border:none; border-radius:12px; padding:10px 16px 10px 36px; font-size:13px; width:180px; color:#374151; outline:none;">
                    </div>

                    {{-- Status Filter --}}
                    <div style="position:relative;">
                        <select wire:model.live="statusFilter"
                            style="appearance:none; background:#f9fafb; border:none; border-radius:12px; padding:10px 36px 10px 16px; font-size:13px; color:#6b7280; cursor:pointer; min-width:155px; outline:none;">
                            <option value="">Status Pesanan</option>
                            <option value="pending">Pending</option>
                            <option value="diterima">Antrian</option>
                            <option value="dikerjakan">Dikerjakan</option>
                            <option value="selesai">Selesai</option>
                        </select>
                        <div
                            style="position:absolute; right:12px; top:50%; transform:translateY(-50%); color:#666666; pointer-events:none;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <path d="m6 9 6 6 6-6" />
                            </svg>
                        </div>
                    </div>

                    {{-- Date Filter --}}
                    <div
                        style="display:flex; align-items:center; background:#f9fafb; border-radius:12px; overflow:hidden;">
                        <div style="padding:10px 10px 10px 14px; color:#666666;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <rect width="18" height="18" x="3" y="4" rx="2" ry="2" />
                                <line x1="16" x2="16" y1="2" y2="6" />
                                <line x1="8" x2="8" y1="2" y2="6" />
                                <line x1="3" x2="21" y1="10" y2="10" />
                            </svg>
                        </div>
                        <input wire:model.live="deadlineFilter" type="date"
                            style="background:transparent; border:none; padding:10px 16px 10px 0; font-size:13px; color:#6b7280; outline:none; cursor:text;">
                    </div>

                    {{-- Per Page --}}
                    <div style="display:flex; align-items:center; gap:8px;">
                        <div style="position:relative;">
                            <select wire:model.live="perPage"
                                style="appearance:none; background:#f9fafb; border:none; border-radius:12px; padding:10px 28px 10px 12px; font-size:13px; color:#6b7280; cursor:pointer; outline:none;">
                                <option value="5">5</option>
                                <option value="10">10</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                            </select>
                            <div
                                style="position:absolute; right:8px; top:50%; transform:translateY(-50%); color:#666666; pointer-events:none;">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2">
                                    <path d="m6 9 6 6 6-6" />
                                </svg>
                            </div>
                        </div>
                        <span style="font-size:13px; color:#666666;">Total Baris</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- TABLE SECTION --}}
        <div style="overflow-x:auto; padding:0 24px 12px;">
            <div style="min-width:1000px; border:1px solid #f3f4f6; border-radius:16px; overflow:hidden;">
                <table style="width:100%; border-collapse:collapse; text-align:left;">
                    <thead>
                        <tr style="background:#fafafa; border-bottom:1px solid #f3f4f6;">
                            <th style="padding:12px 16px; width:40px;"><input type="checkbox" disabled
                                    style="accent-color:#7c3aed;"></th>
                            <th style="padding:12px 8px; font-size:13px; font-weight:600; color:#666666; width:32px;">#
                            </th>
                            <th style="padding:12px 8px; font-size:13px; font-weight:600; color:#666666; width:22%;">
                                Pesanan & Pelanggan</th>
                            <th style="padding:12px 8px; font-size:13px; font-weight:600; color:#666666; width:15%;">
                                Deadline</th>
                            <th style="padding:12px 8px; font-size:13px; font-weight:600; color:#666666; width:18%;">
                                Sisa Tagihan (Sisa/Total)</th>
                            <th
                                style="padding:12px 8px; font-size:13px; font-weight:600; color:#666666; min-width:280px;">
                                Tipe Produk & Status Pesanan</th>
                            <th
                                style="padding:12px 16px; font-size:13px; font-weight:600; color:#666666; width:120px; text-align:right;">
                                Aksi (Action)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($orders as $index => $order)
                            <tr style="border-bottom:1px solid #f9fafb;">
                                {{-- Checkbox --}}
                                <td style="padding:16px; vertical-align:top;">
                                    <input type="checkbox" disabled style="accent-color:#7c3aed;">
                                </td>

                                {{-- Index --}}
                                <td
                                    style="padding:16px 8px; vertical-align:top; font-size:15px; font-weight:500; color:#666666;">
                                    {{ $orders->firstItem() + $index }}
                                </td>

                                <td style="padding:16px 8px; vertical-align:top;">
                                    <div style="display:flex; align-items:center; gap:6px; margin-bottom:8px;">
                                        @if($order->is_express)
                                            <span
                                                style="background:#dc2626; color:#fff; font-size:10px; font-weight:800; padding:2px 8px; border-radius:999px; letter-spacing:0.02em; display:inline-flex; align-items:center; gap:3px;">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"
                                                    viewBox="0 0 24 24" fill="currentColor" stroke="none">
                                                    <path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z" />
                                                </svg>
                                                EXPRESS
                                            </span>
                                        @endif
                                        <div
                                            style="font-weight:500; color:#666666; font-size:13px; letter-spacing:0.04em; text-transform:uppercase;">
                                            {{ $order->order_number }}
                                        </div>
                                    </div>
                                    <div style="display:flex; flex-direction:column; gap:6px; align-items:flex-start;">
                                        <div
                                            style="display:inline-flex; align-items:center; gap:6px; padding:4px 12px; border:1px solid #f3e8ff; border-radius:9999px; background:white; color:#a855f7; font-size:12px; font-weight:500;">
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none"
                                                stroke="currentColor" stroke-width="2">
                                                <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2" />
                                                <circle cx="12" cy="7" r="4" />
                                            </svg>
                                            {{ $order->customer->name ?? '-' }}
                                        </div>
                                        <div
                                            style="display:inline-flex; align-items:center; gap:6px; padding:4px 12px; border:1px solid #f3e8ff; border-radius:9999px; background:white; color:#a855f7; font-size:12px; font-weight:500;">
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none"
                                                stroke="currentColor" stroke-width="2">
                                                <path
                                                    d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z" />
                                            </svg>
                                            {{ $order->customer->phone ?? '-' }}
                                        </div>
                                    </div>
                                </td>

                                {{-- Deadline --}}
                                <td style="padding:16px 8px; vertical-align:top;">
                                    @php
                                        $deadline = \Carbon\Carbon::parse($order->deadline);
                                        $today = \Carbon\Carbon::today();
                                        $diff = $today->diffInDays($deadline, false);

                                        if ($diff < 0) {
                                            $sisaText = 'Terlambat ' . abs($diff) . ' hari';
                                            $sisaBadgeStyle = 'color:#e11d48; border:1px solid #fda4af; background:white;';
                                            $sisaIconColor = '#e11d48';
                                        } elseif ($diff == 0) {
                                            $sisaText = 'Hari ini';
                                            $sisaBadgeStyle = 'color:#e11d48; border:1px solid #fda4af; background:white;';
                                            $sisaIconColor = '#e11d48';
                                        } elseif ($diff <= 3) {
                                            $sisaText = 'Sisa ' . $diff . ' hari';
                                            $sisaBadgeStyle = 'color:#e11d48; border:1px solid #fda4af; background:white;';
                                            $sisaIconColor = '#e11d48';
                                        } elseif ($diff <= 7) {
                                            $sisaText = 'Sisa ' . $diff . ' hari';
                                            $sisaBadgeStyle = 'color:#ca8a04; border:1px solid #fbbf24; background:white;';
                                            $sisaIconColor = '#ca8a04';
                                        } else {
                                            $sisaText = 'Sisa ' . $diff . ' hari';
                                            $sisaBadgeStyle = 'color:#16a34a; border:1px solid #86efac; background:white;';
                                            $sisaIconColor = '#16a34a';
                                        }
                                    @endphp
                                    <div style="display:flex; align-items:center; gap:8px; margin-bottom:8px;">
                                        <span
                                            style="font-size:14px; font-weight:500; color:#6b7280;">{{ $deadline->format('d M Y') }}</span>
                                    </div>
                                    <div
                                        style="display:inline-flex; align-items:center; gap:6px; padding:4px 10px; border-radius:9999px; font-size:11px; font-weight:500; {{ $sisaBadgeStyle }}">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none"
                                            stroke="{{ $sisaIconColor }}" stroke-width="2">
                                            <rect width="18" height="18" x="3" y="4" rx="2" ry="2" />
                                            <line x1="16" x2="16" y1="2" y2="6" />
                                            <line x1="8" x2="8" y1="2" y2="6" />
                                            <line x1="3" x2="21" y1="10" y2="10" />
                                        </svg>
                                        {{ $sisaText }}
                                    </div>
                                </td>

                                {{-- Sisa Tagihan --}}
                                <td style="padding:16px 8px; vertical-align:top;">
                                    <div style="font-size:15px; font-weight:700; color:#374151; margin-bottom:6px;">Rp
                                        {{ number_format($order->remaining_balance ?? 0, 0, ',', '.') }}
                                    </div>
                                    <div style="font-size:11px; color:#666666; font-weight:500; margin-bottom:6px;">Total
                                        Tagihan</div>
                                    <div
                                        style="display:inline-flex; padding:4px 12px; border-radius:9999px; background:#faf5ff; border:1px solid #f3e8ff; color:#a855f7; font-size:12px; font-weight:700;">
                                        Rp {{ number_format($order->total_price ?? 0, 0, ',', '.') }}
                                    </div>
                                </td>

                                {{-- Tipe Produk & Status --}}
                                <td style="padding:16px 8px; vertical-align:top;">
                                    <div style="display:flex; flex-direction:column; gap:16px;">
                                        @foreach($order->orderItems as $item)
                                            @php
                                                $catName = match ($item->production_category) {
                                                    'produksi' => 'Produksi',
                                                    'non_produksi' => 'Non-Produksi',
                                                    'custom' => 'Custom',
                                                    default => 'Jasa',
                                                };

                                                // Progress calculation for this specific item
                                                $itemTasks = $item->productionTasks;
                                                $itemProgress = 0;
                                                $itemStatusLabel = 'Belum Diproses';

                                                if ($itemTasks->count() > 0) {
                                                    $itemDone = $itemTasks->where('status', 'done')->count();
                                                    $itemProgress = round(($itemDone / $itemTasks->count()) * 100);

                                                    // Get active stage name or finished
                                                    $activeItemTask = $itemTasks->whereIn('status', ['in_progress', 'pending', 'antrian'])->first();
                                                    if ($activeItemTask) {
                                                        $itemStatusLabel = $activeItemTask->stage_name;
                                                        // Give min progress if not done yet
                                                        $itemProgress = max(5, $itemProgress);
                                                    } elseif ($itemProgress == 100) {
                                                        $itemStatusLabel = 'Selesai';
                                                    } else {
                                                        $itemStatusLabel = 'Antrian';
                                                    }
                                                } else {
                                                    // Fallback to order status
                                                    if ($order->status === 'pending' || $order->status === 'diterima') {
                                                        $itemProgress = 0;
                                                        $itemStatusLabel = 'Belum Diproses';
                                                    } elseif ($order->status === 'selesai' || $order->status === 'diambil') {
                                                        $itemProgress = 100;
                                                        $itemStatusLabel = 'Selesai';
                                                    } else {
                                                        $itemProgress = 0;
                                                        $itemStatusLabel = 'Antrian';
                                                    }
                                                }
                                                @endphp
                                                @php
                                                    $statusKey = strtolower($itemStatusLabel);
                                                    $colorMap = [
                                                        'antrian' => '#818cf8',
                                                        'potong' => '#f87171',
                                                        'jahit' => '#22d3ee',
                                                        'bordir' => '#fb923c',
                                                        'kancing' => '#6366f1',
                                                        'finishing' => '#4ade80',
                                                        'qc' => '#a855f7',
                                                        'selesai' => '#2dd4bf',
                                                        'siap diambil' => '#22c55e',
                                                        'batal' => '#ef4444',
                                                        'diterima' => '#d946ef',
                                                        'dikerjakan' => '#3b82f6',
                                                    ];
                                                    
                                                    $baseColor = $colorMap[$statusKey] ?? ($itemProgress === 100 ? '#16a34a' : '#a855f7');
                                                    
                                                    // Generate lighter tints for background and border
                                                    $barColor = $baseColor;
                                                    $bgColor = $baseColor . '15'; // 15 is hex for ~8% opacity
                                                    $borderColor = $baseColor . '30'; // 30 is hex for ~18% opacity
                                                @endphp
                                                
                                                <div style="display:flex; flex-direction:column; gap:2px;">
                                                    <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                                                        <span
                                                            style="font-size:14px; font-weight:500; color:#6b7280;">{{ $item->quantity }}x
                                                            {{ $item->product_name }}</span>
                                                        <span
                                                            style="padding:2px 8px; border-radius:4px; border:1px solid {{ $borderColor }}; background:{{ $bgColor }}; color:{{ $barColor }}; font-size:11px; font-weight:500;">{{ $catName }}</span>
                                                    </div>

                                                    {{-- Individual Progress Bar --}}
                                                    <div style="display:flex; align-items:center; gap:10px; margin-top:2px;">
                                                        <div
                                                            style="width:70px; height:6px; background:#f1f5f9; border-radius:9999px; overflow:hidden; border:1px solid #e2e8f0;">
                                                            <div
                                                                style="height:100%; background:{{ $barColor }}; border-radius:9999px; width:{{ $itemProgress }}%;">
                                                            </div>
                                                        </div>
                                                        <span
                                                            style="font-size:10px; font-weight:600; color:{{ $barColor }};">{{ $itemStatusLabel }}</span>
                                                    </div>
                                                </div>
                                        @endforeach
                                    </div>
                                </td>

                                {{-- Actions --}}
                                <td style="padding:16px; vertical-align:top; text-align:right;">
                                    <div style="display:flex; flex-direction:column; align-items:flex-end; gap:8px;">

                                        <a href="{{ \App\Filament\Resources\Orders\OrderResource::getUrl('edit', ['record' => $order]) }}"
                                            style="display:inline-flex; align-items:center; gap:6px; padding:6px 12px; background:#eab308; color:white; font-size:12px; font-weight:600; border-radius:8px; text-decoration:none; box-shadow:0 1px 2px rgba(0,0,0,0.1);">
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none"
                                                stroke="currentColor" stroke-width="2.5">
                                                <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z" />
                                                <circle cx="12" cy="12" r="3" />
                                            </svg>
                                            Detail
                                        </a>

                                        @if(auth()->user()->role === 'owner')
                                            @php
                                                $phone = $order->customer->phone ?? '';
                                                if (str_starts_with($phone, '0')) {
                                                    $phone = '62' . substr($phone, 1);
                                                }
                                                $waUrl = 'https://wa.me/' . preg_replace('/[^0-9]/', '', $phone);
                                            @endphp
                                            <a href="{{ $waUrl }}" target="_blank"
                                                style="display:inline-flex; align-items:center; gap:6px; padding:6px 12px; background:#22c55e; color:white; font-size:12px; font-weight:600; border-radius:8px; text-decoration:none; box-shadow:0 1px 2px rgba(0,0,0,0.1);">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                                    <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z" />
                                                </svg>
                                                WhatsApp
                                            </a>
                                        @else
                                            <a href="{{ \App\Filament\Resources\ControlProduksis\ControlProduksiResource::getUrl('index') }}"
                                                style="display:inline-flex; align-items:center; gap:6px; padding:6px 12px; background:#7c3aed; color:white; font-size:12px; font-weight:600; border-radius:8px; text-decoration:none; box-shadow:0 1px 2px rgba(0,0,0,0.1);">
                                                <svg width="16" height="13" viewBox="0 0 24 24" fill="none"
                                                    stroke="currentColor" stroke-width="2">
                                                    <path d="M12 22h6a2 2 0 0 0 2-2V7l-5-5H6a2 2 0 0 0-2 2v10" />
                                                    <path d="M14 2v4a2 2 0 0 0 2 2h4" />
                                                    <path d="M10.4 12.6a2 2 0 1 1 3 3L8 21l-4 1 1-4Z" />
                                                </svg>
                                                Tugas
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" style="padding:48px; text-align:center; color:#666666;">
                                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#d1d5db"
                                        stroke-width="1.5" style="margin:0 auto 12px; display:block;">
                                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                                        <polyline points="17 8 12 3 7 8" />
                                        <line x1="12" x2="12" y1="3" y2="15" />
                                    </svg>
                                    <p style="font-size:14px;">Belum ada pesanan ditemukan.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- PAGINATION --}}
        @if($orders->hasPages())
            <div
                style="padding:16px 24px; border-top:1px solid #f3f4f6; display:flex; align-items:center; justify-content:space-between;">
                <div style="font-size:13px; color:#666666;">
                    Show Result
                    <span
                        style="display:inline-block; padding:2px 8px; margin:0 4px; background:#f9fafb; border:1px solid #e5e7eb; border-radius:6px;">{{ $perPage }}</span>
                </div>
                <div>
                    {{ $orders->links('filament::components.pagination') }}
                </div>
            </div>
        @endif
    </div>
</x-filament-widgets::widget>