@php
    $record = $orderRecord;
    $fmt = fn(int $v) => 'Rp ' . number_format($v, 0, ',', '.');
    
    // Group by product name for 1 row per product
    $items = $record->orderItems()
        ->select('product_name', 'production_category')
        ->selectRaw('SUM(quantity) as total_qty, SUM(price * quantity) as total_price')
        ->groupBy('product_name', 'production_category')
        ->get();
    
    $subtotal = 0;
    $hasItems = $items->isNotEmpty();
    $isExpress = (bool) $record->is_express;
@endphp

<div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-100 dark:border-gray-800">
    <div class="max-w-full space-y-10" style="padding: 30px 30px;">
        <!-- Header Ringkasan -->
        <div class="flex justify-between items-start border-b border-gray-100 pb-6 px-2">
            <div>
                <h3 class="text-2xl font-black text-gray-900 dark:text-white tracking-tight uppercase">Ringkasan Pesanan</h3>
                <p class="text-sm text-gray-500 font-bold tracking-widest mt-1 mb-2">{{ $record->order_number }}</p>
            </div>
            @if ($isExpress)
                <div style="display:inline-flex; align-items:center; gap:6px; padding:6px 16px; background:#dc2626; color:white; border-radius:9999px; font-size:12px; font-weight:800; box-shadow:0 4px 6px -1px rgba(220, 38, 38, 0.2);">
                    <svg style="width:14px; height:14px;" fill="currentColor" viewBox="0 0 24 24"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
                    EXPRESS
                </div>
            @endif
        </div>

        <!-- List Produk -->
        <div class="space-y-4">
            <h3 class="text-sm font-bold text-gray-400 uppercase tracking-widest mb-2 mt-2 px-2">Item Produk</h3>
            @foreach ($items as $item)
                @php
                    $name = htmlspecialchars($item->product_name ?: 'Produk');
                    $totalPrice = (int) $item->total_price;
                    $totalQty = (int) $item->total_qty;
                    $subtotal += $totalPrice;
                    
                    // Get size breakdown for this product
                    $sizes = $record->orderItems()
                        ->where('product_name', $item->product_name)
                        ->select('size')
                        ->selectRaw('SUM(quantity) as qty')
                        ->groupBy('size')
                        ->get()
                        ->map(fn($s) => ($s->size ?: '-') . ': ' . $s->qty)
                        ->implode(', ');

                    $cat = $item->production_category ?? 'produksi';
                    $badgeStyle = 'background:#f3e8ff;color:#7c3aed;font-size:11px;font-weight:700;padding:2px 8px;border-radius:6px;white-space:nowrap;';
                    $badgeLabel = match ($cat) {
                        'custom' => 'Konveksi',
                        'non_produksi' => 'Baju Jadi',
                        'jasa' => 'Jasa',
                        default => 'Konveksi',
                    };
                @endphp

                <div style="display:flex;align-items:center;gap:12px;padding:16px 24px;border:1.5px solid #e9d5ff;border-radius:14px;background:#faf5ff;width:100%;transition:all 0.2s;">
                    <span style="{{ $badgeStyle }} border: 1px solid #d8b4fe;">{{ $badgeLabel }}</span>
                    <div style="flex:1;min-width:0;">
                        <div style="font-size:15px;font-weight:600;color:#1f2937;">
                            {{ $totalQty }}x {{ $name }} 
                            <span style="font-size:13px;font-weight:400;color:#6b7280;margin-left:4px;">({{ $sizes }})</span>
                        </div>
                        <div style="font-size:14px;font-weight:700;color:#7c3aed;margin-top:2px;">{{ $fmt($totalPrice) }}</div>
                    </div>
                </div>
            @endforeach

            @if (!$hasItems)
                <p style="color:#9ca3af;font-size:14px;font-style:italic;" class="px-2">Belum ada produk ditambahkan</p>
            @endif
        </div>

        <!-- Rincian Biaya -->
        <div style="margin-top:32px;border-top:2px solid #f3f4f6;padding-top:24px;max-width:100%;" class="px-2">
            @php
                $shipping = (int) ($record->shipping_cost ?? 0);
                $tax = (int) ($record->tax ?? 0);
                $discount = (int) ($record->discount ?? 0);
                $expressFeeVal = (int) ($record->express_fee ?? 0);
                $total = (int) $record->total_price;
                $totalPaid = (int) $record->payments()->sum('amount');
                $remaining = $total - $totalPaid;

                $row = fn(string $label, string $value, bool $purple = false, bool $bold = false, string $fontSize = '14px') =>
                    '<div style="display:flex;justify-content:space-between;align-items:center;padding:6px 0;">'
                    . '<span style="color:#6b7280;font-size:'.$fontSize.';font-weight:500;">' . $label . '</span>'
                    . '<span style="font-size:'.$fontSize.';' . ($purple ? 'color:#7c3aed;' : 'color:#374151;') . ($bold ? 'font-weight:700;' : 'font-weight:600;') . '">' . $value . '</span>'
                    . '</div>';
            @endphp

            {!! $row('Subtotal Produk', $fmt($subtotal), true, true) !!}
            
            @if ($isExpress)
                {!! $row('Biaya Layanan Express', $expressFeeVal > 0 ? $fmt($expressFeeVal) : 'Rp 0 (Prioritas)', true) !!}
            @endif

            {!! $row('Ongkos Kirim', $fmt($shipping)) !!}
            {!! $row('PPn 11%', $fmt($tax)) !!}
            {!! $row('Potongan Diskon', '- ' . $fmt($discount), false, false) !!}

            <hr style="border:none;border-top:1.5px dashed #e5e7eb;margin:16px 0;">

            {!! $row('Total Tagihan', $fmt($total), true, true, '20px') !!}
            {!! $row('Total Telah Dibayar', $fmt($totalPaid), false, false, '15px') !!}

            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:20px;padding:20px 24px;background:#f3e8ff;border-radius:14px;border:1px solid #e9d5ff;">
                <span style="color:#333333;font-size:16px;font-weight:700;">Sisa Tagihan</span>
                @if ($remaining <= 0)
                    <span style="background:#22c55e;color:white;font-size:13px;font-weight:800;padding:6px 16px;border-radius:20px;text-transform:uppercase;letter-spacing:0.05em;box-shadow:0 2px 4px rgba(34,197,94,0.2);">Lunas</span>
                @else
                    <span style="color:#7c3aed;font-weight:900;font-size:26px;letter-spacing:-0.02em;">{{ $fmt($remaining) }}</span>
                @endif
            </div>

            <!-- Tombol Kuitansi -->
            <div style="margin-top:32px;">
                <a href="{{ route('orders.receipt', $record) }}" target="_blank" 
                   style="display:flex;align-items:center;justify-content:center;gap:10px;width:100%;padding:16px;background:#f9fafb;color:#4b5563;border:1.5px solid #e5e7eb;border-radius:14px;text-decoration:none;font-weight:700;font-size:14px;transition:all 0.2s;box-shadow:0 1px 2px rgba(0,0,0,0.05);">
                    <svg style="width:18px;height:18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 012-2H5a2 2 0 012 2v4a2 2 0 002 2m8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v7h10z"></path></svg>
                    CETAK KUITANSI RESMI
                </a>
            </div>
        </div>
    </div>
</div>
