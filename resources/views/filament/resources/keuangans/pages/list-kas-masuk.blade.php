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
                <div class="inline-flex items-center gap-2">
                    <span>Riwayat Kas Masuk</span>
                    @if($pendingApprovalsCount > 0)
                        <span class="relative flex h-2.5 w-2.5" title="{{ $pendingApprovalsCount }} pengajuan butuh review">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-red-600"></span>
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