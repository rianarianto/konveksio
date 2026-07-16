<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Kuitansi {{ $order->order_number }}</title>
    <style>
        body {
            font-family: 'Helvetica', sans-serif;
            color: #333;
            line-height: 1.5;
            margin: 0;
            padding: 0;
            font-size: 12px;
        }

        .container {
            padding: 30px;
        }

        .header {
            border-bottom: 2px solid #7F00FF;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }

        .shop-name {
            font-size: 24px;
            font-weight: bold;
            color: #7F00FF;
            margin: 0;
        }

        .shop-info {
            color: #666;
            margin-top: 5px;
        }

        .title-box {
            text-align: right;
            float: right;
        }

        .receipt-title {
            font-size: 28px;
            font-weight: bold;
            color: #ddd;
            text-transform: uppercase;
            margin-top: -10px;
        }

        .info-table {
            width: 100%;
            margin-bottom: 30px;
        }

        .info-table td {
            vertical-align: top;
            width: 50%;
        }

        .info-label {
            color: #888;
            font-size: 10px;
            text-transform: uppercase;
            font-weight: bold;
            margin-bottom: 2px;
        }

        .info-value {
            font-size: 13px;
            font-weight: bold;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }

        .items-table th {
            background: #f8f9fa;
            border-bottom: 2px solid #eee;
            padding: 12px 10px;
            text-align: left;
            text-transform: uppercase;
            font-size: 10px;
            color: #666;
        }

        .items-table td {
            padding: 12px 10px;
            border-bottom: 1px solid #eee;
            vertical-align: top;
        }

        .item-name {
            font-weight: bold;
            font-size: 13px;
        }

        .item-details {
            font-size: 11px;
            color: #777;
            margin-top: 4px;
        }

        .item-badge {
            display: inline-block;
            padding: 2px 6px;
            background: #f3eeff;
            color: #7c3aed;
            border-radius: 4px;
            font-size: 9px;
            font-weight: bold;
            margin-top: 4px;
        }

        .totals-table {
            width: 100%;
            margin-top: 20px;
        }

        .totals-table td {
            padding: 4px 0;
        }

        .totals-label {
            text-align: right;
            padding-right: 20px;
            color: #666;
        }

        .totals-value {
            text-align: right;
            width: 120px;
            font-weight: bold;
        }

        .grand-total {
            font-size: 18px;
            color: #7F00FF;
            border-top: 1px solid #7F00FF;
            padding-top: 10px;
            margin-top: 10px;
        }

        .payments-section {
            margin-top: 40px;
        }

        .section-title {
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            color: #888;
            margin-bottom: 10px;
            border-bottom: 1px solid #eee;
            padding-bottom: 5px;
        }

        .payment-row {
            padding: 8px 0;
            border-bottom: 1px dashed #eee;
        }

        .footer {
            margin-top: 50px;
            text-align: center;
            color: #999;
            font-size: 10px;
        }

        .clearfix::after {
            content: "";
            clear: both;
            display: table;
        }

        /* Annex (Lampiran Spesifikasi) Styles */
        .page-break {
            page-break-before: always;
        }
        .annex-card {
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 12px 16px;
            margin-bottom: 15px;
            background: #fafafa;
        }
        .annex-card-title {
            font-size: 12px;
            font-weight: bold;
            color: #111;
            margin-bottom: 8px;
            background: #f3eeff;
            padding: 5px 10px;
            border-radius: 4px;
            border-left: 3px solid #7F00FF;
        }
        .annex-grid {
            width: 100%;
            margin-bottom: 5px;
            border-collapse: collapse;
        }
        .annex-grid td {
            padding: 4px 0;
            vertical-align: top;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header clearfix">
            <div style="float: left;">
                <h1 class="shop-name">{{ $order->shop->name }}</h1>
                <div class="shop-info">
                    {{ $order->shop->address }}<br>
                    Telp: {{ $order->shop->phone }}
                </div>
            </div>
            <div class="title-box">
                <div class="receipt-title">Kuitansi</div>
                <div class="info-value" style="color: #7F00FF; font-size: 16px;">
                    @if($order->is_express)
                        <span style="color: #d12; font-weight: 900; font-size: 14px;">EXPRESS</span>
                    @endif
                    {{ $order->order_number }}
                </div>
            </div>
        </div>

        <table class="info-table">
            <tr>
                <td>
                    <div class="info-label">Pelanggan</div>
                    <div class="info-value">{{ $order->customer->name }}</div>
                    <div class="shop-info">{{ $order->customer->phone }}</div>
                </td>
                <td style="text-align: right;">
                    <div class="info-label">Tanggal Pesanan</div>
                    <div class="info-value">{{ $order->order_date->format('d F Y') }}</div>
                    <div class="info-label" style="margin-top: 10px;">Batas Waktu (Deadline)</div>
                    <div class="info-value" style="color: #d12; ">{{ $order->deadline->format('d F Y') }}</div>
                </td>
            </tr>
        </table>

        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 40px;">No</th>
                    <th>Deskripsi Produk</th>
                    <th style="text-align: center; width: 60px;">Jumlah</th>
                    <th style="text-align: right; width: 100px;">Harga Satuan</th>
                    <th style="text-align: right; width: 120px;">Total</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $groupedItems = $order->orderItems->groupBy('product_name');
                    $index = 0;
                @endphp

                @foreach($groupedItems as $productName => $itemsGroup)
                    @php
                        $index++;
                        $totalQty = $itemsGroup->sum('quantity');
                        $totalPrice = $itemsGroup->sum(fn($i) => $i->price * $i->quantity);
                        
                        // Collect all sizes in this group
                        $sizeBreakdown = [];
                        $materials = [];
                        $catalogProducts = [];
                        $colors = [];
                        $categories = [];

                        foreach ($itemsGroup as $item) {
                            $sz = $item->size ?: '-';
                            if ($sz === 'Custom') {
                                $sz = 'Ukur Badan';
                            }
                            
                            // Hanya masukkan ke breakdown jika bukan Jasa atau jika ada ukuran nyata
                            if ($item->production_category !== 'jasa' || ($sz !== '-' && $sz !== '')) {
                                $sizeBreakdown[$sz] = ($sizeBreakdown[$sz] ?? 0) + $item->quantity;
                            }
                            
                            if ($item->bahan_id) {
                                $bahanName = \App\Models\Material::find($item->bahan_id)?->name ?? 'Bahan';
                                $materials[$bahanName] = true;
                            }

                            // Ambil data Katalog & Warna (untuk Baju Jadi/Konveksi)
                            $details = $item->size_and_request_details ?? [];
                            $variantId = $details['product_variant_id'] ?? $details['material_variant_id'] ?? null;
                            if ($variantId) {
                                if ($item->production_category === 'non_produksi') {
                                    $v = \App\Models\ProductVariant::with('product')->find($variantId);
                                    if ($v?->product) $catalogProducts[$v->product->name] = true;
                                    if ($v?->color_name) $colors[$v->color_name] = true;
                                } else {
                                    $v = \App\Models\MaterialVariant::find($variantId);
                                    if ($v?->color_name) $colors[$v->color_name] = true;
                                }
                            }

                            $cat = match ($item->production_category) {
                                'custom' => 'Konveksi',
                                'non_produksi' => 'Non-Produksi',
                                'jasa' => 'Jasa',
                                default => 'Konveksi'
                            };
                            $categories[$cat] = true;
                        }

                        $sizeStrings = [];
                        foreach ($sizeBreakdown as $sz => $q) {
                            $sizeStrings[] = $sz . ": " . $q;
                        }
                        $sizeSummary = implode(', ', $sizeStrings);
                        $materialSummary = implode(', ', array_keys($materials));
                        $catalogSummary = implode(', ', array_keys($catalogProducts));
                        $colorSummary = implode(', ', array_keys($colors));
                        $categorySummary = implode(', ', array_keys($categories));
                        
                        $unitPrice = $itemsGroup->first()->price;
                    @endphp
                    <tr>
                        <td style="text-align: center; color: #888;">{{ $index }}</td>
                        <td>
                            <div class="item-name">{{ $productName }}</div>
                            <div class="item-badge">{{ $categorySummary }}</div>
                            <div class="item-details">
                                @if($catalogSummary)
                                    <div>Produk: {{ $catalogSummary }}</div>
                                @endif
                                @if($materialSummary)
                                    <div>Bahan: {{ $materialSummary }}</div>
                                @endif
                                @if($colorSummary)
                                    <div>Warna: {{ $colorSummary }}</div>
                                @endif
                                @if($sizeSummary)
                                    <div>Ukuran: {{ $sizeSummary }}</div>
                                @endif
                            </div>
                        </td>
                        <td style="text-align: center; font-weight: bold;">{{ $totalQty }}</td>
                        <td style="text-align: right;">Rp {{ number_format($unitPrice, 0, ',', '.') }}</td>
                        <td style="text-align: right; font-weight: bold; color: #7F00FF;">
                            Rp {{ number_format($totalPrice, 0, ',', '.') }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div>
            <div style="width: 100%; margin-bottom: 30px;">
                <div class="payments-section">
                    <div class="section-title">Riwayat Pembayaran</div>
                    @forelse($order->payments as $pay)
                        <div class="payment-row clearfix">
                            <span style="color: #666;">{{ $pay->payment_date->format('d/m/Y') }} -
                                {{ ucwords($pay->payment_method) }}</span>
                            <span style="float: right; font-weight: bold;">Rp
                                {{ number_format($pay->amount, 0, ',', '.') }}</span>
                        </div>
                    @empty
                        <div style="color: #999; font-style: italic; font-size: 11px;">Belum ada pembayaran yang tercatat.
                        </div>
                    @endforelse
                </div>
            </div>

            <div style="width: 100%; display: flex; justify-content: flex-end;">
                <table class="totals-table" style="width: 40%; margin-left: auto;">
                    <tr>
                        <td class="totals-label">Subtotal</td>
                        <td class="totals-value">Rp {{ number_format($order->subtotal, 0, ',', '.') }}</td>
                    </tr>
                    @if($order->is_express)
                        <tr>
                            <td class="totals-label" style="color: #d12;">Layanan Express ⚡</td>
                            <td class="totals-value" style="color: #d12;">{{ $order->express_fee > 0 ? 'Rp ' . number_format($order->express_fee, 0, ',', '.') : 'GRATIS' }}</td>
                        </tr>
                    @endif
                    @if($order->shipping_cost > 0)
                        <tr>
                            <td class="totals-label">Ongkos Kirim</td>
                            <td class="totals-value">Rp {{ number_format($order->shipping_cost, 0, ',', '.') }}</td>
                        </tr>
                    @endif
                    @if($order->tax > 0)
                        <tr>
                            <td class="totals-label">Pajak (PPn)</td>
                            <td class="totals-value">Rp {{ number_format($order->tax, 0, ',', '.') }}</td>
                        </tr>
                    @endif
                    @if($order->discount > 0)
                        <tr>
                            <td class="totals-label" style="color: #d12;">Diskon (-)</td>
                            <td class="totals-value" style="color: #d12;">Rp
                                {{ number_format($order->discount, 0, ',', '.') }}
                            </td>
                        </tr>
                    @endif
                    <tr class="grand-total">
                        <td class="totals-label" style="font-weight: bold; color: #7F00FF;">Total Keseluruhan</td>
                        <td class="totals-value" style="font-size: 16px;">Rp
                            {{ number_format($order->total_price, 0, ',', '.') }}
                        </td>
                    </tr>
                    <tr>
                        <td class="totals-label">Sudah Dibayar</td>
                        <td class="totals-value" style="color: #22c55e;">Rp
                            {{ number_format($order->total_paid, 0, ',', '.') }}
                        </td>
                    </tr>
                    <tr>
                        <td class="totals-label">Sisa Tagihan</td>
                        <td class="totals-value"
                            style="color: {{ $order->remaining_balance > 0 ? '#7F00FF' : '#22c55e' }}; font-size: 14px;">
                            {{ $order->remaining_balance > 0 ? 'Rp ' . number_format($order->remaining_balance, 0, ',', '.') : 'LUNAS' }}
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="footer">
            Terima kasih telah mempercayakan pesanan Anda kepada <strong>{{ $order->shop->name }}</strong>.<br>
            Kuitansi ini adalah bukti pembayaran yang sah.
        </div>
    </div>

    @php
        $receiptSpecGroups = [];
        foreach ($order->orderItems as $item) {
            $d = $item->size_and_request_details ?? [];
            $isProduksi = in_array($item->production_category ?? 'produksi', ['produksi', 'custom']);
            
            if (!$isProduksi) {
                $gen = 'UMUM';
                $slv = '-';
                $pck = '-';
                $btn = '-';
                $tun = '-';
                $clr = '-';
            } else {
                $gen = $d['gender'] ?? 'L';
                $slv = strtoupper($d['sleeve_model'] ?? 'PENDEK');
                $pck = strtoupper(str_replace('_', ' ', $d['pocket_model'] ?? 'TANPA SAKU'));
                $btn = strtoupper($d['button_model'] ?? 'BIASA');
                $tun = !empty($d['is_tunic']) ? 'TUNIK' : 'STANDAR';
                $clr = strtoupper($d['collar_model'] ?? 'BIASA');
            }
            
            $sbList = [];
            if (!empty($d['sablon_bordir'])) {
                foreach ($d['sablon_bordir'] as $sb) {
                    $ket = !empty($sb['keterangan']) ? " - " . strtoupper($sb['keterangan']) : "";
                    $sbList[] = strtoupper($sb['jenis'] ?? '') . " (" . strtoupper($sb['lokasi'] ?? '') . $ket . ")";
                }
            } elseif (!empty($d['sablon_jenis'])) {
                $ket = !empty($d['sablon_keterangan']) ? " - " . strtoupper($d['sablon_keterangan']) : "";
                $sbList[] = strtoupper($d['sablon_jenis']) . " (" . strtoupper($d['sablon_lokasi'] ?? '-') . $ket . ")";
            }
            sort($sbList);
            $sbStr = implode(' | ', $sbList);
            
            $reqList = [];
            if (!empty($d['request_tambahan']) && is_array($d['request_tambahan'])) {
                foreach ($d['request_tambahan'] as $rt) {
                    $reqList[] = strtoupper($rt['jenis'] ?? '') . ": " . ($rt['keterangan'] ?? '');
                }
            }
            sort($reqList);
            $reqStr = implode(' | ', $reqList);

            $gen = trim($gen);
            $slv = trim($slv);
            $pck = trim($pck);
            $btn = trim($btn);
            $tun = trim($tun);
            $sbStr = trim($sbStr);
            $reqStr = trim($reqStr);

            $groupKey = "{$item->product_name}|{$gen}|{$slv}|{$pck}|{$btn}|{$tun}|{$clr}|{$sbStr}|{$reqStr}";
            
            if (!isset($receiptSpecGroups[$groupKey])) {
                $receiptSpecGroups[$groupKey] = [
                    'product_name' => $item->product_name,
                    'production_category' => $item->production_category,
                    'gender' => $gen === 'UMUM' ? 'UMUM' : ($gen === 'L' ? 'LAKI-LAKI' : 'PEREMPUAN'),
                    'is_produksi' => $isProduksi,
                    'sleeve' => $slv,
                    'pocket' => $pck,
                    'button' => $btn,
                    'tunic' => $tun,
                    'collar' => $clr,
                    'sablon_bordir' => $sbList,
                    'requests' => $reqList,
                    'total_qty' => 0,
                    'sizes' => [],
                    'bahan' => $item->bahan ? ($item->bahan->material->name . ' - ' . $item->bahan->color_name) : null,
                    'recipients' => ['standard' => [], 'custom' => []],
                ];
            }
            
            $receiptSpecGroups[$groupKey]['total_qty'] += $item->quantity;
            $sz = strtoupper($item->size ?? 'TANPA_UKURAN');
            $receiptSpecGroups[$groupKey]['sizes'][$sz] = ($receiptSpecGroups[$groupKey]['sizes'][$sz] ?? 0) + $item->quantity;

            if (!empty($d['detail_custom'])) {
                foreach ($d['detail_custom'] as $u) {
                    $receiptSpecGroups[$groupKey]['recipients']['custom'][] = [
                        'nama' => $u['nama'] ?? '-',
                        'size' => $u['ukuran'] ?? 'Custom',
                        'desc' => !empty($u['LD']) ? "LD:{$u['LD']} PB:{$u['PB']} PL:{$u['PL']} LB:{$u['LB']} LP:{$u['LP']} LPh:{$u['LPh']}" : ""
                    ];
                }
            } elseif ($sz === 'CUSTOM') {
                $m = [];
                foreach (['LD', 'PB', 'PL', 'LB', 'LP', 'LPh'] as $mk) {
                    if (!empty($d[$mk])) $m[] = "$mk:{$d[$mk]}";
                }
                $receiptSpecGroups[$groupKey]['recipients']['custom'][] = [
                    'nama' => $item->recipient_name ?? '-',
                    'size' => 'Custom',
                    'desc' => implode(' ', $m)
                ];
            } elseif (!empty($item->recipient_name)) {
                $receiptSpecGroups[$groupKey]['recipients']['standard'][$sz][] = $item->recipient_name;
            }
        }
    @endphp

    @if(!empty($receiptSpecGroups))
    <div class="page-break"></div>
    <div class="container">
        <div class="header clearfix">
            <div style="float: left;">
                <h1 class="shop-name">{{ $order->shop->name }}</h1>
                <div class="shop-info">LAMPIRAN SPESIFIKASI PESANAN</div>
            </div>
            <div class="title-box">
                <div class="info-value" style="color: #7F00FF; font-size: 16px;">
                    Order #{{ $order->order_number }}
                </div>
            </div>
        </div>

        <div style="font-size: 11px; color: #666; margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 10px;">
            Lampiran ini berisi rincian spesifikasi produk, bahan, warna, dan ukuran resmi yang telah dikonfirmasi oleh customer untuk diproduksi.
        </div>

        @foreach($receiptSpecGroups as $group)
            <div class="annex-card">
                <div class="annex-card-title">
                    {{ strtoupper($group['product_name']) }} ({{ $group['total_qty'] }} pcs)
                </div>
                
                <table class="annex-grid" style="font-size: 10px; line-height: 1.5;">
                    <tr>
                        <td style="width: 130px; color: #666; font-weight: bold;">Kategori</td>
                        <td>: {{ in_array($group['production_category'], ['produksi', 'custom']) ? 'Konveksi' : ($group['production_category'] === 'non_produksi' ? 'Non-Produksi (Katalog)' : 'Jasa Makloon') }}</td>
                    </tr>
                    @if($group['bahan'])
                    <tr>
                        <td style="color: #666; font-weight: bold;">Bahan & Warna Utama</td>
                        <td>: <strong>{{ $group['bahan'] }}</strong></td>
                    </tr>
                    @endif
                    @if($group['is_produksi'])
                    <tr>
                        <td style="color: #666; font-weight: bold;">Model & Potongan</td>
                        <td>
                            : Gender/JK: {{ $group['gender'] }} | Lengan: {{ $group['sleeve'] }} | Saku: {{ $group['pocket'] }} | Kancing: {{ $group['button'] }} | Kerah: {{ $group['collar'] }}
                        </td>
                    </tr>
                    @endif
                    @if(!empty($group['sablon_bordir']))
                    <tr>
                        <td style="color: #666; font-weight: bold;">Sablon / Bordir</td>
                        <td>: {{ implode(' | ', $group['sablon_bordir']) }}</td>
                    </tr>
                    @endif
                    @if(!empty($group['requests']))
                    <tr>
                        <td style="color: #666; font-weight: bold;">Request Khusus</td>
                        <td>: {{ implode(' | ', $group['requests']) }}</td>
                    </tr>
                    @endif
                    <tr>
                        <td style="color: #666; font-weight: bold;">Rincian Ukuran</td>
                        <td>
                            : 
                            @foreach($group['sizes'] as $sz => $q)
                                <strong>{{ $sz }}</strong> ({{ $q }} pcs){{ !$loop->last ? ', ' : '' }}
                            @endforeach
                        </td>
                    </tr>
                    
                    @if(!empty($group['recipients']['custom']))
                    <tr>
                        <td style="color: #666; font-weight: bold; vertical-align: top; padding-top: 5px;">Rincian Ukuran Custom</td>
                        <td style="padding-top: 5px;">
                            <table style="width: 100%; border: none; font-size: 9.5px; border-collapse: collapse;">
                                @foreach($group['recipients']['custom'] as $cust)
                                    <tr>
                                        <td style="border: none; padding: 2px 0; color: #333;">
                                            • <strong>{{ $cust['nama'] }}</strong> ({{ $cust['size'] }}): <span style="font-family: monospace; font-size: 9px; color: #555;">{{ $cust['desc'] }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </table>
                        </td>
                    </tr>
                    @endif
                </table>
            </div>
        @endforeach
    </div>
    @endif
</body>

</html>