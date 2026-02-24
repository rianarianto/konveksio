@php
    $viewData = $this->getViewData();
    $totalBeban = $viewData['totalBeban'];
    
    // Separate 'Antrian' from the rest of the stages
    $allStages = collect($viewData['stages']);
    $antrianStage = $allStages->firstWhere('name', 'Antrian');
    $antrianQty = $antrianStage ? $antrianStage['qty'] : 0;
    
    $processStages = $allStages->filter(fn($s) => $s['name'] !== 'Antrian');
    
    $trend = $viewData['trend'];
    $trendPositive = $trend && str_starts_with($trend, '+');
@endphp

<x-filament::widget>
    <div class="flex flex-col gap-4 w-full">

        {{-- Top Row: Overview (2 Columns) --}}
        <div class="grid grid-cols-2 md:grid-cols-2 gap-4">
            {{-- Beban Produksi --}}
            <div class="flex flex-col justify-between rounded-xl bg-white dark:bg-gray-900 p-5 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10">
                <div class="flex justify-between items-center mb-4">
                    <span class="text-xs font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400">Total Beban Produksi</span>
                    <div class="rounded-md bg-primary-50 dark:bg-primary-900/30 p-1.5 ring-1 ring-primary-500/10">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-primary-600 dark:text-primary-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18 9 11.25l4.306 4.306a11.95 11.95 0 0 1 5.814-5.518l2.74-1.22m0 0-5.94-2.281m5.94 2.28-2.28 5.941" />
                        </svg>
                    </div>
                </div>
                <div class="mt-auto">
                    <div class="text-4xl font-light text-gray-900 dark:text-white leading-none tracking-tight flex items-baseline gap-2">
                        {{ number_format($totalBeban) }}
                        <span class="text-lg font-medium text-gray-400">Pcs</span>
                    </div>
                    @if($trend)
                    <p class="mt-3 text-xs font-semibold flex items-center gap-1 {{ $trendPositive ? 'text-success-600' : 'text-danger-600' }}">
                        @if($trendPositive)
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 flex-shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M12 7a1 1 0 01.707.293l4 4a1 1 0 01-1.414 1.414L12 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4A1 1 0 0112 7z" clip-rule="evenodd"/></svg>
                        @else
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 flex-shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M12 13a1 1 0 01-.707-.293l-4-4a1 1 0 011.414-1.414L12 10.586l3.293-3.293a1 1 0 011.414 1.414l-4 4A1 1 0 0112 13z" clip-rule="evenodd"/></svg>
                        @endif
                        {{ $trend }}
                    </p>
                    @else
                    <p class="mt-3 text-xs text-gray-400 flex items-center gap-1.5">
                        <span class="h-1 w-1 rounded-full bg-gray-300"></span> Tanpa data kemarin
                    </p>
                    @endif
                </div>
            </div>

            {{-- Antrian --}}
            <div class="flex flex-col justify-between rounded-xl bg-white dark:bg-gray-900 p-5 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10">
                <div class="flex justify-between items-center mb-4">
                    <span class="text-xs font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400">Total Antrian (Belum Diproses)</span>
                    <div class="rounded-md bg-warning-50 dark:bg-warning-900/30 p-1.5 ring-1 ring-warning-500/10">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-warning-600 dark:text-warning-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
                <div class="mt-auto">
                    <div class="text-4xl font-light text-gray-900 dark:text-white leading-none tracking-tight flex items-baseline gap-2">
                        {{ number_format($antrianQty) }}
                        <span class="text-lg font-medium text-gray-400">Pcs</span>
                    </div>
                    <p class="mt-3 text-xs font-semibold text-warning-600 dark:text-warning-400 flex items-center gap-1.5">
                        <span class="h-1.5 w-1.5 rounded-full ring-2 ring-warning-500 bg-white animate-pulse"></span> Harus Segera Diplot
                    </p>
                </div>
            </div>
        </div>

        {{-- Bottom Row: Stages (Horizontally Scrollable) --}}
        <h4 class="text-sm font-semibold text-gray-800 dark:text-gray-200 mt-4">Detail Produksi Berjalan</h4>
        
        <div class="flex flex-nowrap overflow-x-auto gap-3 p-3 snap-x">
            @foreach($processStages as $stage)
                @php
                    $qty = $stage['qty'];
                    if ($qty === 0) {
                        $statusText  = 'Kosong';
                        $statusClass = 'text-gray-400';
                        $bgClass = 'bg-gray-50 dark:bg-gray-800/50';
                    } elseif ($qty >= 50) {
                        $statusText  = 'Menumpuk';
                        $statusClass = 'text-danger-600 dark:text-danger-400';
                        $bgClass = 'bg-danger-50 border-danger-100 dark:bg-danger-900/10 dark:border-danger-900/30';
                    } else {
                        $statusText  = 'Lancar';
                        $statusClass = 'text-success-600 dark:text-success-400';
                        $bgClass = 'bg-success-50 border-success-100 dark:bg-success-900/10 dark:border-success-900/30';
                    }
                @endphp
                <div class="snap-start flex-1 min-w-[140px] flex flex-col justify-between rounded-xl {{ $bgClass }} p-4 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 transition hover:shadow-md">
                    <div class="flex justify-between items-center mb-3">
                        <span class="text-[10px] font-bold tracking-wider uppercase text-gray-600 dark:text-gray-300">{{ $stage['name'] }}</span>
                    </div>
                    <div class="mt-auto pt-1">
                        <div class="text-3xl font-black text-gray-900 dark:text-white leading-tight tracking-tight">{{ number_format($qty) }}</div>
                        <div class="flex items-center gap-1.5 mt-2">
                            @if($qty >= 50)
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 text-danger-500" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                            @elseif($qty > 0)
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 text-success-500" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                            @endif
                            <div class="text-[10px] font-bold uppercase tracking-wider {{ $statusClass }}">{{ $statusText }}</div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

    </div>
</x-filament::widget>
