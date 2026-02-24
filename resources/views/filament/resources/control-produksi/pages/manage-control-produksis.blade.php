<x-filament-panels::page>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 items-start">
        <div class="lg:col-span-3 space-y-4">
            {{ $this->table }}
        </div>
        <div class="lg:col-span-1 border-gray-200 dark:border-gray-800 lg:border-l lg:pl-6 pb-12">
            <div class="sticky top-6">
                @livewire(\App\Livewire\EmployeeWorkloadSidebar::class)
            </div>
        </div>
    </div>
</x-filament-panels::page>