<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="30">
    <title>Monitor Produksi — {{ $shop->name }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --bg: #0f1117;
            --surface: #1a1d27;
            --surface2: #22263a;
            --border: rgba(255,255,255,0.07);
            --text: #f1f5f9;
            --muted: #94a3b8;
            --green: #10b981;
            --green-bg: rgba(16,185,129,0.15);
            --yellow: #f59e0b;
            --yellow-bg: rgba(245,158,11,0.15);
            --red: #ef4444;
            --red-bg: rgba(239,68,68,0.12);
            --blue: #3b82f6;
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
            flex: 1;
            overflow: hidden;
            gap: 0;
        }

        /* ── SIDEBAR ── */
        .sidebar {
            width: 280px;
            flex-shrink: 0;
            background: var(--surface);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .sidebar-header {
            padding: 16px 18px 12px;
            border-bottom: 1px solid var(--border);
        }

        .sidebar-header h2 {
            font-size: 11px;
            font-weight: 700;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.1em;
        }

        .sidebar-count {
            display: inline-flex;
            align-items: center;
            background: var(--yellow-bg);
            color: var(--yellow);
            font-size: 11px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 999px;
            margin-top: 6px;
        }

        .sidebar-scroll {
            flex: 1;
            overflow-y: auto;
            padding: 12px;
        }

        .sidebar-scroll::-webkit-scrollbar { width: 4px; }
        .sidebar-scroll::-webkit-scrollbar-track { background: transparent; }
        .sidebar-scroll::-webkit-scrollbar-thumb { background: var(--surface2); border-radius: 4px; }

        .queue-card {
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 12px 14px;
            margin-bottom: 10px;
            position: relative;
        }

        .queue-card.is-express {
            border-color: var(--red);
            border-width: 1.5px;
        }

        .queue-card-meta {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 6px;
        }

        .badge-cat {
            font-size: 10px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 999px;
            background: rgba(124,58,237,0.2);
            color: #a78bfa;
        }

        .badge-express {
            font-size: 10px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 999px;
            background: var(--red-bg);
            color: var(--red);
            animation: pulse 1.5s infinite;
        }

        .order-num {
            font-size: 10px;
            color: var(--muted);
            margin-left: auto;
        }

        .queue-product {
            font-size: 15px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 4px;
            line-height: 1.3;
        }

        .queue-details {
            font-size: 11px;
            color: var(--muted);
            margin-bottom: 6px;
        }

        .queue-deadline {
            font-size: 12px;
            font-weight: 700;
        }

        .queue-deadline.urgent { color: var(--red); }
        .queue-deadline.soon { color: var(--yellow); }
        .queue-deadline.ok { color: var(--green); }

        /* ── MAIN AREA ── */
        .main {
            flex: 1;
            overflow-x: auto;
            overflow-y: hidden;
            display: flex;
            gap: 16px;
            padding: 16px;
        }

        .main::-webkit-scrollbar { height: 6px; }
        .main::-webkit-scrollbar-track { background: transparent; }
        .main::-webkit-scrollbar-thumb { background: var(--surface2); border-radius: 4px; }

        /* ── ITEM CARD ── */
        .item-card {
            width: 340px;
            flex-shrink: 0;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .item-card.is-express {
            border-color: var(--red);
            border-width: 2px;
            box-shadow: 0 0 20px rgba(239,68,68,0.15);
        }

        .item-card-header {
            padding: 14px 16px 10px;
            border-bottom: 1px solid var(--border);
        }

        .item-card-meta {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 8px;
        }

        .customer-name {
            font-size: 11px;
            color: var(--muted);
        }

        .express-banner {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: var(--red);
            color: #fff;
            font-size: 11px;
            font-weight: 800;
            padding: 3px 10px;
            border-radius: 999px;
            letter-spacing: 0.05em;
            animation: pulse 1.5s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.75; }
        }

        .item-qty {
            font-size: 13px;
            font-weight: 700;
            color: var(--muted);
        }

        .item-name {
            font-size: 26px;
            font-weight: 800;
            color: var(--text);
            letter-spacing: -0.03em;
            line-height: 1.15;
            margin-bottom: 8px;
        }

        .item-info {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
            margin-bottom: 6px;
        }

        .info-badge {
            font-size: 11px;
            font-weight: 600;
            padding: 2px 10px;
            border-radius: 6px;
            background: rgba(59,130,246,0.15);
            color: #93c5fd;
        }

        .item-sizes {
            font-size: 12px;
            color: var(--muted);
            margin-bottom: 4px;
        }

        .item-sablon {
            font-size: 12px;
            color: var(--muted);
            font-style: italic;
        }

        /* ── DEADLINE ── */
        .deadline-row {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 16px;
            border-bottom: 1px solid var(--border);
        }

        .deadline-text {
            font-size: 13px;
            font-weight: 700;
        }
        .deadline-text.urgent { color: var(--red); }
        .deadline-text.soon { color: var(--yellow); }
        .deadline-text.ok { color: var(--green); }

        .hari-badge {
            font-size: 12px;
            font-weight: 800;
            padding: 3px 10px;
            border-radius: 6px;
        }

        .hari-badge.urgent { background: var(--red-bg); color: var(--red); }
        .hari-badge.soon { background: var(--yellow-bg); color: var(--yellow); }
        .hari-badge.ok { background: var(--green-bg); color: var(--green); }

        /* ── PROGRESS ── */
        .progress-row {
            padding: 10px 16px 8px;
            border-bottom: 1px solid var(--border);
        }

        .progress-label {
            display: flex;
            justify-content: space-between;
            font-size: 11px;
            color: var(--muted);
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
            padding: 8px;
        }

        .tasks-scroll::-webkit-scrollbar { width: 4px; }
        .tasks-scroll::-webkit-scrollbar-thumb { background: var(--surface2); border-radius: 4px; }

        .task-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 12px;
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
        }

        .task-stage {
            font-size: 14px;
            font-weight: 700;
            color: var(--text);
            line-height: 1;
        }

        .task-worker {
            font-size: 12px;
            color: var(--muted);
            margin-top: 3px;
        }

        .task-sizes {
            font-size: 11px;
            color: var(--muted);
        }

        .task-status {
            font-size: 12px;
            font-weight: 700;
            text-align: right;
            white-space: nowrap;
        }

        .task-status.done { color: var(--green); }
        .task-status.in-progress { color: var(--yellow); }
        .task-status.pending { color: var(--muted); }

        /* ── EMPTY STATE ── */
        .empty-main {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            gap: 12px;
            color: var(--muted);
        }

        .empty-icon { font-size: 64px; }
        .empty-text { font-size: 20px; font-weight: 600; }
        .empty-sub { font-size: 14px; }

        /* ── REFRESH INDICATOR ── */
        .refresh-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--green);
            animation: blink 2s infinite;
        }

        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.2; }
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

        {{-- ─ SIDEBAR: Antrian ─ --}}
        <div class="sidebar">
            <div class="sidebar-header">
                <h2>⏳ Antrian Produksi</h2>
                <div class="sidebar-count">{{ $antrian->count() }} item menunggu</div>
            </div>
            <div class="sidebar-scroll">
                @forelse($antrian as $item)
                    @php
                        $order    = $item->order;
                        $daysLeft = now()->startOfDay()->diffInDays($order->deadline, false);
                        $dlClass  = $daysLeft <= 1 ? 'urgent' : ($daysLeft <= 3 ? 'soon' : 'ok');

                        $cat = match($item->production_category) {
                            'custom'       => '🧵 Custom',
                            'non_produksi' => '📦 Non-Produksi',
                            'jasa'         => '🔧 Jasa',
                            default        => '🏭 Produksi',
                        };

                        $details = $item->size_and_request_details ?? [];
                        $sizeParts = [];
                        if (!empty($details['varian_ukuran'])) {
                            foreach ($details['varian_ukuran'] as $v) {
                                $sz = strtoupper($v['ukuran'] ?? '');
                                $q  = (int)($v['qty'] ?? 0);
                                if ($sz && $q > 0) $sizeParts[] = "{$sz}:{$q}";
                            }
                        }
                        $sizeStr = implode(', ', $sizeParts);
                    @endphp
                    <div class="queue-card {{ $order->is_express ? 'is-express' : '' }}">
                        <div class="queue-card-meta">
                            <span class="badge-cat">{{ $cat }}</span>
                            @if($order->is_express)
                                <span class="badge-express">⚡ EXPRESS</span>
                            @endif
                            <span class="order-num">{{ $order->order_number }}</span>
                        </div>
                        <div class="queue-product">{{ $item->quantity }}x {{ $item->product_name }}</div>
                        @if($sizeStr)
                            <div class="queue-details">{{ $sizeStr }}</div>
                        @endif
                        <div class="queue-deadline {{ $dlClass }}">
                            📅 Deadline {{ $order->deadline->translatedFormat('d M Y') }}
                            @if($daysLeft <= 0) — HARI INI!
                            @elseif($daysLeft <= 1) — H-{{ $daysLeft }}
                            @endif
                        </div>
                    </div>
                @empty
                    <div style="padding:24px;text-align:center;color:#475569;font-size:13px;">
                        Tidak ada item dalam antrian
                    </div>
                @endforelse
            </div>
        </div>

        {{-- ─ MAIN: In Progress ─ --}}
        <div class="main">
            @forelse($inProgress as $item)
                @php
                    $order    = $item->order;
                    $tasks    = $item->productionTasks->sortBy('id');
                    $total    = $tasks->count();
                    $done     = $tasks->where('status', 'done')->count();
                    $progress = $total > 0 ? round(($done / $total) * 100) : 0;
                    $daysLeft = now()->startOfDay()->diffInDays($order->deadline, false);
                    $dlClass  = $daysLeft < 0 ? 'urgent' : ($daysLeft <= 1 ? 'urgent' : ($daysLeft <= 3 ? 'soon' : 'ok'));

                    $details  = $item->size_and_request_details ?? [];
                    $sizeParts = [];
                    if (!empty($details['varian_ukuran'])) {
                        foreach ($details['varian_ukuran'] as $v) {
                            $sz = strtoupper($v['ukuran'] ?? '');
                            $q  = (int)($v['qty'] ?? 0);
                            if ($sz && $q > 0) $sizeParts[] = "{$sz}:{$q}";
                        }
                    }
                    $sizeStr = implode(', ', $sizeParts);

                    // Bahan
                    $bahan = $details['bahan'] ?? null;

                    // Sablon/bordir
                    $sablonParts = [];
                    if (!empty($details['sablon_jenis'])) $sablonParts[] = $details['sablon_jenis'];
                    if (!empty($details['sablon_lokasi'])) $sablonParts[] = $details['sablon_lokasi'];
                    if (!empty($details['sablon_bordir'])) {
                        foreach ($details['sablon_bordir'] as $sb) {
                            $j = $sb['jenis'] ?? '';
                            $l = $sb['lokasi'] ?? '';
                            if ($j || $l) $sablonParts[] = trim("$j $l");
                        }
                    }
                    $sablonStr = implode(', ', $sablonParts);

                    // H-X label
                    if ($daysLeft < 0) $hLabel = 'TERLAMBAT';
                    elseif ($daysLeft == 0) $hLabel = 'HARI INI';
                    else $hLabel = 'H-' . $daysLeft;
                @endphp

                <div class="item-card {{ $order->is_express ? 'is-express' : '' }}">

                    {{-- Header --}}
                    <div class="item-card-header">
                        <div class="item-card-meta">
                            <span class="customer-name">{{ $order->customer->name ?? 'Tanpa Nama' }} &bull; {{ $order->order_number }}</span>
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
                            <span>{{ $done }}/{{ $total }} selesai — {{ $progress }}%</span>
                        </div>
                        <div class="progress-bar-bg">
                            <div class="progress-bar-fill" style="width:{{ $progress }}%"></div>
                        </div>
                    </div>

                    {{-- Task List --}}
                    <div class="tasks-scroll">
                        @foreach($tasks as $task)
                            @php
                                $rowClass    = match($task->status) { 'done' => 'done', 'in_progress' => 'in-progress', default => 'pending' };
                                $statusLabel = match($task->status) { 'done' => '✅ Selesai', 'in_progress' => '🔨 Proses', default => 'Belum Mulai' };

                                // Size quantities untuk task ini
                                $sizeQtyParts = [];
                                if (!empty($task->size_quantities) && is_array($task->size_quantities)) {
                                    foreach ($task->size_quantities as $sz => $q) {
                                        if ((int)$q > 0) $sizeQtyParts[] = strtoupper($sz) . ':' . $q;
                                    }
                                }
                                $taskSizes = implode(', ', $sizeQtyParts);
                            @endphp
                            <div class="task-row {{ $rowClass }}">
                                <div class="task-left">
                                    <div class="task-stage">
                                        {{ $task->stage_name }}
                                        <span style="font-weight:500;font-size:12px;color:var(--muted);"> | {{ $task->assignedTo?->name ?? '—' }}</span>
                                    </div>
                                    @if($taskSizes)
                                        <div class="task-sizes">{{ $taskSizes }}</div>
                                    @endif
                                </div>
                                <div class="task-status {{ $rowClass }}">{{ $statusLabel }}</div>
                            </div>
                        @endforeach
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

    </div>

    <script>
        // Live clock
        function updateClock() {
            const now = new Date();
            const h   = String(now.getHours()).padStart(2,'0');
            const m   = String(now.getMinutes()).padStart(2,'0');
            document.getElementById('clock').textContent = h + ':' + m + ' WIB';

            const days  = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
            const months = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
            document.getElementById('clockdate').textContent =
                days[now.getDay()] + ', ' + now.getDate() + ' ' + months[now.getMonth()] + ' ' + now.getFullYear();
        }
        updateClock();
        setInterval(updateClock, 1000);
    </script>
</body>
</html>
