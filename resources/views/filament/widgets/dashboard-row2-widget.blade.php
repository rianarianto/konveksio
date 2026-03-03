<x-filament-widgets::widget>
    @php
        $data = $this->getData();

        $piutang = $data['totalPiutang'];
        $pemasukan = $data['pemasukanHariIni'];
        $total = $piutang + $pemasukan;

        // Donut chart ratios (SVG stroke-dasharray trick)
        // r=54, but viewBox is 140x140 (center 70,70) giving 8px padding each side for stroke-width=16
        $circumference = 2 * M_PI * 54;
        $pemasukanDash = $total > 0 ? ($pemasukan / $total) * $circumference : 0;
        $piutangDash = $circumference - $pemasukanDash;

        // Status badge config
        $statusConfig = [
            'selesai' => ['label' => 'SIAP DIAMBIL', 'bg' => '#dcfce7', 'color' => '#16a34a', 'border' => '#bbf7d0'],
            'dikerjakan' => ['label' => 'DIKERJAKAN', 'bg' => '#fef9c3', 'color' => '#ca8a04', 'border' => '#fef08a'],
            'diterima' => ['label' => 'ANTRIAN', 'bg' => '#f1f5f9', 'color' => '#475569', 'border' => '#e2e8f0'],
            'pending' => ['label' => 'ANTRIAN', 'bg' => '#f1f5f9', 'color' => '#475569', 'border' => '#e2e8f0'],
            'diambil' => ['label' => 'DIAMBIL', 'bg' => '#eff6ff', 'color' => '#2563eb', 'border' => '#bfdbfe'],
            'batal' => ['label' => 'BATAL', 'bg' => '#fef2f2', 'color' => '#dc2626', 'border' => '#fecaca'],
        ];

        // Map stage name -> badge (for production stage names)
        $stageConfig = [
            'potong' => ['label' => 'POTONG', 'bg' => '#fff7ed', 'color' => '#c2410c', 'border' => '#fed7aa'],
            'jahit' => ['label' => 'JAHIT', 'bg' => '#fdf4ff', 'color' => '#7e22ce', 'border' => '#e9d5ff'],
            'sablon' => ['label' => 'SABLON', 'bg' => '#eff6ff', 'color' => '#1d4ed8', 'border' => '#bfdbfe'],
            'bordir' => ['label' => 'BORDIR', 'bg' => '#f0fdf4', 'color' => '#15803d', 'border' => '#bbf7d0'],
            'finishing' => ['label' => 'FINISHING', 'bg' => '#fef9c3', 'color' => '#a16207', 'border' => '#fef08a'],
            'packing' => ['label' => 'PACKING', 'bg' => '#f0f9ff', 'color' => '#0369a1', 'border' => '#bae6fd'],
            'antrian' => ['label' => 'ANTRIAN', 'bg' => '#f1f5f9', 'color' => '#475569', 'border' => '#e2e8f0'],
        ];
    @endphp

    <style>
        .r2-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
        }

        @media (max-width: 900px) {
            .r2-grid {
                grid-template-columns: 1fr;
            }
        }

        /* ─── LEFT CARD ─── */
        .r2-card {
            background: #fff;
            border-radius: 18px;
            border: 1px solid #e9e9e9;
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
            padding: 28px 28px 24px 28px;
        }

        .r2-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 24px;
        }

        .r2-icon-wrap {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: #f3eeff;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        @media (max-width: 640px) {
            .r2-card-header .r2-icon-wrap {
                display: none;
            }
            .r2-cashflow-title {
                margin-top: 8px; /* Compensate for removed icon space */
            }
        }

        .r2-icon-wrap svg {
            width: 18px;
            height: 18px;
            stroke: #7c3aed;
        }

        .r2-cashflow-inner {
            display: flex;
            gap: 32px;
            align-items: center;
        }

        @media (max-width: 640px) {
            .r2-cashflow-inner {
                flex-direction: column;
                align-items: flex-start;
                gap: 24px;
            }
        }

        .r2-cashflow-title {
            font-size: 16px;
            font-weight: 600;
            color: #222;
            margin-bottom: 24px;
        }

        .r2-metric-label {
            font-size: 12px;
            color: #a3a3a3;
            font-weight: 500;
            margin-bottom: 4px;
        }

        .r2-metric-value {
            font-size: 42px;
            font-weight: 600;
            color: #171717;
            letter-spacing: -0.5px;
            margin-bottom: 20px;
            line-height: 1.1;
            word-break: break-word; /* Prevent long numbers from overflowing */
        }

        /* ─── DONUT CHART ─── */
        .r2-donut-wrap {
            flex-shrink: 0;
            position: relative;
            width: 200px;
            height: 200px;
            margin: 0 auto; /* Center on mobile when column flex */
        }

        @media (max-width: 640px) {
            .r2-donut-wrap {
                width: 160px;
                height: 160px;
            }
            .r2-donut-wrap svg {
                width: 160px;
                height: 160px;
            }
        }

        .r2-donut-wrap svg {
            transform: rotate(-90deg);
            display: block;
        }

        .r2-donut-legend {
            margin-top: 12px;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        @media (max-width: 640px) {
            .r2-donut-legend {
                align-items: center; /* Center legend text on mobile */
            }
        }

        .r2-legend-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            color: #6b6b6b;
        }

        .r2-legend-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        /* ─── RIGHT CARD (ACTIVITIES) ─── */
        .r2-act-card {
            background: #fff;
            border-radius: 18px;
            border: 1px solid #e9e9e9;
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
            padding: 24px;
            display: flex;
            flex-direction: column;
        }

        .r2-act-title {
            font-size: 15px;
            font-weight: 600;
            color: #222;
            margin-bottom: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .r2-act-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .r2-act-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            padding-bottom: 12px;
            border-bottom: 1px solid #f1f5f9;
        }

        @media (max-width: 640px) {
            .r2-act-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }
        }

        .r2-act-item:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .r2-act-info {
            flex: 1;
            min-width: 0;
            width: 100%;
        }

        .r2-act-invoice {
            font-size: 11px;
            color: #b0b0b0;
            margin-bottom: 2px;
        }

        .r2-act-customer {
            font-size: 14px;
            font-weight: 600;
            color: #222;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 100%;
        }

        .r2-badge {
            font-size: 11px;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 6px;
            border: 1px solid;
            white-space: nowrap;
            flex-shrink: 0;
            letter-spacing: 0.3px;
        }

        .r2-empty {
            font-size: 13px;
            color: #ccc;
            text-align: center;
            padding: 24px 0;
        }
    </style>

    <div class="r2-grid">
        {{-- ═══ LEFT: Arus Kas (Cashflow) ═══ --}}
        <div class="r2-card">
            
            <div class="r2-cashflow-inner">
                {{-- Metrics --}}
                <div style="flex:1; width: 100%;">
                    <div class="r2-card-header">
                        <div class="r2-icon-wrap">
                            <svg fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="m4.5 19.5 15-15m0 0H8.25m11.25 0v11.25" />
                            </svg>
                        </div>
                    </div>
                    <div class="r2-cashflow-title">Arus Kas (Cashflow)</div>
                    <div class="r2-metric-label">Total Piutang</div>
                    <div class="r2-metric-value">IDR {{ number_format($piutang, 0, ',', '.') }}</div>
                    <div class="r2-metric-label">Pendapatan Hari Ini</div>
                    <div class="r2-metric-value">IDR
                        {{ number_format($pemasukan, 0, ',', '.') }}
                    </div>
                </div>

                {{-- Donut Chart --}}
                <div style="flex:1; width: 100%; display: flex; flex-direction: column; align-items: center;">
                    <div class="r2-donut-wrap" style="justify-self: center;">
                        {{-- viewBox 140x140, center (70,70), r=54 → outer edge=54+8=62 → 70-62=8px padding. All edges
                        safe! --}}
                        <svg viewBox="0 0 140 140" width="200" height="200">
                            {{-- Background track --}}
                            <circle cx="70" cy="70" r="54" fill="none" stroke="#e9d5ff" stroke-width="28" />
                            {{-- Pemasukan segment (purple) --}}
                            @if($pemasukanDash > 0)
                                <circle cx="70" cy="70" r="54" fill="none" stroke="#7c3aed" stroke-width="28"
                                    stroke-dasharray="{{ $pemasukanDash }} {{ $circumference - $pemasukanDash }}"
                                    stroke-dashoffset="0" stroke-linecap="round" />
                            @endif
                        </svg>
                    </div>
                    <div class="r2-donut-legend" style="justify-self: center;">
                        <div class="r2-legend-item">
                            <div class="r2-legend-dot" style="background:#7c3aed"></div>
                            <span>Pendapatan Hari Ini</span>
                        </div>
                        <div class="r2-legend-item">
                            <div class="r2-legend-dot" style="background:#e9d5ff"></div>
                            <span>Piutang</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ═══ RIGHT: Daftar Aktivitas Terbaru ═══ --}}
        <div class="r2-act-card">
            <div class="r2-act-title">
                <span>Daftar Aktivitas Terbaru</span>
                <div class="r2-icon-wrap">
                    <svg fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="m4.5 19.5 15-15m0 0H8.25m11.25 0v11.25" />
                    </svg>
                </div>
            </div>

            <div class="r2-act-list">
                @forelse($data['activities'] as $act)
                    @php
                        $stageKey = strtolower(trim($act['stage']));
                        $badge = $stageConfig[$stageKey]
                            ?? ['label' => strtoupper($act['stage']), 'bg' => '#f1f5f9', 'color' => '#475569', 'border' => '#e2e8f0'];
                    @endphp
                    <div class="r2-act-item">
                        <div class="r2-act-info">
                            <div class="r2-act-invoice">{{ $act['invoice'] }}</div>
                            <div class="r2-act-customer">{{ $act['customer_name'] }}</div>
                        </div>
                        <span class="r2-badge"
                            style="background:{{ $badge['bg'] }}; color:{{ $badge['color'] }}; border-color:{{ $badge['border'] }};">
                            {{ $badge['label'] }}
                        </span>
                    </div>
                @empty
                    <div class="r2-empty">Belum ada aktivitas produksi</div>
                @endforelse
            </div>
        </div>
    </div>
</x-filament-widgets::widget>