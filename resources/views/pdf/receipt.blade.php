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
        $receiptProducts = [];
        foreach ($order->orderItems as $item) {
            $d = $item->size_and_request_details ?? [];
            $pName = $item->product_name ?: 'Produk Tanpa Nama';
            $isProduksi = in_array($item->production_category ?? 'produksi', ['produksi', 'custom']);
            
            if (!isset($receiptProducts[$pName])) {
                $receiptProducts[$pName] = [
                    'product_name' => $pName,
                    'production_category' => $item->production_category,
                    'bahan' => $item->bahan ? ($item->bahan->material->name . ' - ' . $item->bahan->color_name) : null,
                    'design_image' => $item->design_image,
                    'total_qty' => 0,
                    'spec_groups' => [],
                ];
            }

            if (empty($receiptProducts[$pName]['design_image']) && !empty($item->design_image)) {
                $receiptProducts[$pName]['design_image'] = $item->design_image;
            }
            
            $receiptProducts[$pName]['total_qty'] += $item->quantity;

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
            
            $groupLabel = $d['group_label'] ?? null;
            $specNotes = $d['spec_notes'] ?? null;

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

            $groupKey = "{$groupLabel}|{$gen}|{$slv}|{$pck}|{$btn}|{$tun}|{$clr}|{$sbStr}|{$reqStr}|{$specNotes}";
            
            if (!isset($receiptProducts[$pName]['spec_groups'][$groupKey])) {
                $receiptProducts[$pName]['spec_groups'][$groupKey] = [
                    'group_label' => $groupLabel,
                    'gender' => $gen === 'UMUM' ? 'UMUM' : ($gen === 'L' ? 'LAKI-LAKI' : 'PEREMPUAN'),
                    'is_produksi' => $isProduksi,
                    'sleeve' => $slv,
                    'pocket' => $pck,
                    'button' => $btn,
                    'tunic' => $tun,
                    'collar' => $clr,
                    'spec_notes' => $specNotes,
                    'sablon_bordir' => $sbList,
                    'requests' => $reqList,
                    'total_qty' => 0,
                    'sizes' => [],
                    'recipients' => ['standard' => [], 'custom' => []],
                ];
            }
            
            $receiptProducts[$pName]['spec_groups'][$groupKey]['total_qty'] += $item->quantity;
            $sz = strtoupper($item->size ?? 'TANPA_UKURAN');
            $receiptProducts[$pName]['spec_groups'][$groupKey]['sizes'][$sz] = ($receiptProducts[$pName]['spec_groups'][$groupKey]['sizes'][$sz] ?? 0) + $item->quantity;

            if (!empty($d['detail_custom'])) {
                foreach ($d['detail_custom'] as $u) {
                    $receiptProducts[$pName]['spec_groups'][$groupKey]['recipients']['custom'][] = [
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
                $receiptProducts[$pName]['spec_groups'][$groupKey]['recipients']['custom'][] = [
                    'nama' => $item->recipient_name ?? '-',
                    'size' => 'Custom',
                    'desc' => implode(' ', $m)
                ];
            } elseif (!empty($item->recipient_name)) {
                $receiptProducts[$pName]['spec_groups'][$groupKey]['recipients']['standard'][$sz][] = $item->recipient_name;
            }
        }
    @endphp

    @if(!empty($receiptProducts))
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

        @foreach($receiptProducts as $product)
            @if(($product['total_qty'] ?? 0) <= 0) @continue @endif
            <div class="annex-card" style="border: 2px solid #7F00FF; border-radius: 10px; padding: 14px; margin-bottom: 20px; background: #fff;">
                {{-- Main Product Header --}}
                <div class="annex-card-title" style="font-size: 14px; font-weight: bold; color: #7F00FF; background: #F3EEFF; padding: 8px 12px; border-radius: 6px; border-left: 4px solid #7F00FF; margin-bottom: 12px; display: flex; justify-content: space-between; align-items: center;">
                    <span>PRODUK: {{ strtoupper($product['product_name']) }}</span>
                    <span style="font-size: 12px; font-weight: normal; color: #6B21A8; background: #E9D5FF; padding: 2px 8px; border-radius: 4px;">Total Produk: {{ $product['total_qty'] }} pcs</span>
                </div>
                
                {{-- Shared Product Overview --}}
                <table class="annex-grid" style="font-size: 10px; width: 100%; border-collapse: collapse; margin-bottom: 14px; background: #F9FAFB; border: 1px solid #F3F4F6;">
                    <tr style="border-bottom: 1px solid #E5E7EB;">
                        <td style="width: 140px; color: #6B7280; font-weight: bold; padding: 6px 8px;">Kategori Produk</td>
                        <td style="padding: 6px 8px; color: #111827; font-weight: 600;">{{ in_array($product['production_category'], ['produksi', 'custom']) ? 'Konveksi' : ($product['production_category'] === 'non_produksi' ? 'Non-Produksi (Katalog)' : 'Jasa Makloon') }}</td>
                    </tr>
                    @if($product['bahan'])
                    <tr>
                        <td style="color: #6B7280; font-weight: bold; padding: 6px 8px;">Bahan & Warna Utama</td>
                        <td style="padding: 6px 8px; color: #111827; font-weight: bold;">{{ $product['bahan'] }}</td>
                    </tr>
                    @endif
                </table>

                {{-- Specification Sub-Sections --}}
                @foreach($product['spec_groups'] as $group)
                    @if(($group['total_qty'] ?? 0) <= 0) @continue @endif
                    @php
                        $groupLabelTitle = !empty($group['group_label']) 
                            ? strtoupper($group['group_label']) 
                            : ($group['gender'] === 'LAKI-LAKI' ? 'SPESIFIKASI PRIA' : ($group['gender'] === 'PEREMPUAN' ? 'SPESIFIKASI WANITA' : 'SPESIFIKASI PRODUK'));

                        $modelParts = [];
                        if ($group['is_produksi']) {
                            if ($group['gender'] === 'LAKI-LAKI') $modelParts[] = 'Pria';
                            elseif ($group['gender'] === 'PEREMPUAN') $modelParts[] = 'Wanita';
                            
                            if ($group['sleeve'] && $group['sleeve'] !== '-') {
                                $modelParts[] = 'Lengan ' . ucwords(strtolower(str_replace('_', ' ', $group['sleeve'])));
                            }
                            if ($group['collar'] && $group['collar'] !== '-') {
                                $modelParts[] = 'Kerah ' . ucwords(strtolower(str_replace('_', ' ', $group['collar'])));
                            }
                            if ($group['pocket'] && $group['pocket'] !== '-') {
                                $modelParts[] = ucwords(strtolower(str_replace('_', ' ', $group['pocket'])));
                            }
                            if ($group['button'] && $group['button'] !== '-') {
                                $modelParts[] = 'Kancing ' . ucwords(strtolower(str_replace('_', ' ', $group['button'])));
                            }
                            if ($group['tunic'] === 'TUNIK') {
                                $modelParts[] = 'Model Tunik';
                            }
                        }
                        $modelStr = implode('  ·  ', $modelParts);
                    @endphp

                    <div style="border: 1px solid #E5E7EB; border-radius: 6px; padding: 10px; margin-bottom: 12px; background: #FAFAFA; page-break-inside: avoid;">
                        <div style="font-size: 11px; font-weight: bold; color: #4C1D95; background: #EDE9FE; padding: 4px 8px; border-radius: 4px; border-left: 3px solid #7C3AED; margin-bottom: 8px;">
                            {{ $groupLabelTitle }} ({{ $group['total_qty'] }} pcs)
                        </div>

                        <table class="annex-grid" style="font-size: 10px; width: 100%; border-collapse: collapse; margin-bottom: 8px;">
                            @if(!empty($modelStr))
                            <tr style="border-bottom: 1px solid #E5E7EB;">
                                <td style="width: 130px; color: #6B7280; font-weight: bold; padding: 3px 0;">Model & Potongan</td>
                                <td style="padding: 3px 0; color: #111827; font-weight: 500;">{{ $modelStr }}</td>
                            </tr>
                            @endif
                            @if(!empty($group['sablon_bordir']))
                            <tr style="border-bottom: 1px solid #E5E7EB;">
                                <td style="color: #6B7280; font-weight: bold; padding: 3px 0;">Sablon / Bordir</td>
                                <td style="padding: 3px 0; color: #111827;">{{ implode(', ', $group['sablon_bordir']) }}</td>
                            </tr>
                            @endif
                            @if(!empty($group['spec_notes']))
                            <tr style="border-bottom: 1px solid #E5E7EB;">
                                <td style="color: #6B7280; font-weight: bold; padding: 3px 0;">Catatan Spesifikasi</td>
                                <td style="padding: 3px 0; color: #111827; font-style: italic;">{{ $group['spec_notes'] }}</td>
                            </tr>
                            @endif
                            @if(!empty($group['requests']))
                            <tr style="border-bottom: 1px solid #E5E7EB;">
                                <td style="color: #6B7280; font-weight: bold; padding: 3px 0;">Request Khusus</td>
                                <td style="padding: 3px 0; color: #111827;">{{ implode(', ', $group['requests']) }}</td>
                            </tr>
                            @endif
                        </table>

                        <!-- Rincian Ukuran Table -->
                        <div style="font-size: 9.5px; color: #6B7280; font-weight: bold; margin-bottom: 4px;">Rincian Ukuran & Jumlah :</div>
                        <table style="width: 100%; border-collapse: collapse; font-size: 9.5px; border: 1px solid #E5E7EB; text-align: center; margin-bottom: 8px; background: #fff;">
                            <thead>
                                <tr style="background: #F3F4F6; font-weight: bold; border-bottom: 1px solid #E5E7EB; color: #374151;">
                                    @foreach($group['sizes'] as $sz => $q)
                                        <th style="padding: 4px; border-right: 1px solid #E5E7EB; font-weight: bold;">{{ $sz === 'TANPA_UKURAN' ? 'Tanpa Ukuran' : $sz }}</th>
                                    @endforeach
                                    <th style="padding: 4px; background: #EDE9FE; color: #6D28D9; font-weight: bold;">TOTAL</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr style="color: #111827;">
                                    @foreach($group['sizes'] as $sz => $q)
                                        <td style="padding: 4px; border-right: 1px solid #E5E7EB;">{{ $q }} pcs</td>
                                    @endforeach
                                    <td style="padding: 4px; font-weight: bold; background: #EDE9FE; color: #6D28D9;">{{ $group['total_qty'] }} pcs</td>
                                </tr>
                            </tbody>
                        </table>

                        <!-- Custom Measurements Table -->
                        @if(!empty($group['recipients']['custom']))
                        <div style="font-size: 9.5px; color: #6B7280; font-weight: bold; margin-bottom: 4px;">Detail Ukuran Custom :</div>
                        <table style="width: 100%; border-collapse: collapse; font-size: 9px; border: 1px solid #E5E7EB; background: #fff;">
                            <thead>
                                <tr style="background: #F3F4F6; font-weight: bold; border-bottom: 1px solid #E5E7EB; color: #374151;">
                                    <th style="padding: 4px; text-align: left; border-right: 1px solid #E5E7EB; width: 30%;">Nama Penerima</th>
                                    <th style="padding: 4px; text-align: left;">Rincian Ukuran Badan (cm)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($group['recipients']['custom'] as $cust)
                                    <tr style="border-bottom: 1px solid #F3F4F6; color: #111827;">
                                        <td style="padding: 4px 6px; font-weight: bold; border-right: 1px solid #E5E7EB;">
                                            {{ $cust['nama'] }} <span style="font-weight: normal; color: #6B7280; font-size: 8.5px;">({{ $cust['size'] }})</span>
                                        </td>
                                        <td style="padding: 4px 6px; font-family: monospace; color: #4B5563; letter-spacing: 0.5px;">
                                            {{ str_replace(' ', '  |  ', $cust['desc']) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        @endif
                    </div>
                @endforeach

                {{-- Gambar Desain ACC / Proposal --}}
                @if(!empty($product['design_image']))
                    @php
                        $imgPath = storage_path('app/public/' . $product['design_image']);
                        if (!file_exists($imgPath)) {
                            $imgPath = public_path('storage/' . $product['design_image']);
                        }
                        $imgData = file_exists($imgPath) ? 'data:image/' . pathinfo($imgPath, PATHINFO_EXTENSION) . ';base64,' . base64_encode(file_get_contents($imgPath)) : null;
                    @endphp

                    @if($imgData)
                    <div style="margin-top: 14px; padding: 12px; border: 1.5px dashed #7F00FF; border-radius: 8px; background: #FAF5FF; text-align: center; page-break-inside: avoid;">
                        <div style="font-size: 11px; font-weight: bold; color: #7F00FF; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.05em;">
                            🖼️ GAMBAR DESAIN ACC / PROPOSAL: {{ strtoupper($product['product_name']) }}
                        </div>
                        <img src="{{ $imgData }}" style="max-height: 380px; max-width: 100%; object-fit: contain; border-radius: 6px; border: 1px solid #E9D5FF; box-shadow: 0 2px 4px rgba(127,0,255,0.1);" alt="Gambar Desain">
                    </div>
                    @endif
                @endif
            </div>
        @endforeach
    </div>
    @endif
</body>

</html>