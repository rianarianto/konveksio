<x-filament-widgets::widget>
    <style>
        [x-cloak] {
            display: none !important;
        }

        /* ─── BASE STYLES & OVERRIDES ─── */
        .r3-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            border: 1.5px solid #f1f5f9;
            font-family: inherit;
        }

        .r3-header-title {
            font-size: 20px;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 20px;
        }

        .r3-search-input,
        .r3-filter-select {
            background: #f9fafb;
            color: #374151;
            border: none;
        }

        .r3-table-container {
            min-width: 1000px;
            border: 1px solid #f3f4f6;
            border-radius: 16px;
        }

        .r3-th {
            background: #fafafa;
            border-bottom: 1px solid #f3f4f6;
            color: #666666;
        }

        .r3-tr {
            border-bottom: 1px solid #f9fafb;
        }

        .r3-td-text {
            color: #666666;
        }

        .r3-td-text-bold {
            color: #374151;
        }

        .r3-action-btn {
            background: #f3f4f6;
            color: #4b5563;
            border-color: #e5e7eb;
        }

        .r3-dropdown {
            background: white;
            border: 1px solid #e5e7eb;
        }

        /* ─── DARK MODE ─── */
        .dark .r3-container {
            background: rgba(30, 20, 50, .7);
            border-color: rgba(255, 255, 255, .05);
        }

        .dark .r3-header-title {
            color: #f3f4f6;
        }

        .dark .r3-search-input,
        .dark .r3-filter-select,
        .dark .r3-filter-date-container {
            background: rgba(255, 255, 255, .05) !important;
            color: #f3f4f6 !important;
        }

        .dark .r3-filter-date-input {
            color: #f3f4f6 !important;
            color-scheme: dark;
        }

        .dark .r3-table-container {
            border-color: rgba(255, 255, 255, .05);
        }

        .dark .r3-th {
            background: rgba(0, 0, 0, .2);
            border-bottom-color: rgba(255, 255, 255, .05);
            color: #9ca3af;
        }

        .dark .r3-tr {
            border-bottom-color: rgba(255, 255, 255, .05);
        }

        .dark .r3-td-text {
            color: #d1d5db;
        }

        .dark .r3-td-text-bold {
            color: #f3f4f6;
        }

        .dark .r3-action-btn {
            background: rgba(255, 255, 255, .05);
            color: #d1d5db;
            border-color: rgba(255, 255, 255, .1);
        }

        .dark .r3-dropdown {
            background: #18181b;
            border-color: rgba(255, 255, 255, .1);
        }

        .dark .r3-dropdown-item {
            color: #d1d5db !important;
        }
    </style>
    <div wire:poll.10s class="r3-container">
        {{-- HEADER SECTION --}}
        <div style="padding:24px 24px 16px;">
            <h2 class="r3-header-title">
                {{ auth()->user()->role === 'owner' ? 'PANTAUAN PRIORITAS OWNER' : 'Daftar Pesanan Cepat' }}
            </h2>

            <div style="display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:16px;">
                {{-- Add Button --}}
                <a href="{{ \App\Filament\Resources\Orders\OrderResource::getUrl('index') }}"
                    style="display:inline-flex; align-items:center; gap:8px; background:#7c3aed; color:white; padding:10px 20px; border-radius:12px; font-weight:500; font-size:14px; text-decoration:none; transition:background 0.2s;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M5 12h14" />
                        <path d="M12 5v14" />
                    </svg>
                    Tambah Pesanan Baru
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
                            class="r3-search-input"
                            style="border-radius:12px; padding:10px 16px 10px 36px; font-size:13px; width:180px; outline:none;">
                    </div>

                    {{-- Status Filter --}}
                    <div style="position:relative;">
                        <select wire:model.live="statusFilter" class="r3-filter-select"
                            style="appearance:none; border-radius:12px; padding:10px 36px 10px 16px; font-size:13px; cursor:pointer; min-width:155px; outline:none;">
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
                    <div class="r3-filter-date-container"
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
                        <input wire:model.live="deadlineFilter" type="date" class="r3-filter-date-input"
                            style="background:transparent; border:none; padding:10px 16px 10px 0; font-size:13px; color:#6b7280; outline:none; cursor:text;">
                    </div>

                    {{-- Per Page --}}
                    <div style="display:flex; align-items:center; gap:8px;">
                        <div style="position:relative;">
                            <select wire:model.live="perPage" class="r3-filter-select"
                                style="appearance:none; border-radius:12px; padding:10px 28px 10px 12px; font-size:13px; cursor:pointer; outline:none;">
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
        <div style="overflow-x:auto; padding:0 24px 50px;">
            <div class="r3-table-container">
                <table style="width:100%; border-collapse:collapse; text-align:left;">
                    <thead>
                        <tr class="r3-th">
                            <th style="padding:12px 16px; width:40px;">
                                <input type="checkbox" disabled style="accent-color:#7c3aed;">
                            </th>
                            <th style="padding:12px 8px; font-size:13px; font-weight:600; width:32px;">#</th>
                            <th style="padding:12px 8px; font-size:13px; font-weight:600; width:22%;">Pesanan & Pelanggan</th>
                            <th style="padding:12px 8px; font-size:13px; font-weight:600; width:18%;">Timeline</th>
                            <th style="padding:12px 8px; font-size:13px; font-weight:600; width:18%;">Finance</th>
                            <th style="padding:12px 8px; font-size:13px; font-weight:600; min-width:280px;">Produk & Status</th>
                            <th style="padding:12px 16px; font-size:13px; font-weight:600; width:120px; text-align:right;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($orders as $index => $order)
                            <tr class="r3-tr">
                                {{-- Checkbox --}}
                                <td style="padding:16px; vertical-align:top;">
                                    <input type="checkbox" disabled style="accent-color:#7c3aed;">
                                </td>

                                {{-- Index --}}
                                <td class="r3-td-text" style="padding:16px 8px; vertical-align:top; font-size:14px; font-weight:500;">
                                    {{ $orders->firstItem() + $index }}
                                </td>

                                {{-- KOLOM 1: Pesanan & Pelanggan --}}
                                <td style="padding:16px 8px; vertical-align:top;">
                                    @php
                                        $expressHtml = '';
                                        if ($order->is_express) {
                                            $expressHtml = '<span style="background:#dc2626; color:#fff; font-size:10px; font-weight:800; padding:2px 8px; border-radius:9999px; letter-spacing:0.02em; display:inline-flex; align-items:center; gap:3px; margin-right:4px;">'
                                                . '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="currentColor" stroke="none"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>'
                                                . 'EXPRESS</span>';
                                        }

                                        $orderNum = '<div style="display:flex; align-items:center; gap:6px; margin-bottom:8px;">'
                                            . $expressHtml
                                            . '<div style="font-weight:600; color:#666666; font-size:13px; letter-spacing:0.04em; text-transform:uppercase;">' . e($order->order_number) . '</div>'
                                            . '</div>';

                                        $customer = $order->customer;
                                        $name = $customer?->name ?? '-';
                                        $phone = $customer?->phone ?? null;

                                        $pillClass = 'display:inline-flex; align-items:center; gap:6px; padding:4px 12px; border:1px solid #f3e8ff; border-radius:9999px; background:white; color:#a855f7; font-size:12px; font-weight:500;';

                                        $nameBadge = '<div style="' . $pillClass . '">'
                                            . '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>'
                                            . e($name)
                                            . '</div>';

                                        $phoneLine = $phone
                                            ? '<div style="' . $pillClass . '">'
                                            . '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>'
                                            . e($phone)
                                            . '</div>'
                                            : '';

                                        $creatorName = $order->creator?->name ?? 'Sistem';
                                        $creatorPill = '<div style="display:inline-flex; align-items:center; gap:4px; padding:2px 8px; border-radius:9999px; background:#f8fafc; border:1px solid #e2e8f0; color:#64748b; font-size:11px; font-weight:500;">'
                                            . '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>'
                                            . 'Oleh: ' . e($creatorName)
                                            . '</div>';
                                    @endphp
                                    <div style="display:flex; flex-direction:column; gap:6px; align-items:flex-start;">
                                        {!! $orderNum !!}
                                        {!! $nameBadge !!}
                                        {!! $phoneLine !!}
                                        {!! $creatorPill !!}
                                    </div>
                                </td>

                                {{-- KOLOM 2: Timeline --}}
                                <td style="padding:16px 8px; vertical-align:top;">
                                    @php
                                        $masukStr = $order->order_date instanceof \Illuminate\Support\Carbon ? $order->order_date->format('d M Y') : ($order->order_date ? date('d M Y', strtotime($order->order_date)) : '-');
                                        $deadlineStr = $order->deadline instanceof \Illuminate\Support\Carbon ? $order->deadline->format('d M Y') : ($order->deadline ? date('d M Y', strtotime($order->deadline)) : '-');

                                        $days = $order->deadline
                                            ? now()->startOfDay()->diffInDays(\Illuminate\Support\Carbon::parse($order->deadline)->startOfDay(), false)
                                            : null;

                                        $sisaBadgeStyle = '';
                                        $sisaText = '';
                                        $sisaIconColor = '';

                                        if ($days === null) {
                                            $sisaText = 'Tidak ada';
                                            $sisaBadgeStyle = 'color:#9ca3af; border:1px solid #e5e7eb; background:#f9fafb;';
                                            $sisaIconColor = '#9ca3af';
                                        } elseif ($days < 0) {
                                            $sisaText = 'Terlambat ' . abs($days) . ' hari';
                                            $sisaBadgeStyle = 'color:#e11d48; border:1px solid #fda4af; background:white;';
                                            $sisaIconColor = '#e11d48';
                                        } elseif ($days === 0) {
                                            $sisaText = 'Hari ini';
                                            $sisaBadgeStyle = 'color:#e11d48; border:1px solid #fda4af; background:white;';
                                            $sisaIconColor = '#e11d48';
                                        } elseif ($days <= 3) {
                                            $sisaText = 'Sisa ' . $days . ' hari';
                                            $sisaBadgeStyle = 'color:#e11d48; border:1px solid #fda4af; background:white;';
                                            $sisaIconColor = '#e11d48';
                                        } elseif ($days <= 7) {
                                            $sisaText = 'Sisa ' . $days . ' hari';
                                            $sisaBadgeStyle = 'color:#ca8a04; border:1px solid #fbbf24; background:white;';
                                            $sisaIconColor = '#ca8a04';
                                        } else {
                                            $sisaText = 'Sisa ' . $days . ' hari';
                                            $sisaBadgeStyle = 'color:#16a34a; border:1px solid #86efac; background:white;';
                                            $sisaIconColor = '#16a34a';
                                        }

                                        $displayStatus = $order->status;
                                        if ($displayStatus === 'diproses') {
                                            $allTasks = $order->orderItems->flatMap->productionTasks;
                                            if ($allTasks->isNotEmpty() && $allTasks->every(fn($t) => $t->status === 'done')) {
                                                $displayStatus = 'selesai';
                                            }
                                        }

                                        $badgeStyles = match ($displayStatus) {
                                            'draft' => ['bg' => '#fef3c7', 'text' => '#d97706', 'border' => '#fde68a', 'indicator' => '#d97706', 'label' => 'DRAFT'],
                                            'diterima' => ['bg' => '#f3e8ff', 'text' => '#7e22ce', 'border' => '#ddd6fe', 'indicator' => '#7e22ce', 'label' => 'DITERIMA'],
                                            'diproses' => ['bg' => '#dbeafe', 'text' => '#2563eb', 'border' => '#bfdbfe', 'indicator' => '#2563eb', 'label' => 'PROSES'],
                                            'selesai' => ['bg' => '#dcfce7', 'text' => '#16a34a', 'border' => '#bbf7d0', 'indicator' => '#16a34a', 'label' => 'SELESAI'],
                                            'siap_diambil' => ['bg' => '#dcfce7', 'text' => '#16a34a', 'border' => '#bbf7d0', 'indicator' => '#16a34a', 'label' => 'SIAP DIAMBIL'],
                                            default => ['bg' => '#f3f4f6', 'text' => '#4b5563', 'border' => '#e5e7eb', 'indicator' => '#6b7280', 'label' => strtoupper($displayStatus)],
                                        };

                                        $statusBadge = '<div style="display:inline-flex; align-items:center; gap:6px; padding:3px 8px; border-radius:6px; font-size:10px; font-weight:800; border:1px solid ' . $badgeStyles['border'] . '; background:' . $badgeStyles['bg'] . '; color:' . $badgeStyles['text'] . '; line-height:1; vertical-align:middle;">'
                                            . '<div style="width:3.5px; height:12px; background-color:' . $badgeStyles['indicator'] . '; border-radius:2px; flex-shrink:0;"></div>'
                                            . '<span>' . $badgeStyles['label'] . '</span>'
                                            . '</div>';
                                    @endphp
                                    <div style="display:flex; flex-direction:column;">
                                        <div style="display:flex; align-items:center; gap:8px; margin-bottom:4px;">
                                            <span style="font-size:13px; font-weight:500; color:#9ca3af;">{{ $masukStr }}</span>
                                            {!! $statusBadge !!}
                                        </div>
                                        <div style="display:flex; align-items:center; gap:8px; margin-bottom:8px;">
                                            <span style="font-size:14px; font-weight:500; color:#4b5563;">{{ $deadlineStr }}</span>
                                        </div>
                                        <div style="display:inline-flex; align-items:center; gap:6px; padding:4px 10px; border-radius:9999px; font-size:11px; font-weight:500; {!! $sisaBadgeStyle !!}">
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="{{ $sisaIconColor }}" stroke-width="2.5"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>
                                            {{ $sisaText }}
                                        </div>
                                    </div>
                                </td>

                                {{-- KOLOM 3: Finance --}}
                                <td style="padding:16px 8px; vertical-align:top;">
                                    @php
                                        $total = (int) $order->total_price;
                                        $paid = (int) $order->payments->sum('amount');
                                        $sisa = max(0, $total - $paid);
                                        $lunas = ($total > 0 && $sisa === 0);
                                        $isZero = ($total === 0);

                                        $label = 'Rp ' . number_format($sisa, 0, ',', '.');
                                        if ($isZero) {
                                            $label = 'MENUNGGU PRODUK';
                                            $sisaColor = '#9ca3af';
                                            $sisaBg = '#f3f4f6';
                                        } elseif ($lunas) {
                                            $label = 'LUNAS';
                                            $sisaColor = '#16a34a';
                                            $sisaBg = '#f0fdf4';
                                        } else {
                                            $sisaColor = '#7c3aed';
                                            $sisaBg = '#f3e8ff';
                                        }

                                        $cicilan = $order->payments->count();
                                    @endphp
                                    <div style="display:flex; flex-direction:column; min-width:180px;">
                                        <div style="display:inline-flex; align-items:center; gap:6px; padding:6px 14px; border-radius:12px; background:{{ $sisaBg }}; color:{{ $sisaColor }}; font-size:15px; font-weight:700; margin-bottom:8px;">
                                            {{ $label }}
                                        </div>
                                        <div style="display:flex; justify-content:space-between; align-items:center; font-size:12px; color:#6b7280; font-weight:500; margin-bottom:4px;">
                                            <span>Total Tagihan</span>
                                            <span style="color:#374151; font-weight:600;">Rp {{ number_format($total, 0, ',', '.') }}</span>
                                        </div>
                                        @if($paid > 0)
                                            <div style="display:flex; justify-content:space-between; align-items:center; font-size:12px; color:#6b7280; font-weight:500;">
                                                <span>Sudah Dibayar</span>
                                                <span style="color:#374151; font-weight:600;">Rp {{ number_format($paid, 0, ',', '.') }} ({{ $cicilan }}x)</span>
                                            </div>
                                        @else
                                            <div style="display:inline-flex; align-items:center; gap:6px; font-size:12px; color:#f59e0b; font-weight:600;">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="8" y2="12"/><line x1="12" x2="12.01" y1="16" y2="16"/></svg>
                                                Belum ada deposit
                                            </div>
                                        @endif
                                    </div>
                                </td>

                                {{-- KOLOM 4: Produk & Status --}}
                                <td style="padding:16px 8px; vertical-align:top;">
                                    @php
                                        $items = $order->orderItems;
                                    @endphp
                                    @if($items->isEmpty())
                                        <span style="color:#9ca3af;font-size:13px;">-</span>
                                    @else
                                        @php
                                            $groupedItems = $items->groupBy('product_name');
                                        @endphp
                                        <div style="display:flex; flex-direction:column; gap:12px;">
                                            @foreach($groupedItems as $productName => $itemsGroup)
                                                @php
                                                    $totalQty = $itemsGroup->sum('quantity');

                                                    $categories = [];
                                                    foreach ($itemsGroup as $item) {
                                                        $cat = match ($item->production_category) {
                                                            'custom' => ['Produksi', 'rgba(124,58,237,0.10)', '#7c3aed'],
                                                            'non_produksi' => ['Non-Produksi', 'rgba(245,158,11,0.12)', '#d97706'],
                                                            'jasa' => ['Jasa', 'rgba(16,185,129,0.12)', '#059669'],
                                                            default => ['Produksi', 'rgba(124,58,237,0.10)', '#7c3aed'],
                                                        };
                                                        $categories[$cat[0]] = $cat;
                                                    }
                                                    $firstCat = reset($categories);

                                                    $groupTasks = $itemsGroup->flatMap->productionTasks;
                                                    $totalTasks = $groupTasks->count();
                                                    $doneTasks = $groupTasks->where('status', 'done')->count();

                                                    $groupWo = $itemsGroup->map(fn($it) => $it->workOrder)->filter()->first();

                                                    $groupItemIds = $itemsGroup->pluck('id');
                                                    $hasActiveReturn = \App\Models\OrderReturn::whereIn('order_item_id', $groupItemIds)
                                                        ->whereIn('status', ['pending', 'diproses'])
                                                        ->exists();

                                                    $isHandedOver = ($order->status === 'selesai' || $order->status === 'diambil' || !empty($order->pickup_proof) || !empty($order->pickup_at));

                                                    if ($hasActiveReturn) {
                                                        $displayStatusText = 'Retur';
                                                    } elseif ($isHandedOver) {
                                                        $displayStatusText = 'Diserahkan';
                                                    } elseif ($groupWo) {
                                                        if ($groupWo->status === \App\Models\WorkOrder::STATUS_COMPLETED || $groupWo->delivered_at !== null) {
                                                            $displayStatusText = 'Siap Diambil';
                                                        } else {
                                                            $displayStatusText = match($groupWo->status) {
                                                                \App\Models\WorkOrder::STATUS_CREATED => 'Antrian',
                                                                \App\Models\WorkOrder::STATUS_QC_PREP, 'QC_PERSIAPAN' => 'QC Persiapan',
                                                                \App\Models\WorkOrder::STATUS_QC_REVIEW => 'QC ' . str_replace('_', ' ', $groupWo->current_review_stage ?? ''),
                                                                \App\Models\WorkOrder::STATUS_QC_AKHIR => 'QC Akhir',
                                                                default => str_replace('_', ' ', $groupWo->status),
                                                            };
                                                        }
                                                    } elseif ($totalTasks > 0) {
                                                        $activeTask = $groupTasks->whereIn('status', ['in_progress', 'pending', 'antrian'])->first();
                                                        if ($activeTask) {
                                                            $displayStatusText = $activeTask->stage_name ?: ($activeTask->nama_tugas ?: 'Proses');
                                                        } elseif ($doneTasks == $totalTasks) {
                                                            $displayStatusText = 'Siap Diambil';
                                                        } else {
                                                            $displayStatusText = 'Antrian';
                                                        }
                                                    } else {
                                                        if ($order->status === 'batal') {
                                                            $displayStatusText = 'Batal';
                                                        } else {
                                                            $displayStatusText = 'Siap Diambil';
                                                        }
                                                    }

                                                    $statusBadge = match ($displayStatusText) {
                                                        'Diserahkan' => '<span style="padding:2px 6px; border-radius:4px; background:#f3f4f6; color:#6b7280; font-size:10px; font-weight:600;">DISERAHKAN</span>',
                                                        'Retur' => '<span style="padding:2px 6px; border-radius:4px; background:#fef2f2; color:#dc2626; font-size:10px; font-weight:600;">REVISI RETUR</span>',
                                                        'Siap Diambil' => '<span style="padding:2px 6px; border-radius:4px; background:#dcfce7; color:#16a34a; font-size:10px; font-weight:600;">SIAP DIAMBIL</span>',
                                                        'Selesai' => '<span style="padding:2px 6px; border-radius:4px; background:#f3f4f6; color:#6b7280; font-size:10px; font-weight:600;">DISERAHKAN</span>',
                                                        'Proses' => '<span style="padding:2px 6px; border-radius:4px; background:#dbeafe; color:#2563eb; font-size:10px; font-weight:600;">PROSES</span>',
                                                        'Belum Diatur' => '<span style="padding:2px 6px; border-radius:4px; background:#f3f4f6; color:#6b7280; font-size:10px; font-weight:600;">BELUM DIATUR</span>',
                                                        default => '<span style="padding:2px 6px; border-radius:4px; background:#fef3c7; color:#d97706; font-size:10px; font-weight:600;">'.strtoupper($displayStatusText).'</span>',
                                                    };
                                                @endphp

                                                <div style="display:flex; flex-direction:column; background:white; border:1.5px solid #f1f5f9; border-radius:12px; padding:12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);">
                                                    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:4px; gap:8px;">
                                                        <div style="font-size:14px; font-weight:700; color:#111827; line-height:1.4;">
                                                            <span style="color:#6b7280; margin-right:4px;">{{ $totalQty }}x</span>{{ $productName }}
                                                        </div>
                                                        <div style="display:inline-flex; align-items:center; padding:2px 8px; border-radius:9999px; font-size:11px; font-weight:600; background:{{ $firstCat[1] }}; color:{{ $firstCat[2] }};">
                                                            {{ $firstCat[0] }}
                                                        </div>
                                                    </div>
                                                    <div style="margin-top:4px;">
                                                        {!! $statusBadge !!}
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>

                                {{-- Actions --}}
                                <td style="padding:16px; vertical-align:top; text-align:right;">
                                    <div x-data="{ open: false }"
                                        style="position:relative; display:inline-block; text-align:left;">
                                        <button @click.stop="open = !open" class="r3-action-btn"
                                            style="padding:6px; border-radius:10px; border:1px solid #e5e7eb; background:white; cursor:pointer; transition:all 0.2s; display:flex; align-items:center; justify-content:center; color:#6b7280;"
                                            onmouseover="this.style.background='#f9fafb'" onmouseout="this.style.background='white'">
                                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                                                stroke="currentColor" stroke-width="2.5" stroke-linecap="round"
                                                stroke-linejoin="round">
                                                <circle cx="12" cy="12" r="1" />
                                                <circle cx="12" cy="5" r="1" />
                                                <circle cx="12" cy="19" r="1" />
                                            </svg>
                                        </button>

                                        <div x-show="open" x-cloak @click.away="open = false"
                                            x-transition:enter="transition ease-out duration-100"
                                            x-transition:enter-start="transform opacity-0 scale-95"
                                            x-transition:enter-end="transform opacity-100 scale-100"
                                            x-transition:leave="transition ease-in duration-75"
                                            x-transition:leave-start="transform opacity-100 scale-100"
                                            x-transition:leave-end="transform opacity-0 scale-95" class="r3-dropdown"
                                            style="position:absolute; right:0; top:calc(100% + 5px); z-index:1000; width:185px; border-radius:12px; box-shadow:0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05); padding:6px; background:white; border:1px solid #e5e7eb;">

                                            @php
                                                $canEditOrder = \App\Filament\Resources\Orders\OrderResource::canEdit($order);
                                                $canDeliverOrder = \App\Filament\Resources\Orders\Schemas\OrderDeliveryForm::isVisible($order);
                                                $canReturnOrder = in_array(auth()->user()->role, ['owner', 'admin']) && $order->status === 'selesai';
                                                $canViewProof = $order->status === 'selesai' || !empty($order->pickup_proof);
                                                $canDeleteOrder = auth()->user()->role === 'owner';

                                                $detailUrl = \App\Filament\Resources\Orders\OrderResource::getUrl(
                                                    in_array($order->status, ['produksi', 'draft']) ? 'edit' : 'view',
                                                    ['record' => $order]
                                                );
                                            @endphp

                                            {{-- 1. Lihat Detail --}}
                                            <a href="{{ $detailUrl }}"
                                                style="display:flex; align-items:center; gap:8px; padding:6px 8px; color:#7c3aed; font-size:12px; font-weight:600; text-decoration:none; border-radius:8px; transition:all 0.2s;"
                                                onmouseover="this.style.background='#f5f3ff'"
                                                onmouseout="this.style.background='transparent'">
                                                <div style="width:22px; height:22px; background:#f5f3ff; border-radius:6px; display:flex; align-items:center; justify-content:center;">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                                        <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z" />
                                                        <circle cx="12" cy="12" r="3" />
                                                    </svg>
                                                </div>
                                                Lihat Detail
                                            </a>

                                            {{-- 2. Cetak SPK --}}
                                            <a href="{{ route('orders.spk', ['order' => $order->id]) }}" target="_blank" @click="open = false"
                                                style="display:flex; align-items:center; gap:8px; padding:6px 8px; color:#0284c7; font-size:12px; font-weight:600; text-decoration:none; border-radius:8px; transition:all 0.2s;"
                                                onmouseover="this.style.background='#f0f9ff'"
                                                onmouseout="this.style.background='transparent'">
                                                <div style="width:22px; height:22px; background:#f0f9ff; border-radius:6px; display:flex; align-items:center; justify-content:center;">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                                        <polyline points="6 9 6 2 18 2 18 9"></polyline>
                                                        <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                                                        <rect x="6" y="14" width="12" height="8"></rect>
                                                    </svg>
                                                </div>
                                                Cetak SPK
                                            </a>

                                            {{-- 3. Kuitansi --}}
                                            <button wire:click="downloadReceipt({{ $order->id }})" @click="open = false"
                                                style="width:100%; display:flex; align-items:center; gap:8px; padding:6px 8px; color:#16a34a; font-size:12px; font-weight:600; background:none; border:none; cursor:pointer; border-radius:8px; transition:all 0.2s; text-align:left;"
                                                onmouseover="this.style.background='#f0fdf4'"
                                                onmouseout="this.style.background='transparent'">
                                                <div style="width:22px; height:22px; background:#f0fdf4; border-radius:6px; display:flex; align-items:center; justify-content:center;">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                                                        <polyline points="14 2 14 8 20 8" />
                                                        <line x1="12" y1="18" x2="12" y2="12" />
                                                        <polyline points="9 15 12 18 15 15" />
                                                    </svg>
                                                </div>
                                                Kuitansi
                                            </button>

                                            {{-- 4. Edit --}}
                                            @if($canEditOrder)
                                                <a href="{{ \App\Filament\Resources\Orders\OrderResource::getUrl('edit', ['record' => $order]) }}"
                                                    style="display:flex; align-items:center; gap:8px; padding:6px 8px; color:#d97706; font-size:12px; font-weight:600; text-decoration:none; border-radius:8px; transition:all 0.2s;"
                                                    onmouseover="this.style.background='#fffbeb'"
                                                    onmouseout="this.style.background='transparent'">
                                                    <div style="width:22px; height:22px; background:#fffbeb; border-radius:6px; display:flex; align-items:center; justify-content:center;">
                                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                                            <path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z" />
                                                        </svg>
                                                    </div>
                                                    Edit
                                                </a>
                                            @endif

                                            {{-- 5. Serahkan Pesanan --}}
                                            @if($canDeliverOrder)
                                                <a href="{{ \App\Filament\Resources\Orders\OrderResource::getUrl('view', ['record' => $order]) }}"
                                                    style="display:flex; align-items:center; gap:8px; padding:6px 8px; color:#16a34a; font-size:12px; font-weight:600; text-decoration:none; border-radius:8px; transition:all 0.2s;"
                                                    onmouseover="this.style.background='#f0fdf4'"
                                                    onmouseout="this.style.background='transparent'">
                                                    <div style="width:22px; height:22px; background:#f0fdf4; border-radius:6px; display:flex; align-items:center; justify-content:center;">
                                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                                            <polyline points="22 4 12 14.01 9 11.01"></polyline>
                                                        </svg>
                                                    </div>
                                                    Serahkan Pesanan
                                                </a>
                                            @endif

                                            {{-- 6. Retur Pesanan --}}
                                            @if($canReturnOrder)
                                                <a href="{{ \App\Filament\Resources\Orders\OrderResource::getUrl('view', ['record' => $order]) }}"
                                                    style="display:flex; align-items:center; gap:8px; padding:6px 8px; color:#ea580c; font-size:12px; font-weight:600; text-decoration:none; border-radius:8px; transition:all 0.2s;"
                                                    onmouseover="this.style.background='#fff7ed'"
                                                    onmouseout="this.style.background='transparent'">
                                                    <div style="width:22px; height:22px; background:#fff7ed; border-radius:6px; display:flex; align-items:center; justify-content:center;">
                                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                                            <path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"/>
                                                        </svg>
                                                    </div>
                                                    Retur Pesanan
                                                </a>
                                            @endif

                                            {{-- 7. Lihat Bukti Penyerahan --}}
                                            @if($canViewProof)
                                                <a href="{{ \App\Filament\Resources\Orders\OrderResource::getUrl('view', ['record' => $order]) }}"
                                                    style="display:flex; align-items:center; gap:8px; padding:6px 8px; color:#0284c7; font-size:12px; font-weight:600; text-decoration:none; border-radius:8px; transition:all 0.2s;"
                                                    onmouseover="this.style.background='#f0f9ff'"
                                                    onmouseout="this.style.background='transparent'">
                                                    <div style="width:22px; height:22px; background:#f0f9ff; border-radius:6px; display:flex; align-items:center; justify-content:center;">
                                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                                            <path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z"/>
                                                            <circle cx="12" cy="13" r="3"/>
                                                        </svg>
                                                    </div>
                                                    Lihat Bukti Penyerahan
                                                </a>
                                            @endif

                                            {{-- 8. Delete --}}
                                            @if($canDeleteOrder)
                                                <button
                                                    @click="if(confirm('Apakah Anda yakin ingin menghapus pesanan ini?')) { $wire.deleteOrder({{ $order->id }}); open = false; }"
                                                    style="width:100%; display:flex; align-items:center; gap:8px; padding:6px 8px; color:#ef4444; font-size:12px; font-weight:600; background:none; border:none; cursor:pointer; border-radius:8px; transition:all 0.2s; text-align:left;"
                                                    onmouseover="this.style.background='#fef2f2'"
                                                    onmouseout="this.style.background='transparent'">
                                                    <div style="width:22px; height:22px; background:#fef2f2; border-radius:6px; display:flex; align-items:center; justify-content:center;">
                                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
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