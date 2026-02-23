@php
    $viewData = $this->getViewData();
    $totalBeban = $viewData['totalBeban'];
    $stages = $viewData['stages'];
    $trend = $viewData['trend'];
    $trendPositive = $trend && str_starts_with($trend, '+');
@endphp

<x-filament::widget>
    <div class="flex flex-col sm:flex-row gap-3 w-full">

        {{-- Hero Card: Total Beban --}}
        <div class="flex flex-col justify-between rounded-xl bg-white dark:bg-gray-900 p-4 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 sm:min-w-[200px] flex-shrink-0">
            <div class="flex justify-between items-center">
                <span class="text-[10px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Beban Produksi</span>
                <div class="rounded-full bg-primary-50 dark:bg-primary-900/30 p-1 ring-1 ring-primary-500/10">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 text-primary-600 dark:text-primary-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18 9 11.25l4.306 4.306a11.95 11.95 0 0 1 5.814-5.518l2.74-1.22m0 0-5.94-2.281m5.94 2.28-2.28 5.941" />
                    </svg>
                </div>
            </div>
            <div class="mt-2">
                <div class="text-3xl font-black text-gray-950 dark:text-white leading-none tracking-tight">
                    {{ number_format($totalBeban) }}
                    <span class="text-sm font-bold text-gray-400 ml-0.5">Pcs</span>
                </div>
                @if($trend)
                <p class="mt-1.5 text-[10px] font-semibold flex items-center gap-1 {{ $trendPositive ? 'text-success-600' : 'text-danger-600' }}">
                    @if($trendPositive)
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-2.5 w-2.5 flex-shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M12 7a1 1 0 01.707.293l4 4a1 1 0 01-1.414 1.414L12 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4A1 1 0 0112 7z" clip-rule="evenodd"/></svg>
                    @else
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-2.5 w-2.5 flex-shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M12 13a1 1 0 01-.707-.293l-4-4a1 1 0 011.414-1.414L12 10.586l3.293-3.293a1 1 0 011.414 1.414l-4 4A1 1 0 0112 13z" clip-rule="evenodd"/></svg>
                    @endif
                    {{ $trend }}
                </p>
                @else
                <p class="mt-1.5 text-[10px] text-gray-400">— vs kemarin</p>
                @endif
            </div>
        </div>

        {{-- Mini Cards Grid --}}
        <div class="grid gap-3 grid-cols-2 lg:grid-cols-3 w-full">
            @foreach($stages as $stage)
                @php
                    $qty = $stage['qty'];
                    if ($qty === 0) {
                        $statusText  = 'Kosong';
                        $statusClass = 'text-gray-400';
                    } elseif ($qty >= 50) {
                        $statusText  = 'Menumpuk';
                        $statusClass = 'text-danger-600 dark:text-danger-400';
                    } else {
                        $statusText  = 'Lancar';
                        $statusClass = 'text-success-600 dark:text-success-400';
                    }
                @endphp
                <div class="flex flex-col justify-between rounded-xl bg-white dark:bg-gray-900 p-3 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10">
                    <div class="flex justify-between items-center">
                        <span class="text-[10px] font-semibold tracking-wider uppercase text-gray-500 dark:text-gray-400">{{ $stage['name'] }}</span>
                        <div class="rounded-full bg-primary-50 dark:bg-primary-900/30 p-1 ring-1 ring-primary-500/10 hover:bg-primary-100 transition-colors cursor-pointer">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-2.5 w-2.5 text-primary-600 dark:text-primary-400" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 19.5l15-15m0 0H8.25m11.25 0v11.25" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-1">
                        <div class="text-2xl font-black text-gray-950 dark:text-white leading-none tracking-tight">{{ number_format($qty) }}</div>
                        <div class="text-[9px] font-bold uppercase tracking-wider mt-1 {{ $statusClass }}">{{ $statusText }}</div>
                    </div>
                </div>
            @endforeach
        </div>

    </div>
</x-filament::widget>
