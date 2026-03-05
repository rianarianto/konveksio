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
                <div class="info-value" style="color: #7F00FF; font-size: 16px;">{{ $order->order_number }}</div>
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
                @foreach($order->orderItems as $index => $item)
                                <tr>
                                    <td style="text-align: center; color: #888;">{{ $index + 1 }}</td>
                                    <td>
                                        <div class="item-name">{{ $item->product_name }}</div>
                                        <div class="item-badge">
                                            {{ match ($item->production_category) {
                        'custom' => 'Custom',
                        'non_produksi' => 'Non-Produksi',
                        'jasa' => 'Jasa',
                        default => 'Produksi'
                    } }}
                                        </div>
                                        <div class="item-details">
                                            @php
                                                $details = $item->size_and_request_details;
                                                $itemsText = [];
                                                if (!empty($details['bahan']))
                                                    $itemsText[] = "Bahan: " . $details['bahan'];
                                                if (!empty($details['sablon_jenis']))
                                                    $itemsText[] = "Sablon: " . $details['sablon_jenis'];

                                                // Sizing
                                                $sizing = [];
                                                if ($item->production_category === 'custom') {
                                                    $sizing[] = count($details['detail_custom'] ?? []) . " pcs";
                                                } else if ($item->production_category === 'non_produksi') {
                                                    foreach ($details['varian_ukuran'] ?? [] as $v) {
                                                        $sz = $v['ukuran'] ?? $v['size'] ?? '?';
                                                        $q = $v['qty'] ?? 0;
                                                        if ($q > 0)
                                                            $sizing[] = "$sz: $q";
                                                    }
                                                } else if ($item->production_category === 'jasa') {
                                                    $sizing[] = ($details['jumlah'] ?? 0) . " unit";
                                                } else {
                                                    foreach ($details['varian_ukuran'] ?? [] as $v) {
                                                        $sz = $v['ukuran'] ?? $v['size'] ?? '?';
                                                        $q = $v['qty'] ?? 0;
                                                        if ($q > 0)
                                                            $sizing[] = "$sz: $q";
                                                    }
                                                }
                                            @endphp
                                            {{ implode(' | ', $itemsText) }}
                                            @if(count($sizing) > 0)
                                                <div style="margin-top: 2px;">Ukuran: {{ implode(', ', $sizing) }}</div>
                                            @endif
                                        </div>
                                    </td>
                                    <td style="text-align: center; font-weight: bold;">{{ $item->quantity }}</td>
                                    <td style="text-align: right;">Rp {{ number_format($item->price, 0, ',', '.') }}</td>
                                    <td style="text-align: right; font-weight: bold;">Rp
                                        {{ number_format($item->price * $item->quantity, 0, ',', '.') }}
                                    </td>
                                </tr>
                @endforeach
            </tbody>
        </table>

        <div class="clearfix">
            <div style="float: left; width: 60%;">
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
            <div style="float: right; width: 35%;">
                <table class="totals-table">
                    <tr>
                        <td class="totals-label">Subtotal</td>
                        <td class="totals-value">Rp {{ number_format($order->subtotal, 0, ',', '.') }}</td>
                    </tr>
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
</body>

</html>