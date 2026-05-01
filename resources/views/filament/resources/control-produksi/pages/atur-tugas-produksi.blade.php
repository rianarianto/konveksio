<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <div class="mt-6 flex items-center gap-4">
            <x-filament::button
                type="submit"
                color="primary"
                size="lg"
                icon="heroicon-m-check-circle"
                wire:loading.attr="disabled"
            >
                <span wire:loading.remove wire:target="save">💾 Simpan Tugas Produksi</span>
                <span wire:loading wire:target="save">⏳ Menyimpan...</span>
            </x-filament::button>

            <x-filament::button
                tag="a"
                href="{{ \App\Filament\Resources\ControlProduksis\ControlProduksiResource::getUrl('index') }}"
                color="gray"
                size="lg"
                outlined
            >
                ← Kembali
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
