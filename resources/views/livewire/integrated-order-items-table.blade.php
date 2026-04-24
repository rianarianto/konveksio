<div x-data="{ collapsed: false }" 
     x-init="
        $nextTick(() => {
            if (!collapsed) {
                // Find all grouping toggle buttons that are currently expanded
                // Filament usually uses an svg with chevron-up or similar for expanded state
                document.querySelectorAll('.fi-ta-group-header button').forEach(button => {
                    button.click();
                });
                collapsed = true;
            }
        })
     ">
    <style>
        .fi-ta-table td {
            vertical-align: baseline !important;
        }
    </style>

    {{ $this->table }}

    <x-filament-actions::modals />
</div>
