<div x-data="{ collapsed: false }" 
     x-init="
        console.log('LIVEWIRE TABLE LOADED');
     ">
    <style>
        .fi-ta-table td {
            vertical-align: baseline !important;
        }
        /* Style for inputs in modals to make them stand out */
        .fi-modal .fi-input-wrp {
            background-color: #f8faff !important;
            border: 1px solid #c7d2fe !important;
            box-shadow: none !important;
        }
        .fi-modal .fi-input-wrp:focus-within {
            background-color: #ffffff !important;
            border: 1px solid #6366f1 !important;
            ring: 1px #6366f1 !important;
        }
    </style>

    <div class="integrated-table-wrapper" style="max-height: 800px !important; overflow-y: auto !important; border: 1px solid #e5e7eb; border-radius: 0.75rem; background: white;">
        {{ $this->table }}
    </div>

    <x-filament-actions::modals />
</div>
