<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    @php
        $isMonthly = ($payroll->note === 'Gaji Bulanan' || $payroll->productionTasks->isEmpty());
    @endphp
    <title>Slip {{ $isMonthly ? 'Gaji' : 'Upah' }} - {{ $payroll->worker->name }}</title>
    <style>
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 12px;
            color: #333;
            margin: 0;
            padding: 0;
        }
        .container {
            width: 100%;
            padding: 20px;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #444;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .header h1 {
            margin: 0;
            font-size: 20px;
            text-transform: uppercase;
        }
        .header p {
            margin: 5px 0 0;
            font-size: 10px;
            color: #666;
            }
        .info-table {
            width: 100%;
            margin-bottom: 20px;
        }
        .info-table td {
            padding: 2px 0;
        }
        .content-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .content-table th {
            background-color: #f2f2f2;
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        .content-table td {
            border: 1px solid #ddd;
            padding: 8px;
        }
        .totals {
            float: right;
            width: 250px;
        }
        .totals-table {
            width: 100%;
        }
        .totals-table td {
            padding: 4px 0;
        }
        .totals-table .label {
            text-align: left;
        }
        .totals-table .value {
            text-align: right;
            font-weight: bold;
        }
        .totals-table .grand-total {
            font-size: 16px;
            border-top: 1px solid #444;
            padding-top: 10px;
            margin-top: 10px;
            color: #000;
        }
        .footer {
            margin-top: 50px;
            width: 100%;
        }
        .signature-box {
            width: 200px;
            text-align: center;
        }
        .signature-space {
            height: 60px;
        }
        .clear {
            clear: both;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>SLIP {{ $isMonthly ? 'GAJI KARYAWAN BULANAN' : 'UPAH KARYAWAN BORONGAN' }}</h1>
            <p>{{ $payroll->shop->name }}</p>
        </div>

        <table class="info-table">
            <tr>
                <td width="15%">Nama</td>
                <td width="35%">: <strong>{{ $payroll->worker->name }}</strong></td>
                <td width="15%">No. Slip</td>
                <td width="35%">: #PAY-{{ str_pad($payroll->id, 5, '0', STR_PAD_LEFT) }}</td>
            </tr>
            <tr>
                <td>Tanggal Bayar</td>
                <td>: {{ $payroll->payment_date->format('d F Y') }}</td>
                <td>Pencatat</td>
                <td>: {{ $payroll->recorder->name }}</td>
            </tr>
        </table>

        @if(!$isMonthly)
        <table class="content-table">
            <thead>
                <tr>
                    <th>Item / Pesanan</th>
                    <th>Tahapan</th>
                    <th style="text-align: center;">Qty</th>
                    <th style="text-align: right;">Upah/Pcs</th>
                    <th style="text-align: right;">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $tasksByOrder = [];
                    foreach($payroll->productionTasks as $task) {
                        $sizeQuantities = $task->size_quantities ?? [];
                        $customQty = $sizeQuantities['CUSTOM'] ?? 0;
                        $totalQty = $task->quantity;
                        $standardQty = $totalQty - $customQty;
                        $totalWage = $task->wage_amount;
                        $customWage = $sizeQuantities['_wage_custom'] ?? 0;
                        
                        // Hitung upah standar
                        $standardWage = ($standardQty > 0) 
                            ? ($totalWage - ($customQty * $customWage)) / $standardQty 
                            : 0;

                        $customerName = $task->orderItem->order->customer->name ?? '-';
                        $productName = $task->orderItem->product_name ?? 'Custom Order';
                        $orderId = $task->orderItem->order_id;
                        $orderNumber = $task->orderItem->order->order_number;
                        $stageName = $task->stage_name;

                        // Tambahkan ke grup Standar per Order
                        if ($standardQty > 0) {
                            $key = "std_{$productName}_{$stageName}_" . round($standardWage);
                            if (!isset($tasksByOrder[$orderId]['items'][$key])) {
                                $tasksByOrder[$orderId]['info'] = ['customer' => $customerName, 'number' => $orderNumber];
                                $tasksByOrder[$orderId]['items'][$key] = [
                                    'product' => $productName,
                                    'stage' => $stageName,
                                    'qty' => 0,
                                    'wage' => $standardWage,
                                    'is_custom' => false
                                ];
                            }
                            $tasksByOrder[$orderId]['items'][$key]['qty'] += $standardQty;
                        }

                        // Tambahkan ke grup Custom per Order
                        if ($customQty > 0) {
                            $key = "cus_{$productName}_{$stageName}_" . round($customWage);
                            if (!isset($tasksByOrder[$orderId]['items'][$key])) {
                                $tasksByOrder[$orderId]['info'] = ['customer' => $customerName, 'number' => $orderNumber];
                                $tasksByOrder[$orderId]['items'][$key] = [
                                    'product' => $productName,
                                    'stage' => $stageName,
                                    'qty' => 0,
                                    'wage' => $customWage,
                                    'is_custom' => true
                                ];
                            }
                            $tasksByOrder[$orderId]['items'][$key]['qty'] += $customQty;
                        }
                    }
                @endphp

                @foreach($tasksByOrder as $orderId => $group)
                <tr style="background-color: #f8fafc;">
                    <td colspan="5" style="padding: 8px 12px; border-bottom: 1px solid #e2e8f0;">
                        <span style="font-size: 11px; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em;">PESANAN:</span>
                        <span style="font-size: 12px; font-weight: 800; color: #1e293b; margin-left: 4px;">{{ $group['info']['customer'] }} ({{ $group['info']['number'] }})</span>
                    </td>
                </tr>
                @foreach($group['items'] as $item)
                <tr>
                    <td style="padding-left: 20px;">
                        {{ $item['product'] }} {{ $item['is_custom'] ? '[Custom]' : '' }}
                    </td>
                    <td>{{ $item['stage'] }}</td>
                    <td style="text-align: center;">{{ $item['qty'] }}</td>
                    <td style="text-align: right;">Rp {{ number_format($item['wage'], 0, ',', '.') }}</td>
                    <td style="text-align: right;">Rp {{ number_format($item['qty'] * $item['wage'], 0, ',', '.') }}</td>
                </tr>
                @endforeach
                @endforeach
            </tbody>
        </table>
        @else
        <table class="content-table">
            <thead>
                <tr>
                    <th>Deskripsi / Rincian</th>
                    <th style="text-align: right; width: 200px;">Nominal</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Gaji Bulanan Tetap (Periode Pembayaran {{ $payroll->payment_date->format('F Y') }})</td>
                    <td style="text-align: right; font-weight: bold;">Rp {{ number_format($payroll->total_wage, 0, ',', '.') }}</td>
                </tr>
            </tbody>
        </table>
        @endif

        <table width="100%">
            <tr>
                <td width="60%">
                    @if(!empty($payroll->note))
                        <div style="font-size: 11px; color: #666; margin-top: 10px;">
                            <strong>Catatan:</strong><br>
                            {{ $payroll->note }}
                        </div>
                    @endif
                </td>
                <td width="40%">
                    <table class="totals-table">
                        <tr>
                            <td class="label">Total {{ $isMonthly ? 'Gaji' : 'Upah' }} Kotor</td>
                            <td class="value">Rp {{ number_format($payroll->total_wage, 0, ',', '.') }}</td>
                        </tr>
                        @if($payroll->kasbon_deduction > 0)
                        <tr>
                            <td class="label">Potongan Kasbon</td>
                            <td class="value">- Rp {{ number_format($payroll->kasbon_deduction, 0, ',', '.') }}</td>
                        </tr>
                        @endif
                        <tr class="grand-total">
                            <td class="label"><strong>TOTAL BERSIH</strong></td>
                            <td class="value" style="color: #000;"><strong>Rp {{ number_format($payroll->net_amount, 0, ',', '.') }}</strong></td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <div class="footer">
            <table width="100%">
                <tr>
                    <td class="signature-box">
                        Penerima,
                        <div class="signature-space"></div>
                        ( {{ $payroll->worker->name }} )
                    </td>
                    <td></td>
                    <td class="signature-box">
                        Admin,
                        <div class="signature-space"></div>
                        ( {{ $payroll->recorder->name }} )
                    </td>
                </tr>
            </table>
        </div>
    </div>
</body>
</html>
