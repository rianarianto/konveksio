<x-filament-widgets::widget>
    @php
        $data = $this->getData();
        $omzet = $data['omzet'];
        $piutang = $data['piutang_macet'];
        $workshop = $data['workshop'];

        $rpFormat = function ($val) {
            if ($val >= 1_000_000_000)
                return 'Rp ' . number_format($val / 1_000_000_000, 1) . ' M';
            if ($val >= 1_000_000)
                return 'Rp ' . number_format($val / 1_000_000, 1) . ' Jt';
            if ($val >= 1_000)
                return 'Rp ' . number_format($val / 1_000, 1) . ' Rb';
            return 'Rp ' . number_format($val, 0, ',', '.');
        };
    @endphp

    <style>
        .ofs-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }

        @media (max-width: 1024px) {
            .ofs-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 640px) {
            .ofs-grid {
                grid-template-columns: 1fr;
            }
        }

        .ofs-card {
            background: #fff;
            border-radius: 18px;
            border: 1px solid #ebebeb;
            padding: 22px 24px 24px;
            box-shadow: 0 1px 4px rgba(0, 0, 0, .04);
            display: flex;
            flex-direction: column;
            gap: 0;
            transition: box-shadow .2s;
            position: relative;
            overflow: hidden;
            height: 100%;
        }

        .dark .ofs-card {
            background: rgba(30, 20, 50, .7);
            border-color: rgba(255, 255, 255, .07);
        }

        .ofs-card:hover {
            box-shadow: 0 6px 20px rgba(127, 0, 255, .10);
        }

        /* Decorative purple accent bar */
        .ofs-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #7F00FF, #bf80ff);
            border-radius: 18px 18px 0 0;
        }

        .ofs-card.danger::before {
            background: linear-gradient(90deg, #ef4444, #f87171);
        }

        .ofs-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 14px;
        }

        .ofs-label {
            font-size: 11px;
            font-weight: 600;
            color: #888;
            text-transform: uppercase;
            letter-spacing: .06em;
        }

        .dark .ofs-label {
            color: #9ca3af;
        }

        .ofs-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: #f3eeff;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .ofs-icon svg {
            width: 18px;
            height: 18px;
        }

        .ofs-card.danger .ofs-icon {
            background: #fff1f1;
        }

        .dark .ofs-icon {
            background: rgba(127, 0, 255, .18);
        }

        .ofs-amount {
            font-size: 30px;
            font-weight: 800;
            color: #111;
            line-height: 1.1;
            letter-spacing: -1px;
            margin-bottom: 12px;
        }

        .dark .ofs-amount {
            color: #f9f9f9;
        }

        .ofs-card.danger .ofs-amount {
            color: #dc2626;
        }

        .ofs-sub {
            font-size: 12px;
            color: #6b7280;
            margin-bottom: 16px;
            font-weight: 500;
        }

        .dark .ofs-sub {
            color: #9ca3af;
        }

        .ofs-sub span {
            color: #16a34a;
            font-weight: 700;
        }

        .ofs-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 11px;
            font-weight: 700;
            padding: 3px 9px;
            border-radius: 999px;
            width: fit-content;
            margin-top: auto;
        }

        .ofs-badge.green {
            background: #dcfce7;
            color: #16a34a;
        }

        .ofs-badge.grey {
            background: #f3f4f6;
            color: #6b7280;
        }

        .ofs-badge.red {
            background: #fee2e2;
            color: #dc2626;
        }

        .dark .ofs-badge.green {
            background: rgba(22, 163, 74, .18);
            color: #4ade80;
        }

        .dark .ofs-badge.grey {
            background: rgba(107, 114, 128, .18);
            color: #9ca3af;
        }

        .dark .ofs-badge.red {
            background: rgba(220, 38, 38, .18);
            color: #fb7185;
        }

        .ofs-tooltip {
            font-size: 11px;
            color: #9ca3af;
            margin-top: 10px;
            font-style: italic;
        }

        /* Progress bar */
        .ofs-progress-wrap {
            margin-top: 14px;
        }

        .ofs-progress-meta {
            display: flex;
            justify-content: space-between;
            font-size: 11px;
            color: #9ca3af;
            margin-bottom: 6px;
            font-weight: 600;
        }

        .ofs-progress-track {
            width: 100%;
            height: 8px;
            background: #f0e6ff;
            border-radius: 999px;
            overflow: hidden;
        }

        .dark .ofs-progress-track {
            background: rgba(127, 0, 255, .15);
        }

        .ofs-progress-fill {
            height: 100%;
            border-radius: 999px;
            background: linear-gradient(90deg, #7F00FF, #bf80ff);
            transition: width .8s cubic-bezier(.4, 0, .2, 1);
        }

        .ofs-progress-fill.warn {
            background: linear-gradient(90deg, #f59e0b, #fcd34d);
        }

        .ofs-progress-fill.alarm {
            background: linear-gradient(90deg, #ef4444, #f87171);
        }

        .ofs-big-detail {
            font-size: 24px;
            font-weight: 800;
            color: #111;
            margin-bottom: 2px;
        }

        .dark .ofs-big-detail {
            color: #f3f4f6;
        }

        .ofs-big-detail small {
            font-size: 12px;
            font-weight: 600;
            color: #7F00FF;
        }

        .dark .ofs-big-detail small {
            color: #bf80ff;
        }
    </style>

    <div class="ofs-grid">

        {{-- ── CARD 1: REVENUE & PROFITABILITY ───────────────────────────── --}}
        <div class="ofs-card">
            <div class="ofs-header">
                <span class="ofs-label">Total Omzet Bulan Ini</span>
                <div class="ofs-icon">
                    <svg fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="#7F00FF">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </div>
            </div>

            <div class="ofs-amount">{{ $rpFormat($omzet['total']) }}</div>

            @php
                $omzetClass = !$omzet['trend_up'] ? 'grey' : 'green';
                $omzetArrow = $omzet['trend_up'] ? '↑' : '↓';
            @endphp
            <span class="ofs-badge {{ $omzetClass }}">
                {{ $omzetArrow }} {{ $omzet['trend_label'] }}
            </span>
        </div>

        {{-- ── CARD 2: PIUTANG MACET ──────────────────────────────────────── --}}
        <div class="ofs-card {{ $piutang['total'] > 0 ? 'danger' : '' }}">
            <div class="ofs-header">
                <span class="ofs-label">Piutang Macet</span>
                <div class="ofs-icon">
                    <svg fill="none" viewBox="0 0 24 24" stroke-width="2"
                        stroke="{{ $piutang['total'] > 0 ? '#ef4444' : '#7F00FF' }}">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                    </svg>
                </div>
            </div>

            <div class="ofs-amount">
                {{ $piutang['total'] > 0 ? $rpFormat($piutang['total']) : 'Nihil ✓' }}
            </div>
            <div class="ofs-sub">Lewat deadline, belum lunas</div>

            @php
                $piutangBadge = $piutang['danger'] ? 'red' : ($piutang['trend_pct'] < 0 ? 'green' : 'grey');
                $piutangArrow = $piutang['trend_pct'] >= 0 ? '↑' : '↓';
            @endphp
            <span class="ofs-badge {{ $piutangBadge }}">
                {{ $piutangArrow }} {{ $piutang['trend_label'] }}
            </span>

            @if($piutang['count_customers'] > 0)
                <div class="ofs-tooltip">
                    💬 Segera tagih {{ $piutang['count_customers'] }} pelanggan
                </div>
            @endif
        </div>

        {{-- ── CARD 3: BEBAN WORKSHOP ─────────────────────────────────────── --}}
        <div class="ofs-card">
            <div class="ofs-header">
                <span class="ofs-label">Beban Workshop</span>
                <div class="ofs-icon">
                    <svg fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="#7F00FF">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12" />
                    </svg>
                </div>
            </div>

            <div class="ofs-big-detail">
                {{ $workshop['active_orders'] }} Pesanan
                <small>({{ number_format($workshop['total_pcs']) }} Pcs)</small>
            </div>
            <div class="ofs-sub" style="margin-bottom:0;">Sedang dikerjakan di workshop</div>

            @php
                $fillClass = $workshop['occupancy_pct'] >= 90 ? 'alarm'
                    : ($workshop['occupancy_pct'] >= 70 ? 'warn' : '');
            @endphp
            <div class="ofs-progress-wrap">
                <div class="ofs-progress-meta">
                    <span>{{ $workshop['occupancy_pct'] }}% Kapasitas</span>
                    <span>{{ number_format($workshop['total_pcs']) }} / {{ number_format($workshop['max_capacity']) }}
                        Pcs</span>
                </div>
                <div class="ofs-progress-track">
                    <div class="ofs-progress-fill {{ $fillClass }}" style="width: {{ $workshop['occupancy_pct'] }}%;">
                    </div>
                </div>
            </div>
        </div>

    </div>
</x-filament-widgets::widget>