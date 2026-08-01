@php
    $record = $getRecord();
@endphp
@if($record && $record->size === 'Custom')
    @php
        $escapedName = e($record->recipient_name);
    @endphp
    <div class="inline-flex items-center rounded-lg border border-amber-300 dark:border-amber-700 bg-amber-50/60 dark:bg-amber-950/30 overflow-hidden shadow-sm transition-all hover:border-amber-400" onclick="event.stopPropagation();" wire:key="rec-name-container-{{ $record->id }}" wire:ignore.self>
        <span class="inline-flex items-center gap-1 text-xs font-bold text-amber-800 dark:text-amber-300 px-2.5 py-1 select-none whitespace-nowrap bg-amber-100/70 dark:bg-amber-900/40 border-r border-amber-300 dark:border-amber-700">
            🧵 Custom:
        </span>
        <input 
            wire:key="rec-name-input-{{ $record->id }}"
            type="text" 
            value="{{ $escapedName }}" 
            placeholder="Nama Penerima..."
            wire:change="updateRecipientName({{ $record->id }}, $event.target.value)"
            class="px-2.5 py-1 text-xs font-semibold text-gray-800 dark:text-gray-200 bg-white dark:bg-gray-800 outline-none border-none focus:ring-1 focus:ring-amber-500 w-40 transition-colors"
            style="height: 28px;"
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
