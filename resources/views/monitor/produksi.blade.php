<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="30">
    <title>Monitor Produksi — {{ $shop->name }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap"
        rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --bg: #0f1117;
            --surface: #1a1d27;
            --surface2: #22263a;
            --border: rgba(255, 255, 255, 0.07);
            --text: #f1f5f9;
            --muted: #94a3b8;
            --green: #10b981;
            --green-bg: rgba(16, 185, 129, 0.25);
            --yellow: #fbbf24;
            --yellow-bg: rgba(251, 191, 36, 0.25);
            --red: #f87171;
            --red-bg: rgba(248, 113, 113, 0.2);
            --blue: #60a5fa;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: var(--text);
            height: 100vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        /* ── HEADER ── */
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 24px;
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            flex-shrink: 0;
        }

        .header-shop {
            font-size: 14px;
            font-weight: 600;
            color: var(--muted);
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }

        .header-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--text);
            letter-spacing: -0.02em;
        }

        .header-clock {
            text-align: right;
        }

        .clock-time {
            font-size: 28px;
            font-weight: 800;
            color: var(--text);
            letter-spacing: -0.04em;
            line-height: 1;
        }

        .clock-date {
            font-size: 12px;
            color: var(--muted);
            margin-top: 2px;
        }

        /* ── BODY ── */
        .body {
            display: flex;
            flex-direction: column;
            flex: 1;
            overflow: hidden;
            gap: 0;
            position: relative;
        }

        /* ── MAIN AREA: 3 KOLOM PENUH DI SMART TV ── */
        .main {
            flex: 1;
            overflow-x: auto;
            overflow-y: hidden;
            display: grid;
            grid-auto-flow: column;
            grid-auto-columns: calc((100% - 32px) / 3);
            gap: 16px;
            padding: 16px 20px;
        }

        @media (max-width: 1200px) {
            .main {
                grid-auto-columns: calc((100% - 16px) / 2);
            }
        }

        @media (max-width: 768px) {
            .main {
                grid-auto-columns: 100%;
            }
        }

        .main::-webkit-scrollbar {
            height: 6px;
        }

        .main::-webkit-scrollbar-track {
            background: transparent;
        }

        .main::-webkit-scrollbar-thumb {
            background: var(--surface2);
            border-radius: 4px;
        }

        /* ── BOTTOM TICKER: ANTRIAN PRODUKSI ── */
        .queue-ticker {
            height: 52px;
            flex-shrink: 0;
            background: var(--surface);
            border-top: 1px solid var(--border);
            display: flex;
            align-items: center;
            padding: 0 16px;
            gap: 16px;
            overflow: hidden;
            z-index: 10;
        }

        .queue-ticker-label {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            font-weight: 800;
            color: var(--yellow);
            background: var(--yellow-bg);
            padding: 6px 14px;
            border-radius: 8px;
            white-space: nowrap;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            flex-shrink: 0;
        }

        .queue-ticker-track {
            flex: 1;
            overflow: hidden;
            position: relative;
            display: flex;
            align-items: center;
            mask-image: linear-gradient(to right, transparent, black 20px, black calc(100% - 20px), transparent);
            -webkit-mask-image: linear-gradient(to right, transparent, black 20px, black calc(100% - 20px), transparent);
        }

        .queue-ticker-items {
            display: flex;
            align-items: center;
            gap: 12px;
            white-space: nowrap;
            animation: tickerScroll 40s linear infinite;
        }

        .queue-ticker-items:hover {
            animation-play-state: paused;
        }

        @keyframes tickerScroll {
            0% {
                transform: translateX(0);
            }
            100% {
                transform: translateX(-50%);
            }
        }

        .ticker-card {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: var(--surface2);
            border: 1px solid var(--border);
            padding: 6px 14px;
            border-radius: 8px;
            font-size: 13px;
            color: var(--text);
            flex-shrink: 0;
        }

        .ticker-card.is-express {
            border-color: var(--red);
            background: rgba(239, 68, 68, 0.15);
        }

        .badge-cat {
            font-size: 10px;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 4px;
            background: rgba(255, 255, 255, 0.08);
            color: var(--muted);
        }

        .badge-express {
            font-size: 10px;
            font-weight: 800;
            padding: 2px 8px;
            border-radius: 999px;
            background: var(--red-bg);
            color: var(--red);
            animation: pulse 1.5s infinite;
        }

        .order-num {
            font-size: 11px;
            color: var(--muted);
            font-weight: 600;
        }

        .ticker-prod-name {
            font-size: 13px;
            font-weight: 700;
            color: #fff;
        }

        .ticker-details {
            font-size: 11px;
            font-weight: 600;
            color: #94a3b8;
            background: rgba(0, 0, 0, 0.2);
            padding: 2px 6px;
            border-radius: 4px;
        }

        .ticker-deadline {
            font-size: 12px;
            font-weight: 700;
        }

        .ticker-deadline.urgent {
            color: var(--red);
        }

        .ticker-deadline.soon {
            color: var(--yellow);
        }

        .ticker-deadline.ok {
            color: var(--green);
        }

        /* ── ITEM CARD ── */
        .item-card {
            width: 100%;
            height: 100%;
            flex-shrink: 0;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        }

        .item-card.is-express {
            border-color: var(--red);
            border-width: 2px;
            box-shadow: 0 0 20px rgba(239, 68, 68, 0.2);
        }

        .item-card-header {
            padding: 12px 16px 10px;
            border-bottom: 1px solid var(--border);
        }

        .item-card-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            margin-bottom: 6px;
        }

        .customer-name {
            font-size: 13px;
            font-weight: 600;
            color: #cbd5e1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .express-banner {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: var(--red);
            color: #fff;
            font-size: 10px;
            font-weight: 800;
            padding: 2px 8px;
            border-radius: 999px;
            letter-spacing: 0.05em;
            animation: pulse 1.5s infinite;
            flex-shrink: 0;
        }

        @keyframes pulse {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: 0.75;
            }
        }

        .item-qty {
            font-size: 18px;
            font-weight: 800;
            color: var(--blue);
            line-height: 1;
            margin-bottom: 2px;
        }

        .item-name {
            font-size: 24px;
            font-weight: 800;
            color: #fff;
            letter-spacing: -0.02em;
            line-height: 1.15;
            margin-bottom: 8px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .item-info {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
            margin-bottom: 6px;
        }

        .info-badge {
            font-size: 12px;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 6px;
            background: rgba(59, 130, 246, 0.25);
            color: #bfdbfe;
        }

        .item-sizes {
            font-size: 12px;
            font-weight: 600;
            color: var(--muted);
            margin-bottom: 4px;
        }

        .item-sablon {
            font-size: 13px;
            font-weight: 600;
            color: #94a3b8;
            margin-top: 4px;
        }

        /* ── DEADLINE ── */
        .deadline-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 8px 16px;
            border-bottom: 1px solid var(--border);
        }

        .deadline-text {
            font-size: 14px;
            font-weight: 700;
        }

        .deadline-text.urgent {
            color: var(--red);
        }

        .deadline-text.soon {
            color: var(--yellow);
        }

        .deadline-text.ok {
            color: var(--green);
        }

        .hari-badge {
            font-size: 13px;
            font-weight: 800;
            padding: 4px 12px;
            border-radius: 6px;
        }

        .hari-badge.urgent {
            background: var(--red-bg);
            color: var(--red);
        }

        .hari-badge.soon {
            background: var(--yellow-bg);
            color: var(--yellow);
        }

        .hari-badge.ok {
            background: var(--green-bg);
            color: var(--green);
        }

        /* ── PROGRESS ── */
        .progress-row {
            padding: 8px 16px 6px;
            border-bottom: 1px solid var(--border);
        }

        .progress-label {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            font-weight: 700;
            color: #cbd5e1;
            margin-bottom: 6px;
        }

        .progress-bar-bg {
            height: 6px;
            background: var(--surface2);
            border-radius: 999px;
            overflow: hidden;
        }

        .progress-bar-fill {
            height: 100%;
            border-radius: 999px;
            background: linear-gradient(90deg, #3b82f6, #10b981);
            transition: width 0.5s ease;
        }

        /* ── TASKS ── */
        .tasks-scroll {
            flex: 1;
            overflow-y: auto;
            padding: 8px 12px;
        }

        .tasks-scroll::-webkit-scrollbar {
            width: 4px;
        }

        .tasks-scroll::-webkit-scrollbar-thumb {
            background: var(--surface2);
            border-radius: 4px;
        }

        .task-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 8px 10px;
            border-radius: 8px;
            margin-bottom: 6px;
        }

        .task-row.done {
            background: var(--green-bg);
        }

        .task-row.in-progress {
            background: var(--yellow-bg);
        }

        .task-row.pending {
            background: var(--surface2);
        }

        .task-left {
            flex: 1;
            min-width: 0;
        }

        .task-stage {
            font-size: 15px;
            font-weight: 800;
            color: #fff;
            line-height: 1.25;
            word-break: break-word;
        }

        .task-worker {
            font-size: 12px;
            font-weight: 600;
            color: #cbd5e1;
            margin-top: 2px;
        }

        .task-sizes {
            font-size: 12px;
            font-weight: 700;
            color: #e2e8f0;
            margin-top: 2px;
        }

        .task-status {
            font-size: 13px;
            font-weight: 800;
            text-align: right;
            white-space: nowrap;
        }

        .task-status.done {
            color: var(--green);
        }

        .task-status.in-progress {
            color: var(--yellow);
        }

        .task-status.pending {
            color: var(--muted);
        }

        /* ── EMPTY STATE ── */
        .empty-main {
            grid-column: 1 / -1;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            gap: 12px;
            color: var(--muted);
            min-height: 300px;
        }

        .empty-icon {
            font-size: 64px;
        }

        .empty-text {
            font-size: 20px;
            font-weight: 600;
        }

        .empty-sub {
            font-size: 14px;
        }

        /* ── REFRESH INDICATOR ── */
        .refresh-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--green);
            animation: blink 2s infinite;
        }

        @keyframes blink {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: 0.2;
            }
        }
    </style>
</head>

<body>

    {{-- ─ HEADER ─ --}}
    <div class="header">
        <div>
            <div class="header-shop">{{ $shop->name }}</div>
            <div style="display:flex;align-items:center;gap:8px;margin-top:2px;">
                <div class="refresh-dot"></div>
                <span style="font-size:11px;color:#64748b;">Auto-refresh setiap 30 detik</span>
            </div>
        </div>

        <div class="header-title">🏭 Monitor Tahapan Produksi</div>

        <div class="header-clock">
            <div class="clock-time" id="clock">--:--</div>
            <div class="clock-date" id="clockdate">--</div>
        </div>
    </div>

    {{-- ─ BODY ─ --}}
    <div class="body">

        {{-- ─ MAIN: In Progress (3 Kolom) ─ --}}
        <div class="main">
            @forelse($inProgress as $item)
                @php
                    $order = $item->order;
                    $groupItemIds = $item->getItemsInGroup()->pluck('id');
                    $wo = \App\Models\WorkOrder::withoutGlobalScopes()
                        ->whereIn('order_item_id', $groupItemIds)
                        ->where('wo_number', 'not like', '%-R%')
                        ->first();

                    $rawTasks = $item->productionTasks;
                    
                    // Pisahkan task QC_PERSIAPAN dengan task tukang biasa
                    $qcPrepTask = $rawTasks->firstWhere('stage_name', 'QC_PERSIAPAN');
                    $workerTasks = $rawTasks->filter(fn($t) => $t->stage_name !== 'QC_PERSIAPAN')->sortBy('id');

                    // Gabungkan dengan urutan: QC_PERSIAPAN dulu (jika ada), baru tahapan tukang
                    $displayTasks = collect();
                    if ($qcPrepTask) {
                        $displayTasks->push($qcPrepTask);
                    }
                    foreach ($workerTasks as $wt) {
                        $displayTasks->push($wt);
                    }

                    // Cek apakah ada QC Akhir pada WorkOrder
                    $hasQcAkhir = $wo ? ($wo->has_qc_selesai ?? true) : false;
                    $qcWorkerName = $wo && $wo->qc_worker_id ? (\App\Models\Worker::find($wo->qc_worker_id)?->name ?? 'Petugas QC') : 'Petugas QC';

                    // Hitung total unit dan total progress
                    $totalSteps = $displayTasks->count() + ($hasQcAkhir ? 1 : 0);
                    $doneSteps = $displayTasks->where('status', 'done')->count();
                    
                    // Tentukan status QC Akhir
                    $qcAkhirStatus = 'pending'; // default: Belum Mulai
                    $qcAkhirLabel = 'Belum Mulai';
                    $qcAkhirRowClass = 'pending';

                    if ($hasQcAkhir && $wo) {
                        if ($wo->status === \App\Models\WorkOrder::STATUS_COMPLETED || $wo->completed_at !== null) {
                            $qcAkhirStatus = 'done';
                            $qcAkhirLabel = '✅ Selesai';
                            $qcAkhirRowClass = 'done';
                            $doneSteps++;
                        } elseif ($wo->status === \App\Models\WorkOrder::STATUS_QC_AKHIR) {
                            $qcAkhirStatus = 'in_progress';
                            $qcAkhirLabel = '🔍 Menunggu Verifikasi QC';
                            $qcAkhirRowClass = 'in-progress';
                        } elseif ($workerTasks->isNotEmpty() && $workerTasks->every(fn($t) => $t->status === 'done')) {
                            // Semua tukang sudah selesai tapi WO belum di-advance/approve
                            $qcAkhirStatus = 'in_progress';
                            $qcAkhirLabel = '🔍 Menunggu Verifikasi QC';
                            $qcAkhirRowClass = 'in-progress';
                        }
                    }

                    $progress = $totalSteps > 0 ? round(($doneSteps / $totalSteps) * 100) : 0;
                    $daysLeft = now()->startOfDay()->diffInDays($order->deadline, false);
                    $dlClass = $daysLeft < 0 ? 'urgent' : ($daysLeft <= 1 ? 'urgent' : ($daysLeft <= 3 ? 'soon' : 'ok'));

                    $details = $item->size_and_request_details ?? [];
                    $sizeParts = [];
                    if (!empty($details['varian_ukuran'])) {
                        foreach ($details['varian_ukuran'] as $v) {
                            $sz = strtoupper($v['ukuran'] ?? '');
                            $q = (int) ($v['qty'] ?? 0);
                            if ($sz && $q > 0)
                                $sizeParts[] = "{$sz}:{$q}";
                        }
                    }
                    $sizeStr = implode(', ', $sizeParts);

                    // Bahan
                    $bahan = $details['bahan'] ?? null;

                    // Sablon/bordir
                    $sablonParts = [];
                    if (!empty($details['sablon_jenis']))
                        $sablonParts[] = $details['sablon_jenis'];
                    if (!empty($details['sablon_lokasi']))
                        $sablonParts[] = $details['sablon_lokasi'];
                    if (!empty($details['sablon_bordir'])) {
                        foreach ($details['sablon_bordir'] as $sb) {
                            $j = $sb['jenis'] ?? '';
                            $l = $sb['lokasi'] ?? '';
                            if ($j || $l)
                                $sablonParts[] = trim("$j $l");
                        }
                    }
                    $sablonStr = implode(', ', $sablonParts);

                    // H-X label
                    if ($daysLeft < 0)
                        $hLabel = 'TERLAMBAT';
                    elseif ($daysLeft == 0)
                        $hLabel = 'HARI INI';
                    else
                        $hLabel = 'H-' . $daysLeft;
                @endphp

                <div class="item-card {{ $order->is_express ? 'is-express' : '' }}">

                    {{-- Header --}}
                    <div class="item-card-header">
                        <div class="item-card-meta">
                            <span class="customer-name">{{ $order->customer->name ?? 'Tanpa Nama' }} &bull;
                                {{ $order->order_number }}</span>
                            @if($order->is_express)
                                <span class="express-banner">⚡ EXPRESS</span>
                            @endif
                        </div>
                        <div class="item-qty">{{ $item->quantity }}x</div>
                        <div class="item-name">{{ $item->product_name }}</div>
                        <div class="item-info">
                            @if($bahan)
                                <span class="info-badge">{{ $bahan }}</span>
                            @endif
                        </div>
                        @if($sizeStr)
                            <div class="item-sizes">📐 {{ $sizeStr }}</div>
                        @endif
                        @if($sablonStr)
                            <div class="item-sablon">✏️ {{ $sablonStr }}</div>
                        @endif
                    </div>

                    {{-- Deadline --}}
                    <div class="deadline-row">
                        <span class="deadline-text {{ $dlClass }}">
                            Deadline {{ $order->deadline->translatedFormat('d M Y') }}
                        </span>
                        <span class="hari-badge {{ $dlClass }}">{{ $hLabel }}</span>
                    </div>

                    {{-- Progress --}}
                    <div class="progress-row">
                        <div class="progress-label">
                            <span>Progress Tahapan</span>
                            <span>{{ $doneSteps }}/{{ $totalSteps }} selesai — {{ $progress }}%</span>
                        </div>
                        <div class="progress-bar-bg">
                            <div class="progress-bar-fill" style="width:{{ $progress }}%"></div>
                        </div>
                    </div>

                    {{-- Task List --}}
                    <div class="tasks-scroll">
                        @foreach($displayTasks as $task)
                            @php
                                $isQcPrep = ($task->stage_name === 'QC_PERSIAPAN');
                                $displayStageName = $isQcPrep ? 'QC Persiapan (Awal)' : str_replace('_', ' ', $task->stage_name);
                                
                                $rowClass = match ($task->status) { 'done' => 'done', 'in_progress' => 'in-progress', default => 'pending'};
                                $statusLabel = match ($task->status) { 'done' => '✅ Selesai', 'in_progress' => '🔨 Proses', default => 'Belum Mulai'};

                                // Size quantities untuk task ini
                                $sizeQtyParts = [];
                                if (!empty($task->size_quantities) && is_array($task->size_quantities)) {
                                    foreach ($task->size_quantities as $sz => $q) {
                                        if (str_starts_with($sz, '_')) continue;
                                        if ((int) $q > 0)
                                            $sizeQtyParts[] = strtoupper($sz) . ':' . $q;
                                    }
                                }
                                $taskSizes = implode(', ', $sizeQtyParts);
                            @endphp
                            <div class="task-row {{ $rowClass }}">
                                <div class="task-left">
                                    <div class="task-stage">
                                        {{ $displayStageName }}
                                        <span style="font-weight:600;font-size:16px;color:#d1dbea;margin-left:6px;">&mdash;
                                            {{ $task->assignedTo?->name ?? '—' }}</span>
                                    </div>
                                    @if($taskSizes)
                                        <div class="task-sizes">{{ $taskSizes }}</div>
                                    @endif
                                </div>
                                <div class="task-status {{ $rowClass }}">{{ $statusLabel }}</div>
                            </div>
                        @endforeach

                        {{-- Baris Khusus: QC Akhir --}}
                        @if($hasQcAkhir)
                            <div class="task-row {{ $qcAkhirRowClass }}">
                                <div class="task-left">
                                    <div class="task-stage">
                                        QC Akhir (Verifikasi Final)
                                        <span style="font-weight:600;font-size:16px;color:#d1dbea;margin-left:6px;">&mdash;
                                            {{ $qcWorkerName }}</span>
                                    </div>
                                </div>
                                <div class="task-status {{ $qcAkhirRowClass }}">{{ $qcAkhirLabel }}</div>
                            </div>
                        @endif
                    </div>

                </div>
            @empty
                <div class="empty-main">
                    <div class="empty-icon">🎉</div>
                    <div class="empty-text">Tidak ada produksi aktif</div>
                    <div class="empty-sub">Semua item sudah selesai atau belum ada yang dimulai</div>
                </div>
            @endforelse
        </div>

        {{-- ─ BOTTOM TICKER: ANTRIAN PRODUKSI ─ --}}
        <div class="queue-ticker">
            <div class="queue-ticker-label">
                <span>⏳ Antrian</span>
                <span style="opacity:0.85;">({{ $antrian->count() }})</span>
            </div>
            <div class="queue-ticker-track">
                @if($antrian->isNotEmpty())
                    <div class="queue-ticker-items">
                        {{-- Duplikat 2x loop agar scrolling marquee mulus tanpa jeda --}}
                        @for($repeat = 0; $repeat < 2; $repeat++)
                            @foreach($antrian as $item)
                                @php
                                    $order = $item->order;
                                    $daysLeft = now()->startOfDay()->diffInDays($order->deadline, false);
                                    $dlClass = $daysLeft < 0 ? 'urgent' : ($daysLeft <= 1 ? 'urgent' : ($daysLeft <= 3 ? 'soon' : 'ok'));

                                    $cat = match ($item->production_category) {
                                        'custom' => 'Produksi',
                                        'non_produksi' => 'Non-Prod',
                                        'jasa' => 'Jasa',
                                        default => 'Produksi',
                                    };

                                    $details = $item->size_and_request_details ?? [];
                                    $sizeParts = [];
                                    if (!empty($details['varian_ukuran'])) {
                                        foreach ($details['varian_ukuran'] as $v) {
                                            $sz = strtoupper($v['ukuran'] ?? '');
                                            $q = (int) ($v['qty'] ?? 0);
                                            if ($sz && $q > 0)
                                                $sizeParts[] = "{$sz}:{$q}";
                                        }
                                    }
                                    $sizeStr = implode(', ', $sizeParts);
                                @endphp
                                <div class="ticker-card {{ $order->is_express ? 'is-express' : '' }}">
                                    <span class="badge-cat">{{ $cat }}</span>
                                    @if($order->is_express)
                                        <span class="badge-express">⚡ EXPRESS</span>
                                    @endif
                                    <span class="order-num">{{ $order->order_number }}</span>
                                    <span class="ticker-prod-name">{{ $item->quantity }}x {{ $item->product_name }}</span>
                                    @if($sizeStr)
                                        <span class="ticker-details">{{ $sizeStr }}</span>
                                    @endif
                                    <span class="ticker-deadline {{ $dlClass }}">
                                        📅 {{ $order->deadline->translatedFormat('d M') }}
                                        @if($daysLeft < 0) (LEWAT!)
                                        @elseif($daysLeft == 0) (HARI INI)
                                        @elseif($daysLeft <= 1) (H-{{ $daysLeft }})
                                        @endif
                                    </span>
                                </div>
                            @endforeach
                        @endfor
                    </div>
                @else
                    <div style="color:#64748b;font-size:13px;font-weight:600;padding-left:12px;">
                        Tidak ada antrian pesanan yang menunggu saat ini ✨
                    </div>
                @endif
            </div>
        </div>

    </div>

    <script>
        // Live clock
        function updateClock() {
            const now = new Date();
            const h = String(now.getHours()).padStart(2, '0');
            const m = String(now.getMinutes()).padStart(2, '0');
            document.getElementById('clock').textContent = h + ':' + m + ' WIB';

            const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
            const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
            document.getElementById('clockdate').textContent =
                days[now.getDay()] + ', ' + now.getDate() + ' ' + months[now.getMonth()] + ' ' + now.getFullYear();
        }
        updateClock();
        setInterval(updateClock, 1000);

        // Auto-scroll tasks inside each card
        function initAutoScroll() {
            const containers = document.querySelectorAll('.tasks-scroll');
            containers.forEach(el => {
                let step = 1;
                let isPaused = false;

                setInterval(() => {
                    if (isPaused) return;

                    // Only scroll if content is longer than container
                    if (el.scrollHeight > el.clientHeight) {
                        el.scrollTop += step;

                        // If reached bottom
                        if (el.scrollTop + el.clientHeight >= el.scrollHeight - 1) {
                            isPaused = true;
                            setTimeout(() => {
                                el.scrollTo({
                                    top: 0,
                                    behavior: 'smooth'
                                });
                                setTimeout(() => {
                                    isPaused = false;
                                }, 3000); // Pause at top
                            }, 3000); // Pause at bottom
                        }
                    }
                }, 50);
            });
        }
        initAutoScroll();

        // Horizontal Auto-scroll for Main columns if > 3 items
        function initMainAutoScroll() {
            const main = document.querySelector('.main');
            if (!main) return;

            let scrollTimer;
            const scrollNext = () => {
                if (main.scrollWidth > main.clientWidth + 20) {
                    const cardWidth = main.querySelector('.item-card')?.offsetWidth || (main.clientWidth / 3);
                    const gap = 16;
                    const shift = (cardWidth + gap) * 3; // Shift 3 columns at once

                    if (main.scrollLeft + main.clientWidth >= main.scrollWidth - 10) {
                        main.scrollTo({ left: 0, behavior: 'smooth' });
                    } else {
                        main.scrollBy({ left: shift, behavior: 'smooth' });
                    }
                }
            };

            // Switch page every 15 seconds if > 3 items
            setInterval(scrollNext, 15000);
        }
        initMainAutoScroll();
    </script>
</body>

</html>