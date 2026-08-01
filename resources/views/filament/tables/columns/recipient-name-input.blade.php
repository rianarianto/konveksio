@php
    $record = $getRecord();
@endphp
@if($record && $record->size === 'Custom')
    @php
        $escapedName = e($record->recipient_name);
    @endphp
    <div class="flex items-center gap-2" onclick="event.stopPropagation();" wire:key="rec-name-container-{{ $record->id }}" wire:ignore.self>
        <span class="inline-flex items-center text-xs font-semibold text-gray-500 dark:text-gray-400 bg-gray-100 dark:bg-gray-800 px-2 py-1 rounded-md border border-gray-200 dark:border-gray-700 whitespace-nowrap">
            🧵 Ukur Badan (Custom)
        </span>
        <input 
            wire:key="rec-name-input-{{ $record->id }}"
            type="text" 
            value="{{ $escapedName }}" 
            placeholder="Nama Penerima..."
            wire:change="updateRecipientName({{ $record->id }}, $event.target.value)"
            class="px-2.5 text-xs font-medium rounded-lg border border-gray-300 dark:border-gray-700 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-200 outline-none w-44 shadow-sm"
            style="height: 28px;"
        />
    </div>
@elseif($record)
    @php
        $badgeText = $record->production_category === 'non_produksi' ? '📦 Standar (Bawaan Produk)' : '📦 Standar (Size Toko)';
    @endphp
    <span class="inline-flex items-center text-xs font-semibold text-gray-500 dark:text-gray-400 bg-gray-100 dark:bg-gray-800 px-2 py-1 rounded-md border border-gray-200 dark:border-gray-700">
        {{ $badgeText }}
    </span>
@endif
