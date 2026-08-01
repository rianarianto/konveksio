@php
    $record = $getRecord();
@endphp
@if($record && $record->size === 'Custom')
    @php
        $escapedName = e($record->recipient_name);
    @endphp
    <div class="flex flex-col gap-1 items-start" onclick="event.stopPropagation();" wire:key="rec-name-container-{{ $record->id }}" wire:ignore.self>
        <span class="inline-flex items-center text-xs font-semibold text-amber-700 dark:text-amber-300 bg-amber-50 dark:bg-amber-950/40 px-2 py-0.5 rounded-md border border-amber-200 dark:border-amber-800">
            🧵 Ukur Badan (Custom)
        </span>
        <input 
            wire:key="rec-name-input-{{ $record->id }}"
            type="text" 
            value="{{ $escapedName }}" 
            placeholder="Ketik nama penerima..."
            wire:change="updateRecipientName({{ $record->id }}, $event.target.value)"
            class="px-2 text-sm font-medium rounded-lg border border-gray-300 dark:border-gray-700 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-200 outline-none w-52 shadow-sm transition-colors"
            style="height: 32px;"
        />
    </div>
@elseif($record)
    @php
        $badgeText = $record->production_category === 'non_produksi' ? '📦 Standar (Bawaan Produk)' : '📦 Standar (Size Toko)';
    @endphp
    <span class="inline-flex items-center text-xs font-semibold text-indigo-700 dark:text-indigo-300 bg-indigo-50 dark:bg-indigo-950/40 px-2.5 py-1 rounded-md border border-indigo-200 dark:border-indigo-800">
        {{ $badgeText }}
    </span>
@endif
