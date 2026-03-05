<x-filament-widgets::widget>
    @php
        $data = $this->getData();
        $rc = $data['revenueCost'];
        $ps = $data['productionStatus'];
        $hm = $data['deadlineHeatmap'];

        // ── Area-chart scaling ──────────────────────────────────────────────
        $maxVal = max(1, max(array_column($rc, 'omzet')), max(array_column($rc, 'biaya')));
        $padT = 22;   // top padding for value labels
        $padL = 15;   // left padding
        $padR = 20;   // right padding
        $plotW = 440; // actual plot area width
        $plotH = 130; // actual plot area height
        $padBot = 18; // bottom padding for month labels inside SVG
        $svgW = $padL + $plotW + $padR;
        $svgH = $padT + $plotH + $padBot;

        $omzetPoints = [];
        $biayaPoints = [];
        $stepX = count($rc) > 1 ? $plotW / (count($rc) - 1) : $plotW;

        foreach ($rc as $i => $m) {
            $x = $padL + $i * $stepX;
            $yO = $padT + $plotH - ($m['omzet'] / $maxVal) * $plotH;
            $yB = $padT + $plotH - ($m['biaya'] / $maxVal) * $plotH;
            $omzetPoints[] = round($x, 1) . ',' . round($yO, 1);
            $biayaPoints[] = round($x, 1) . ',' . round($yB, 1);
        }

        $baseline = $padT + $plotH; // y of the bottom axis
        $omzetLine = implode(' ', $omzetPoints);
        $biayaLine = implode(' ', $biayaPoints);
        $firstX = $padL;
        $lastX = round($padL + (count($rc) - 1) * $stepX, 1);
        $omzetArea = $firstX . ',' . $baseline . ' ' . $omzetLine . ' ' . $lastX . ',' . $baseline;
        $biayaArea = $firstX . ',' . $baseline . ' ' . $biayaLine . ' ' . $lastX . ',' . $baseline;

        // ── Donut chart ──────────────────────────────────────────────────────
        $circumference = 2 * M_PI * 54;
        $donutSegments = [];
        $runningOffset = 0;
        foreach ($ps['segments'] as $seg) {
            $dash = $ps['totalPcs'] > 0 ? ($seg['pcs'] / $ps['totalPcs']) * $circumference : 0;
            $donutSegments[] = [
                ...$seg,
                'dash' => round($dash, 2),
                'gap' => round($circumference - $dash, 2),
                'offset' => round(-$runningOffset, 2),
            ];
            $runningOffset += $dash;
        }

        // ── Rp formatter ─────────────────────────────────────────────────────
        $rpShort = function ($val) {
            if ($val >= 1_000_000_000)
                return number_format($val / 1_000_000_000, 1) . 'M';
            if ($val >= 1_000_000)
                return number_format($val / 1_000_000, 1) . 'Jt';
            if ($val >= 1_000)
                return number_format($val / 1_000, 0) . 'Rb';
            return number_format($val, 0);
        };
    @endphp

    <style>
        /* ═══ VISUAL INSIGHTS GRID — 3 Column ═══ */
        .vi-grid {
            display: grid;
            grid-template-columns: 5fr 3fr 3fr;
            gap: 20px;
            align-items: stretch;
        }

        @media (max-width: 1100px) {
            .vi-grid {
                grid-template-columns: 1fr 1fr;
            }

            .vi-grid> :first-child {
                grid-column: 1 / -1;
            }
        }

        @media (max-width: 640px) {
            .vi-grid {
                grid-template-columns: 1fr;
            }

            .vi-grid> :first-child {
                grid-column: auto;
            }
        }

        .vi-card {
            background: #fff;
            border-radius: 18px;
            border: 1px solid #ebebeb;
            padding: 20px;
            box-shadow: 0 1px 4px rgba(0, 0, 0, .04);
            position: relative;
            overflow: hidden;
            transition: box-shadow .2s;
            display: flex;
            flex-direction: column;
        }

        .dark .vi-card {
            background: rgba(30, 20, 50, .7);
            border-color: rgba(255, 255, 255, .07);
        }

        .vi-card:hover {
            box-shadow: 0 6px 20px rgba(127, 0, 255, .10);
        }

        .vi-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #7F00FF, #bf80ff);
            border-radius: 18px 18px 0 0;
        }

        .vi-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 18px;
        }

        .vi-title {
            font-size: 11px;
            font-weight: 700;
            color: #888;
            text-transform: uppercase;
            letter-spacing: .06em;
            margin-bottom: 0;
        }

        .dark .vi-title {
            color: #9ca3af;
        }

        .vi-title-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: #f3eeff;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .dark .vi-title-icon {
            background: rgba(127, 0, 255, .18);
        }

        .vi-title-icon svg {
            width: 18px;
            height: 18px;
            stroke: #7F00FF;
        }

        /* ─── AREA CHART ─── */
        .vi-card-body {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .vi-chart-wrap {
            width: 100%;
            overflow: hidden;
        }

        .vi-chart-wrap svg {
            width: 100%;
            height: auto;
            display: block;
        }

        .vi-chart-legend {
            display: flex;
            gap: 16px;
            margin-top: 6px;
        }

        .vi-legend-item {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 11px;
            color: #6b7280;
            font-weight: 500;
        }

        .vi-legend-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .vi-chart-labels {
            display: flex;
            justify-content: space-between;
            margin-top: -2px;
            padding: 0
                {{ $padL }}
                px 0
                {{ $padL }}
                px;
        }

        .vi-chart-labels span {
            font-size: 10px;
            color: #9ca3af;
            font-weight: 600;
            text-transform: uppercase;
        }

        /* ─── DONUT ─── */
        .vi-donut-wrap {
            display: flex;
            flex-direction: column;
            align-items: center;
            flex: 1;
            justify-content: center;
        }

        .vi-donut-svg-wrap {
            position: relative;
            width: 140px;
            height: 140px;
        }

        .vi-donut-svg-wrap svg {
            transform: rotate(-90deg);
            display: block;
        }

        .vi-donut-center {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
            width: 80%;
            pointer-events: none;
        }

        .vi-donut-center-val {
            font-size: 20px;
            font-weight: 800;
            color: #111;
            line-height: 1;
            display: block;
        }

        .dark .vi-donut-center-val {
            color: #f3f4f6;
        }

        .vi-donut-center-lbl {
            font-size: 10px;
            color: #94a3b8;
            font-weight: 700;
            text-transform: uppercase;
            margin-top: 2px;
            display: block;
        }

        .vi-donut-legend {
            display: flex;
            flex-wrap: wrap;
            gap: 4px 10px;
            margin-top: 10px;
            justify-content: center;
        }

        /* ─── HEATMAP CALENDAR ─── */
        .vi-cal-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 3px;
            flex: 1;
        }

        .vi-cal-dow {
            font-size: 9px;
            font-weight: 700;
            color: #9ca3af;
            text-align: center;
            padding-bottom: 2px;
            text-transform: uppercase;
        }

        .vi-cal-cell {
            aspect-ratio: 1;
            border-radius: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            font-weight: 600;
            color: #6b7280;
            position: relative;
            transition: transform .15s;
        }

        .vi-cal-cell:hover {
            transform: scale(1.15);
        }

        .vi-cal-cell.empty {
            background: transparent;
        }

        .vi-cal-cell.none {
            background: #f8fafc;
            color: #94a3b8;
        }

        .vi-cal-cell.low {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .vi-cal-cell.med {
            background: #fef3c7;
            color: #92400e;
        }

        .vi-cal-cell.high {
            background: #fee2e2;
            color: #dc2626;
            font-weight: 800;
        }

        .dark .vi-cal-cell.none {
            background: rgba(148, 163, 184, .1);
            color: #94a3b8;
        }

        .dark .vi-cal-cell.low {
            background: rgba(59, 130, 246, .2);
            color: #60a5fa;
        }

        .dark .vi-cal-cell.med {
            background: rgba(234, 179, 8, .2);
            color: #fbbf24;
        }

        .dark .vi-cal-cell.high {
            background: rgba(239, 68, 68, .2);
            color: #f87171;
        }

        .vi-cal-cell.today {
            box-shadow: 0 0 0 2px #7F00FF;
        }

        .vi-cal-legend {
            display: flex;
            gap: 8px;
            justify-content: center;
            margin-top: 8px;
            flex-wrap: wrap;
        }

        .vi-cal-legend-item {
            display: flex;
            align-items: center;
            gap: 3px;
            font-size: 9px;
            color: #9ca3af;
            font-weight: 600;
        }

        .vi-cal-legend-dot {
            width: 8px;
            height: 8px;
            border-radius: 3px;
            flex-shrink: 0;
        }

        /* ─── CALENDAR NAV ─── */
        .vi-cal-nav {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-bottom: 8px;
        }

        .vi-cal-nav-btn {
            width: 24px;
            height: 24px;
            border-radius: 6px;
            border: 1px solid #e5e7eb;
            background: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all .15s;
            color: #6b7280;
            flex-shrink: 0;
        }

        .vi-cal-nav-btn:hover {
            background: #f3eeff;
            border-color: #c4b5fd;
            color: #7F00FF;
        }

        .dark .vi-cal-nav-btn {
            background: rgba(255, 255, 255, .05);
            border-color: rgba(255, 255, 255, .1);
            color: #9ca3af;
        }

        .dark .vi-cal-nav-btn:hover {
            background: rgba(127, 0, 255, .2);
            border-color: rgba(127, 0, 255, .4);
            color: #bf80ff;
        }

        .vi-cal-nav-btn svg {
            width: 12px;
            height: 12px;
        }

        .vi-cal-nav-label {
            font-size: 12px;
            font-weight: 700;
            color: #222;
            min-width: 100px;
            text-align: center;
            cursor: pointer;
            transition: color .15s;
        }

        .vi-cal-nav-label:hover {
            color: #7F00FF;
        }

        .dark .vi-cal-nav-label {
            color: #e5e7eb;
        }

        .dark .vi-cal-nav-label:hover {
            color: #bf80ff;
        }

        .vi-cal-today-btn {
            font-size: 9px;
            font-weight: 600;
            color: #7F00FF;
            background: #f3eeff;
            border: 1px solid #e9d5ff;
            border-radius: 6px;
            padding: 2px 6px;
            cursor: pointer;
            transition: all .15s;
        }

        .vi-cal-today-btn:hover {
            background: #7F00FF;
            color: #fff;
        }

        .dark .vi-cal-today-btn {
            background: rgba(127, 0, 255, .15);
            border-color: rgba(127, 0, 255, .3);
            color: #bf80ff;
        }

        /* ─── TOOLTIP ─── */
        .vi-cal-cell[data-tip]::after {
            content: attr(data-tip);
            position: absolute;
            bottom: calc(100% + 4px);
            left: 50%;
            transform: translateX(-50%);
            background: #1e1b4b;
            color: #fff;
            font-size: 10px;
            padding: 3px 8px;
            border-radius: 4px;
            white-space: nowrap;
            opacity: 0;
            pointer-events: none;
            transition: opacity .15s;
            z-index: 10;
        }

        .vi-cal-cell[data-tip]:hover::after {
            opacity: 1;
        }
    </style>

    <div class="vi-grid">

        {{-- ═══ COL 1: Revenue vs Cost Area Chart ═══ --}}
        <div class="vi-card">
            <div class="vi-header">
                <span class="vi-title">Tren Omzet vs Biaya</span>
                <div class="vi-title-icon">
                    <svg fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M2.25 18 9 11.25l4.306 4.306a11.95 11.95 0 0 1 5.814-5.518l2.74-1.22m0 0-5.94-2.28m5.94 2.28-2.28 5.941" />
                    </svg>
                </div>
            </div>

            <div class="vi-card-body">
                <div class="vi-chart-wrap">
                    <svg viewBox="0 0 {{ $svgW }} {{ $svgH }}" preserveAspectRatio="xMidYMid meet">
                        <defs>
                            <linearGradient id="omzetGrad" x1="0%" y1="0%" x2="0%" y2="100%">
                                <stop offset="0%" stop-color="#7F00FF" stop-opacity="0.35" />
                                <stop offset="100%" stop-color="#7F00FF" stop-opacity="0.03" />
                            </linearGradient>
                            <linearGradient id="biayaGrad" x1="0%" y1="0%" x2="0%" y2="100%">
                                <stop offset="0%" stop-color="#94a3b8" stop-opacity="0.25" />
                                <stop offset="100%" stop-color="#94a3b8" stop-opacity="0.02" />
                            </linearGradient>
                        </defs>

                        {{-- Grid lines --}}
                        @for ($g = 0; $g <= 4; $g++)
                            @php $gy = $padT + ($plotH / 4) * $g; @endphp
                            <line x1="{{ $padL }}" y1="{{ $gy }}" x2="{{ $padL + $plotW }}" y2="{{ $gy }}" stroke="#f1f5f9"
                                stroke-width="1" />
                        @endfor

                        {{-- Biaya area --}}
                        <polygon points="{{ $biayaArea }}" fill="url(#biayaGrad)" />
                        <polyline points="{{ $biayaLine }}" fill="none" stroke="#94a3b8" stroke-width="2.5"
                            stroke-linecap="round" stroke-linejoin="round" />

                        {{-- Omzet area (on top) --}}
                        <polygon points="{{ $omzetArea }}" fill="url(#omzetGrad)" />
                        <polyline points="{{ $omzetLine }}" fill="none" stroke="#7F00FF" stroke-width="2.5"
                            stroke-linecap="round" stroke-linejoin="round" />

                        {{-- Data points + value labels --}}
                        @foreach ($rc as $i => $m)
                            @php
                                $x = $padL + $i * $stepX;
                                $yO = $padT + $plotH - ($m['omzet'] / $maxVal) * $plotH;
                                $yB = $padT + $plotH - ($m['biaya'] / $maxVal) * $plotH;
                            @endphp
                            {{-- Omzet dot --}}
                            <circle cx="{{ round($x, 1) }}" cy="{{ round($yO, 1) }}" r="4" fill="#7F00FF" stroke="#fff"
                                stroke-width="2">
                                <title>{{ $m['label'] }}: Rp {{ number_format($m['omzet'], 0, ',', '.') }}</title>
                            </circle>
                            {{-- Omzet label --}}
                            <text x="{{ round($x, 1) }}" y="{{ round(max($yO - 10, 8), 1) }}" text-anchor="middle"
                                fill="#7F00FF" font-size="10" font-weight="700">
                                {{ $rpShort($m['omzet']) }}
                            </text>
                            {{-- Biaya dot --}}
                            <circle cx="{{ round($x, 1) }}" cy="{{ round($yB, 1) }}" r="4" fill="#94a3b8" stroke="#fff"
                                stroke-width="2">
                                <title>{{ $m['label'] }}: Rp {{ number_format($m['biaya'], 0, ',', '.') }}</title>
                            </circle>
                        @endforeach

                        {{-- Month labels inside SVG --}}
                        @foreach ($rc as $i => $m)
                            @php $lx = $padL + $i * $stepX; @endphp
                            <text x="{{ round($lx, 1) }}" y="{{ $baseline + 14 }}" text-anchor="middle" fill="#9ca3af"
                                font-size="10" font-weight="600" style="text-transform:uppercase">{{ $m['label'] }}</text>
                        @endforeach
                    </svg>
                </div>

                {{-- Legend --}}
                <div class="vi-chart-legend">
                    <div class="vi-legend-item">
                        <div class="vi-legend-dot" style="background:#7F00FF"></div>
                        <span>Omzet (Revenue)</span>
                    </div>
                    <div class="vi-legend-item">
                        <div class="vi-legend-dot" style="background:#94a3b8"></div>
                        <span>Biaya (Upah + Modal)</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="vi-card">
            <div class="vi-header">
                <span class="vi-title">Ringkasan Pesanan</span>
                <div class="vi-title-icon">
                    <svg fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M15.75 10.5V6a3.75 3.75 0 1 0-7.5 0v4.5m11.356-1.993 1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 0 1-1.12-1.243l1.264-12A1.125 1.125 0 0 1 5.513 7.5h12.974c.576 0 1.059.435 1.119 1.007ZM8.625 10.5a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm7.5 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                    </svg>
                </div>
            </div>

            <div class="vi-donut-wrap">
                <div class="vi-donut-svg-wrap">
                    <svg viewBox="0 0 140 140" width="140" height="140">
                        {{-- Background track --}}
                        <circle cx="70" cy="70" r="54" fill="none" stroke="#f1f5f9" stroke-width="32" />

                        @if($ps['totalPcs'] > 0)
                            @foreach ($donutSegments as $seg)
                                <circle cx="70" cy="70" r="54" fill="none" stroke="{{ $seg['color'] }}" stroke-width="32"
                                    stroke-dasharray="{{ $seg['dash'] }} {{ $seg['gap'] }}"
                                    stroke-dashoffset="{{ $seg['offset'] }}" stroke-linecap="butt">
                                    <title>{{ $seg['stage'] }}: {{ number_format($seg['pcs']) }} pcs</title>
                                </circle>
                            @endforeach
                        @endif
                    </svg>

                    {{-- Center text --}}
                    <div class="vi-donut-center">
                        <span class="vi-donut-center-val">{{ number_format($ps['totalPcs']) }}</span>
                        <span class="vi-donut-center-lbl">Pesanan</span>
                    </div>
                </div>

                {{-- Legend --}}
                <div class="vi-donut-legend">
                    @foreach ($ps['segments'] as $seg)
                        <div class="vi-legend-item">
                            <div class="vi-legend-dot" style="background:{{ $seg['color'] }}"></div>
                            <span style="font-weight: 500;">{{ $seg['stage'] }}</span>
                            <span
                                style="color: #94a3b8; font-weight: 700; margin-left: 2px;">{{ $seg['pcs'] ?: '0' }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- ═══ COL 3: Heatmap — Deadline Calendar ═══ --}}
        <div class="vi-card">
            <div class="vi-header">
                <span class="vi-title">Deadline Heatmap</span>
                <div class="vi-title-icon">
                    <svg fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                    </svg>
                </div>
            </div>

            {{-- ◀ Month Navigation ▶ --}}
            <div class="vi-cal-nav">
                <button wire:click="prevMonth" class="vi-cal-nav-btn" title="Bulan sebelumnya">
                    <svg fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                    </svg>
                </button>

                <span class="vi-cal-nav-label" wire:click="resetMonth" title="Kembali ke bulan ini">
                    {{ $hm['monthLabel'] }}
                </span>

                <button wire:click="nextMonth" class="vi-cal-nav-btn" title="Bulan berikutnya">
                    <svg fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                    </svg>
                </button>

                @if(!$hm['isCurrentMonth'])
                    <button wire:click="resetMonth" class="vi-cal-today-btn">Hari Ini</button>
                @endif
            </div>

            <div class="vi-cal-grid">
                {{-- Day-of-week headers --}}
                @foreach (['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'] as $dow)
                    <div class="vi-cal-dow">{{ $dow }}</div>
                @endforeach

                {{-- Empty cells before first day --}}
                @for ($e = 1; $e < $hm['firstDayOfWeek']; $e++)
                    <div class="vi-cal-cell empty"></div>
                @endfor

                {{-- Day cells --}}
                @for ($d = 1; $d <= $hm['daysInMonth']; $d++)
                    @php
                        $count = $hm['deadlines'][$d] ?? 0;
                        $level = $count === 0 ? 'none' : ($count <= 3 ? 'low' : ($count <= 7 ? 'med' : 'high'));
                        $isToday = $hm['today'] !== null && $d === $hm['today'];
                        $tip = $count > 0 ? $count . ' pesanan deadline' : '';
                    @endphp
                    <div class="vi-cal-cell {{ $level }} {{ $isToday ? 'today' : '' }}" @if($tip) data-tip="{{ $tip }}"
                    @endif>
                        {{ $d }}
                    </div>
                @endfor
            </div>

            {{-- Legend --}}
            <div class="vi-cal-legend">
                <div class="vi-cal-legend-item">
                    <div class="vi-cal-legend-dot" style="background:#dbeafe"></div>
                    <span>1-3</span>
                </div>
                <div class="vi-cal-legend-item">
                    <div class="vi-cal-legend-dot" style="background:#fef3c7"></div>
                    <span>4-7</span>
                </div>
                <div class="vi-cal-legend-item">
                    <div class="vi-cal-legend-dot" style="background:#fee2e2"></div>
                    <span>&gt;7 🔥</span>
                </div>
            </div>
        </div>
    </div>
    <div style="font-size: 8px; color: #ccc; text-align: right; margin-top: 10px;">v1.2-Final-Sorting</div>
</x-filament-widgets::widget>