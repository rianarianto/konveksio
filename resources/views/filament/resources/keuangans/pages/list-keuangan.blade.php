<x-filament-panels::page>
    <div x-data="{ activeTab: 'piutang' }" class="space-y-6">
        
        <x-filament::tabs>
            <x-filament::tabs.item
                alpine-active="activeTab === 'piutang'"
                x-on:click="activeTab = 'piutang'"
            >
                Piutang & Penagihan
            </x-filament::tabs.item>

            <x-filament::tabs.item
                alpine-active="activeTab === 'kas_masuk'"
                x-on:click="activeTab = 'kas_masuk'"
            >
                Riwayat Kas Masuk
            </x-filament::tabs.item>

            <x-filament::tabs.item
                alpine-active="activeTab === 'pengeluaran'"
                x-on:click="activeTab = 'pengeluaran'"
            >
                Pengeluaran Operasional
            </x-filament::tabs.item>
        </x-filament::tabs>

        <div>
            <!-- Tab 1: Piutang -->
            <div
                x-show="activeTab === 'piutang'"
                style="display: none;"
                x-bind:style="activeTab === 'piutang' ? 'display: block;' : 'display: none;'"
            >
                @livewire(\App\Filament\Resources\Keuangans\Widgets\PiutangTableWidget::class)
            </div>

            <!-- Tab 2: Kas Masuk -->
            <div
                x-show="activeTab === 'kas_masuk'"
                style="display: none;"
                x-bind:style="activeTab === 'kas_masuk' ? 'display: block;' : 'display: none;'"
            >
                @livewire(\App\Filament\Resources\Keuangans\Widgets\KasMasukTableWidget::class)
            </div>

            <!-- Tab 3: Pengeluaran -->
            <div
                x-show="activeTab === 'pengeluaran'"
                style="display: none;"
                x-bind:style="activeTab === 'pengeluaran' ? 'display: block;' : 'display: none;'"
            >
                @livewire(\App\Filament\Resources\Keuangans\Widgets\PengeluaranTableWidget::class)
            </div>
        </div>
        
    </div>
</x-filament-panels::page>
