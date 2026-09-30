<x-filament-panels::page>
    @php
        $pendingApprovalsCount = 0;
        if (auth()->user()?->role === 'owner') {
            try {
                $pendingApprovalsCount = \App\Models\PaymentCorrectionRequest::where('status', 'pending')->count();
            } catch (\Throwable $e) {
                $pendingApprovalsCount = 0;
            }
        }
    @endphp

    <div x-data="{ activeTab: '{{ $pendingApprovalsCount > 0 ? 'kas_masuk' : 'piutang' }}' }" class="space-y-6">

        <x-filament::tabs>
            <x-filament::tabs.item alpine-active="activeTab === 'piutang'" x-on:click="activeTab = 'piutang'">
                Piutang & Penagihan
            </x-filament::tabs.item>

            <x-filament::tabs.item alpine-active="activeTab === 'kas_masuk'" x-on:click="activeTab = 'kas_masuk'">
                <div class="flex items-center gap-2">
                    <span>Riwayat Kas Masuk</span>
                    @if($pendingApprovalsCount > 0)
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800 border border-amber-300 dark:bg-amber-900/40 dark:text-amber-300 dark:border-amber-700 animate-pulse">
                            {{ $pendingApprovalsCount }} Butuh Review
                        </span>
                    @endif
                </div>
            </x-filament::tabs.item>

        </x-filament::tabs>
        <div>
            <!-- Tab 1: Piutang -->
            <div x-show="activeTab === 'piutang'" style="display: none;"
                x-bind:style="activeTab === 'piutang' ? 'display: block;' : 'display: none;'">
                @livewire(\App\Filament\Resources\Keuangans\Widgets\PiutangTableWidget::class)
            </div>

            <!-- Tab 2: Kas Masuk -->
            <div x-show="activeTab === 'kas_masuk'" style="display: none;"
                x-bind:style="activeTab === 'kas_masuk' ? 'display: block;' : 'display: none;'">
                @livewire(\App\Filament\Resources\Keuangans\Widgets\KasMasukTableWidget::class)
            </div>
        </div>

    </div>
</x-filament-panels::page>