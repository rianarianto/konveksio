<div x-data="{ collapsed: false }" 
     x-init="
        $nextTick(() => {
            if (!collapsed) {
                // Only click the collapse toggle button, NOT action dropdown buttons
                document.querySelectorAll('.fi-ta-group-header button').forEach(button => {
                    const clickAttr = button.getAttribute('x-on:click') || '';
                    if (clickAttr.includes('isGroupCollapsed') || clickAttr.includes('toggleCollapseGroup')) {
                        button.click();
                    }
                });
                collapsed = true;
            }
        });
        if (typeof styleUnassignedGroups === 'function') {
            styleUnassignedGroups();
        }
     ">
    <style>
        /* Sticky Actions Column on the Right */
        .fi-ta-table th:last-child {
            position: sticky !important;
            right: 0 !important;
            z-index: 20 !important;
            background-color: #ffffff !important;
            box-shadow: -4px 0 6px -4px rgba(0, 0, 0, 0.1) !important;
        }
        .dark .fi-ta-table th:last-child {
            background-color: #111827 !important;
        }
        .fi-ta-table tr:not(.fi-ta-group-header) td:last-child {
            position: sticky !important;
            right: 0 !important;
            z-index: 15 !important;
            background-color: #ffffff !important;
            box-shadow: -4px 0 6px -4px rgba(0, 0, 0, 0.1) !important;
        }
        .dark .fi-ta-table tr:not(.fi-ta-group-header) td:last-child {
            background-color: #111827 !important;
        }
        .fi-ta-table tr:not(.fi-ta-group-header):hover td:last-child {
            background-color: #f9fafb !important;
        }
        .dark .fi-ta-table tr:not(.fi-ta-group-header):hover td:last-child {
            background-color: #1f2937 !important;
        }
        
        /* Elevate z-index when actions cell is hovered or focused to prevent overlapping */
        .fi-ta-table tr:not(.fi-ta-group-header) td:last-child:hover,
        .fi-ta-table tr:not(.fi-ta-group-header) td:last-child:focus-within {
            z-index: 30 !important;
        }

        .fi-ta-table th {
            vertical-align: baseline !important;
            padding-left: 24px !important;
            padding-right: 24px !important;
        }
        .fi-ta-table td {
            vertical-align: baseline !important;
            padding-left: 24px !important;
            padding-right: 24px !important;
        }
        .fi-ta-table .fi-badge {
            padding: 4px 8px !important;
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

        /* Visible Horizontal Scrollbar styling for Order Items Table */
        .fi-ta-content, 
        .fi-ta-table-container,
        .fi-ta-ctn {
            overflow-x: auto !important;
            -webkit-overflow-scrolling: touch !important;
            padding-bottom: 6px !important;
        }
        .fi-ta-content::-webkit-scrollbar, 
        .fi-ta-table-container::-webkit-scrollbar,
        .fi-ta-ctn::-webkit-scrollbar {
            height: 10px !important;
            width: 10px !important;
            display: block !important;
        }
        .fi-ta-content::-webkit-scrollbar-track, 
        .fi-ta-table-container::-webkit-scrollbar-track,
        .fi-ta-ctn::-webkit-scrollbar-track {
            background: #e2e8f0 !important;
            border-radius: 6px !important;
        }
        .fi-ta-content::-webkit-scrollbar-thumb, 
        .fi-ta-table-container::-webkit-scrollbar-thumb,
        .fi-ta-ctn::-webkit-scrollbar-thumb {
            background: #6366f1 !important;
            border-radius: 6px !important;
            border: 2px solid #e2e8f0 !important;
        }
        .fi-ta-content::-webkit-scrollbar-thumb:hover, 
        .fi-ta-table-container::-webkit-scrollbar-thumb:hover,
        .fi-ta-ctn::-webkit-scrollbar-thumb:hover {
            background: #4f46e5 !important;
        }

        /* Ensure dropdown sits on top */
        .fi-dropdown-panel {
            z-index: 9999 !important;
        }
        
        .fi-ta-group-header {
            padding: 0.75rem 1rem !important;
        }
        /* Make inner div of group header a flex row so title and badge/button are inline */
        .fi-ta-group-header > div:not(.fi-ta-group-checkbox):first-of-type {
            display: flex !important;
            align-items: center !important;
            flex: 1 !important;
            gap: 0.5rem !important;
            min-width: 0 !important;
        }
        .fi-ta-group-heading {
            white-space: nowrap !important;
            flex-shrink: 0 !important;
            margin: 0 !important;
        }
        .fi-ta-group-description {
            display: flex !important;
            align-items: center !important;
            flex: 1 !important;
            min-width: 0 !important;
            margin: 0 !important;
            padding: 0 !important;
        }
        tr.fi-ta-group-header, 
        tr.fi-ta-group-header td, 
        tr.fi-ta-group-header th {
            background-color: #eef2ff !important; /* Indigo 50 */
            border-bottom: 1px solid #c7d2fe !important;
            border-top: 1px solid #c7d2fe !important;
        }
        .dark tr.fi-ta-group-header, 
        .dark tr.fi-ta-group-header td, 
        .dark tr.fi-ta-group-header th {
            background-color: #1e1b4b !important; /* Indigo 950 */
            border-bottom: 1px solid #3730a3 !important;
            border-top: 1px solid #3730a3 !important;
        }
        
        /* Apply left border ONLY to the very first cell (checkbox column) so it doesn't touch the text */
        tr.fi-ta-group-header td:first-child,
        tr.fi-ta-group-header th:first-child {
            border-left: 4px solid #4f46e5 !important;
        }
        .dark tr.fi-ta-group-header td:first-child,
        .dark tr.fi-ta-group-header th:first-child {
            border-left: 4px solid #818cf8 !important;
        }

        tr.fi-ta-group-header button, 
        tr.fi-ta-group-header span, 
        tr.fi-ta-group-header div {
            color: #312e81 !important; /* Indigo 900 */
            font-weight: 700 !important;
            padding-left: 8px !important; /* Give elegant breathing room so it never touches any borders */
        }
        .dark tr.fi-ta-group-header div {
            color: #e0e7ff !important; /* Indigo 100 */
        }

        /* Style unassigned group headers dynamically via pure CSS has() */
        tr[class*="group-header"]:has(.unassigned-badge),
        tr[class*="group-header"]:has(.unassigned-badge) td,
        tr[class*="group-header"]:has(.unassigned-badge) th {
            background-color: #fffbeb !important; /* Soft yellow warning */
            border-bottom: 1px solid #fef3c7 !important;
            border-top: 1px solid #fef3c7 !important;
        }
        tr[class*="group-header"]:has(.unassigned-badge) td:first-child,
        tr[class*="group-header"]:has(.unassigned-badge) th:first-child {
            border-left: 4px solid #ea580c !important; /* Orange left border */
        }
        tr[class*="group-header"]:has(.unassigned-badge) span,
        tr[class*="group-header"]:has(.unassigned-badge) button,
        tr[class*="group-header"]:has(.unassigned-badge) div {
            color: #9a3412 !important; /* Brownish-orange text for warning */
        }
        
        .dark tr[class*="group-header"]:has(.unassigned-badge),
        .dark tr[class*="group-header"]:has(.unassigned-badge) td,
        .dark tr[class*="group-header"]:has(.unassigned-badge) th {
            background-color: #451a03 !important; /* Dark amber/brown warning */
            border-bottom: 1px solid #78350f !important;
            border-top: 1px solid #78350f !important;
        }
        .dark tr[class*="group-header"]:has(.unassigned-badge) td:first-child,
        .dark tr[class*="group-header"]:has(.unassigned-badge) th:first-child {
            border-left: 4px solid #ea580c !important;
        }
        .dark tr[class*="group-header"]:has(.unassigned-badge) span,
        .dark tr[class*="group-header"]:has(.unassigned-badge) button,
        .dark tr[class*="group-header"]:has(.unassigned-badge) div {
            color: #ffedd5 !important;
        }

        /* Badge styling inside the group header */
        .unassigned-badge {
            background-color: #fdf2f2 !important;
            color: #b91c1c !important;
            font-size: 11px !important;
            font-weight: 600 !important;
            padding: 2px 10px !important;
            border-radius: 9999px !important;
            margin-left: 10px !important;
            border: 1px solid #fecaca !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 4px !important;
            vertical-align: middle !important;
            box-shadow: 0 1px 2px rgba(0,0,0,0.02) !important;
            line-height: 1.2 !important;
        }
        .dark .unassigned-badge {
            background-color: #7f1d1d !important;
            color: #fca5a5 !important;
            border-color: #991b1b !important;
        }

        /* Batasi tinggi HANYA pada isi tabel (th sampai td) dan buat scrollable */
        .fi-ta-content {
            max-height: 500px;
            overflow-y: auto !important;
            overflow-x: auto !important;
        }

        .fi-ta-content-ctn,
        .fi-ta-ctn {
            overflow-x: auto !important;
            overflow-y: visible !important;
            -webkit-overflow-scrolling: touch !important;
            padding-bottom: 6px !important;
        }

        .fi-ta-table {
            width: max-content !important;
            min-width: 100% !important;
        }

        .fi-ta-content-ctn::-webkit-scrollbar,
        .fi-ta-ctn::-webkit-scrollbar {
            height: 10px !important;
            width: 10px !important;
            display: block !important;
        }
        .fi-ta-content-ctn::-webkit-scrollbar-track,
        .fi-ta-ctn::-webkit-scrollbar-track {
            background: #e2e8f0 !important;
            border-radius: 6px !important;
        }
        .fi-ta-content-ctn::-webkit-scrollbar-thumb,
        .fi-ta-ctn::-webkit-scrollbar-thumb {
            background-color: #6366f1 !important;
            border-radius: 6px !important;
        }
        .fi-ta-content-ctn::-webkit-scrollbar-thumb:hover,
        .fi-ta-ctn::-webkit-scrollbar-thumb:hover {
            background-color: #4f46e5 !important;
        }
    </style>

    <div style="margin-bottom: 1rem;">
        {{ $this->table }}
    </div>

    <!-- Beautiful auto-saving notes textarea -->
    <div class="mt-4 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-5 shadow-sm">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200 flex items-center gap-2">
                <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                </svg>
                Catatan / Keterangan Pesanan
            </h3>
            @if(in_array(auth()->user()->role, ['owner', 'admin']))
            <div wire:loading wire:target="notes" class="text-xs text-indigo-500 flex items-center gap-1.5 animate-pulse">
                <svg class="animate-spin h-3.5 w-3.5 text-indigo-500" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Menyimpan...
            </div>
            <div wire:loading.remove wire:target="notes" class="text-xs text-gray-400 flex items-center gap-1">
                <svg class="w-3.5 h-3.5 text-emerald-500" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                </svg>
                Tersimpan Otomatis
            </div>
            @else
            <div class="text-xs text-gray-400 flex items-center gap-1">
                <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                </svg>
                Hanya Lihat
            </div>
            @endif
        </div>
        <textarea 
            wire:model.live.debounce.1000ms="notes"
            @if(!in_array(auth()->user()->role, ['owner', 'admin'])) readonly @endif
            placeholder="Tulis catatan penting mengenai pengerjaan pesanan ini (misal: detail request bordir khusus, toleransi ukuran, dll)..."
            rows="3"
            style="padding: 8px !important; line-height: 1.6;"
            class="w-full text-sm rounded-lg border border-gray-300 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-900/50 focus:border-indigo-500 focus:ring-indigo-500 dark:text-gray-300 placeholder-gray-400 dark:placeholder-gray-600 transition-colors {{ !in_array(auth()->user()->role, ['owner', 'admin']) ? 'opacity-80 cursor-not-allowed' : '' }}"
        ></textarea>
    </div>

    <x-filament-actions::modals />

</div>
