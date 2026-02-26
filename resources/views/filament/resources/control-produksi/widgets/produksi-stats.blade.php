@php
    $viewData = $this->getViewData();
    $totalBeban = $viewData['totalBeban'];

    $allStages = collect($viewData['stages']);
    $antrianStage = $allStages->firstWhere('name', 'Antrian');
    $antrianQty = $antrianStage ? $antrianStage['qty'] : 0;
    $processStages = $allStages->filter(fn($s) => $s['name'] !== 'Antrian');

    $trend = $viewData['trend'];
    $trendPositive = $trend && str_starts_with($trend, '+');
@endphp

<x-filament::widget>
    <div class="flex flex-col gap-4 w-full">

        {{-- Top Row: 2 Kolom Sejajar --}}
        <div class="flex flex-row gap-4 w-full">

            {{-- Beban Produksi --}}
            <div class="flex-1 flex flex-col justify-between rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-white/10 p-4 sm:p-6 shadow-sm min-w-0">
                {{-- Judul & Ikon --}}
                <div class="flex justify-between items-start gap-2">
                    <p class="text-sm sm:text-base font-medium text-gray-400 dark:text-gray-500 truncate">Beban Produksi</p>
                    <div class="h-8 w-8 shrink-0 rounded-full border border-gray-200 dark:border-white/10 hidden sm:flex items-center justify-center text-primary-600 dark:text-primary-400">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 19.5 15-15m0 0H8.25m11.25 0v11.25" />
                        </svg>
                    </div>
                </div>

                {{-- Angka Besar --}}
                <div class="mt-3 mb-3">
                    <span class="text-5xl sm:text-4xl lg:text-4xl font-light text-gray-700 dark:text-white">{{ number_format($totalBeban) }}</span>
                    <span class="text-2xl sm:text-3xl lg:text-4xl font-light text-gray-400 dark:text-gray-500 ml-1">Pcs</span>
                </div>

                {{-- Trend --}}
                <div>
                    @if($trend)
                        <span class="inline-flex items-center gap-1 text-xs sm:text-sm font-medium {{ $trendPositive ? 'text-success-600 dark:text-success-400' : 'text-danger-600 dark:text-danger-400' }}">
                            @if($trendPositive) ↑ @else ↓ @endif
                            {{ $trend }}
                            <span class="text-gray-400 dark:text-gray-500 font-normal">vs kemarin</span>
                        </span>
                    @else
                        <span class="text-xs sm:text-sm text-gray-400 dark:text-gray-500">• Tanpa data kemarin</span>
                    @endif
                </div>
            </div>

            {{-- Antrian --}}
            <div class="flex-1 flex flex-col justify-between rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-white/10 border-l-4 border-l-warning-400 p-4 sm:p-6 shadow-sm min-w-0">
                {{-- Judul --}}
                <div>
                    <p class="text-sm sm:text-base font-medium text-gray-400 dark:text-gray-500">Antrian</p>
                    <p class="text-xs text-gray-400 dark:text-gray-600 mt-0.5">Order belum diplot</p>
                </div>

                {{-- Angka Besar --}}
                <div class="mt-3 mb-3">
                    <span class="text-4xl sm:text-4xl lg:text-4xl font-light text-gray-700 dark:text-white">{{ number_format($antrianQty) }}</span>
                    <span class="text-2xl sm:text-3xl lg:text-4xl font-light text-gray-400 dark:text-gray-500 ml-1">Pcs</span>
                </div>

                {{-- Status --}}
                <div>
                    @if($antrianQty > 0)
                        <span class="text-xs sm:text-sm font-medium text-warning-600 dark:text-warning-400">Pekerjaan harus segera diplot!</span>
                    @else
                        <span class="text-sm text-gray-400 dark:text-gray-500">Tidak ada antrian saat ini</span>
                    @endif
                </div>
            </div>

        </div>

        {{-- Bottom Row: Stages --}}
        <div>
            <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3 mt-1">Detail Produksi Berjalan</h4>

            <div class="flex flex-nowrap gap-3 overflow-x-auto pb-1" style="-ms-overflow-style:none;scrollbar-width:none;">
                @foreach($processStages as $stage)
                    @php
                        $qty = $stage['qty'];
                        if ($qty === 0) {
                            $statusText = 'Kosong';
                            $statusClass = 'text-gray-400 dark:text-gray-500';
                            $cardClass = 'bg-gray-50 dark:bg-gray-800/60 border-gray-200 dark:border-white/5 text-gray-800 dark:text-gray-200';
                        } elseif ($qty >= 50) {
                            $statusText = 'Menumpuk';
                            $statusClass = 'text-danger-600 dark:text-danger-400 font-semibold';
                            $cardClass = 'bg-danger-50 dark:bg-danger-900/10 border-danger-200 dark:border-danger-800 text-danger-900 dark:text-danger-100';
                        } else {
                            $statusText = 'Lancar';
                            $statusClass = 'text-success-600 dark:text-success-400 font-semibold';
                            $cardClass = 'bg-success-50 dark:bg-success-900/10 border-success-200 dark:border-success-800 text-success-900 dark:text-success-100';
                        }
                    @endphp
                    <div class="flex-1 min-w-[120px] shrink-0 flex flex-col justify-between rounded-xl border {{ $cardClass }} p-4 shadow-sm">
                        <p class="text-[10px] font-bold tracking-widest uppercase opacity-60">{{ $stage['name'] }}</p>
                        <div class="mt-3">
                            <div class="text-2xl font-bold leading-tight">{{ number_format($qty) }}</div>
                            <div class="text-[10px] font-bold tracking-wider uppercase mt-1 {{ $statusClass }}">{{ $statusText }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

    </div>
</x-filament::widget>
