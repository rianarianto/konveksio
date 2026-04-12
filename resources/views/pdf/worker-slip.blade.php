<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Slip Upah - {{ $payroll->worker->name }}</title>
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
            <h1>SLIP UPAH KARYAWAN</h1>
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
                @foreach($payroll->productionTasks as $task)
                <tr>
                    <td>{{ $task->orderItem->product->name ?? 'Custom Order' }} ({{ $task->orderItem->order->order_number }})</td>
                    <td>{{ $task->stage_name }}</td>
                    <td style="text-align: center;">{{ $task->quantity }}</td>
                    <td style="text-align: right;">Rp {{ number_format($task->wage_amount / ($task->quantity ?: 1), 0, ',', '.') }}</td>
                    <td style="text-align: right;">Rp {{ number_format($task->wage_amount, 0, ',', '.') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div class="totals">
            <table class="totals-table">
                <tr>
                    <td class="label">Total Upah Kotor</td>
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
        </div>

        <div class="clear"></div>

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
