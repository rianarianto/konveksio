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
        /* Style for inputs in modals to make them stand out */
        .fi-modal .fi-input-wrp {
            background-color: #f8faff !important; /* Extremely light Indigo */
            border: 1px solid #c7d2fe !important; /* Thin Indigo 200 border */
            box-shadow: none !important;
        }
        .fi-modal .fi-input-wrp:focus-within {
            background-color: #ffffff !important;
            border: 1px solid #6366f1 !important; /* Indigo 500 */
            ring: 1px #6366f1 !important;
        }
    </style>

    <div style="max-height: 500px; overflow-y: auto; border: 1px solid #e5e7eb; border-radius: 0.5rem; margin-bottom: 1rem;">
        {{ $this->table }}
    </div>

    <x-filament-actions::modals />
</div>
