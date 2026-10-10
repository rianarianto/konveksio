<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}

        <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100 dark:border-white/10">
            <x-filament::button type="submit" size="md">
                Simpan Perubahan
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
