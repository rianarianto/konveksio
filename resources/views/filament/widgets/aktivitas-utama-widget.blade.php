<x-filament-widgets::widget>
    @php
        $data = $this->getData();
    @endphp

    <style>
        .au-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
        }

        @media (max-width: 1024px) {
            .au-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 640px) {
            .au-grid {
                grid-template-columns: 1fr;
            }
        }

        .au-card {
            background: #ffffff;
            border-radius: 18px;
            border: 1px solid #e9e9e9;
            padding: 22px 24px 20px 24px;
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
            display: flex;
            flex-direction: column;
            /* justify-content: space-between; */
            /* min-height: 160px; */
            transition: box-shadow 0.2s;
        }

        .au-card:hover {
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
        }

        .au-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .au-label {
            font-size: 14px;
            font-weight: 500;
            color: #666666;
            line-height: 1.4;
            max-width: 75%;
        }

        .au-icon-wrap {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: #f3eeff;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .au-icon-wrap svg {
            width: 18px;
            height: 18px;
            color: #7c3aed;
            stroke: #7c3aed;
        }

        .au-body {
            margin-top: 12px;
        }

        .au-number {
            font-size: 48px;
            font-weight: 400;
            color: #171717;
            line-height: 1;
            letter-spacing: -1px;
            margin-bottom: 8px;
        }

        .au-number.red {
            color: #ef4444;
        }

        .au-trend {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 12px;
        }

        .au-trend-up {
            color: #22c55e;
            font-weight: 700;
        }

        .au-trend-text {
            color: #c4c4c4;
            font-weight: 400;
        }

        /* ─── DARK MODE ─── */
        .dark .au-card {
            background: rgba(30, 20, 50, .7);
            border-color: rgba(255, 255, 255, .05);
        }

        .dark .au-label {
            color: #9ca3af;
        }

        .dark .au-icon-wrap {
            background: rgba(127, 0, 255, .15);
        }

        .dark .au-number {
            color: #f3f4f6;
        }

        .dark .au-number.red {
            color: #f87171;
        }

        .dark .au-trend-up {
            color: #4ade80;
        }

        .dark .au-trend-text {
            color: #6b7280;
        }
    </style>

    <div class="au-grid">
        {{-- Card 1: Pesanan Masuk Hari Ini --}}
        <div class="au-card">
            <div class="au-header">
                <span class="au-label">Pesanan Masuk Hari Ini</span>
                <div class="au-icon-wrap">
                    <svg fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="m4.5 19.5 15-15m0 0H8.25m11.25 0v11.25" />
                    </svg>
                </div>
            </div>
            <div class="au-body">
                <div class="au-number">{{ $data['orderToday']['count'] }}</div>
                <div class="au-trend">
                    <span class="au-trend-up">↑ {{ $data['orderToday']['trend'] }}</span>
                    <span class="au-trend-text">{{ $data['orderToday']['label'] }}</span>
                </div>
            </div>
        </div>

        {{-- Card 2: Pesanan Diproses --}}
        <div class="au-card">
            <div class="au-header">
                <span class="au-label">Pesanan Diproses</span>
                <div class="au-icon-wrap">
                    <svg fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="m4.5 19.5 15-15m0 0H8.25m11.25 0v11.25" />
                    </svg>
                </div>
            </div>
            <div class="au-body">
                <div class="au-number">{{ $data['diproses']['count'] }}</div>
                <div class="au-trend">
                    <span class="au-trend-up">↑ {{ $data['diproses']['trend'] }}</span>
                    <span class="au-trend-text">{{ $data['diproses']['label'] }}</span>
                </div>
            </div>
        </div>

        {{-- Card 3: Pesanan Siap Di-ambil --}}
        <div class="au-card">
            <div class="au-header">
                <span class="au-label">Pesanan Siap Di-ambil</span>
                <div class="au-icon-wrap">
                    <svg fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="m4.5 19.5 15-15m0 0H8.25m11.25 0v11.25" />
                    </svg>
                </div>
            </div>
            <div class="au-body">
                <div class="au-number">{{ $data['siapAmbil']['count'] }}</div>
                <div class="au-trend">
                    <span class="au-trend-up">↑ {{ $data['siapAmbil']['trend'] }}</span>
                    <span class="au-trend-text">{{ $data['siapAmbil']['label'] }}</span>
                </div>
            </div>
        </div>

        {{-- Card 4: Deadline Hari Ini --}}
        <div class="au-card">
            <div class="au-header">
                <span class="au-label">Deadline Hari Ini</span>
                <div class="au-icon-wrap">
                    <svg fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="m4.5 19.5 15-15m0 0H8.25m11.25 0v11.25" />
                    </svg>
                </div>
            </div>
            <div class="au-body">
                <div class="au-number red">{{ $data['deadlineToday']['count'] }}</div>
                <div class="au-trend">
                    <span class="au-trend-text">{{ $data['deadlineToday']['label'] }}</span>
                </div>
            </div>
        </div>
    </div>
</x-filament-widgets::widget>