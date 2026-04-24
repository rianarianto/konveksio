@php
    $labels = [];
    
    // Logic from the component
    if (in_array('product_name', $selectedGroups)) {
        $labels[] = "📦 " . ($record->product_name ?: 'Produk Tanpa Nama');
    }

    if (in_array('category', $selectedGroups)) {
        $cat = $record->production_category === 'custom' ? 'produksi' : $record->production_category;
        $catLabel = match ($cat) {
            'produksi' => '🏭 Konveksi',
            'non_produksi' => '📦 Baju Jadi',
            'jasa' => '🔧 Jasa',
            default => $cat,
        };
        $labels[] = $catLabel;
    }

    if (in_array('gender', $selectedGroups)) {
        $gender = $record->size_and_request_details['gender'] ?? 'L';
        $labels[] = $gender === 'P' ? '👩 Perempuan' : '👨 Laki-laki';
    }

    if (in_array('bahan', $selectedGroups)) {
        $labels[] = "🧶 " . ($record->bahan_name ?: 'Tanpa Bahan');
    }

    if (in_array('size', $selectedGroups)) {
        $labels[] = "📏 Size: " . ($record->size ?: '-');
    }

    if (in_array('recipient', $selectedGroups)) {
        $labels[] = "👤 Recipient: " . ($record->recipient_name ?: '-');
    }

    $gender = $record->size_and_request_details['gender'] ?? 'L';
    $isFemale = $gender === 'P';
    
    $bgColor = $isFemale ? 'bg-rose-50' : 'bg-sky-50';
    $textColor = $isFemale ? 'text-rose-700' : 'text-sky-700';
    $borderColor = $isFemale ? 'border-rose-200' : 'border-sky-200';
@endphp

<div class="flex items-center gap-2 px-3 py-1 rounded-lg border {{ $bgColor }} {{ $textColor }} {{ $borderColor }} shadow-sm inline-flex text-sm font-medium">
    @foreach($labels as $label)
        <span>{!! $label !!}</span>
        @if(!$loop->last)
            <span class="opacity-30 px-1">/</span>
        @endif
    @endforeach
</div>
