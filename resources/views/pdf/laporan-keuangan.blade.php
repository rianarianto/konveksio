<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Laporan Keuangan</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 11px;
            color: #333;
            line-height: 1.4;
            margin: 0;
            padding: 15px;
        }
        .header {
            border-bottom: 2px solid #8000FF;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        .header table {
            width: 100%;
        }
        .title {
            font-size: 18px;
            font-weight: bold;
            color: #111827;
        }
        .shop-name {
            font-size: 14px;
            font-weight: bold;
            color: #8000FF;
        }
        .period-info {
            font-size: 11px;
            color: #6b7280;
            margin-top: 3px;
        }
        .summary-box {
            width: 100%;
            margin-bottom: 20px;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 10px;
        }
        .summary-table {
            width: 100%;
            border-collapse: collapse;
        }
        .summary-table td {
            padding: 6px 10px;
            font-size: 11px;
        }
        .summary-val {
            font-weight: bold;
            font-size: 13px;
            text-align: right;
        }
        .section-title {
            font-size: 13px;
            font-weight: bold;
            color: #1f2937;
            margin-top: 15px;
            margin-bottom: 8px;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 4px;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        table.data-table th {
            background-color: #f3f4f6;
            color: #374151;
            font-weight: bold;
            text-align: left;
            padding: 6px 8px;
            border: 1px solid #e5e7eb;
            font-size: 10px;
            text-transform: uppercase;
        }
        table.data-table td {
            padding: 5px 8px;
            border: 1px solid #e5e7eb;
            font-size: 10px;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .badge-modal {
            background-color: #ecfdf5;
            color: #047857;
            font-weight: bold;
            padding: 2px 4px;
            border-radius: 3px;
            font-size: 9px;
        }
        .total-row {
            background-color: #f9fafb;
            font-weight: bold;
        }
    </style>
</head>
<body>

<div class="header">
    <table>
        <tr>
            <td>
                <div class="shop-name">{{ $shop->name ?? 'KONVEKSI' }}</div>
                <div class="title">LAPORAN KAS & KEUANGAN</div>
                <div class="period-info">Periode: {{ \Carbon\Carbon::parse($from)->format('d M Y') }} s/d {{ \Carbon\Carbon::parse($until)->format('d M Y') }}</div>
            </td>
            <td class="text-right" style="vertical-align: top;">
                <div style="font-size: 10px; color: #9ca3af;">Dicetak: {{ now()->format('d/m/Y H:i') }}</div>
            </td>
        </tr>
    </table>
</div>

<div class="summary-box">
    <table class="summary-table">
        <tr>
            <td style="color: #047857;">Total Pemasukan (Kas Masuk / Modal):</td>
            <td class="summary-val" style="color: #047857;">Rp {{ number_format($totalPemasukan, 0, ',', '.') }}</td>
            <td style="color: #b91c1c;">Total Pengeluaran Operasional:</td>
            <td class="summary-val" style="color: #b91c1c;">Rp {{ number_format($totalPengeluaran, 0, ',', '.') }}</td>
            <td style="border-left: 1px solid #e5e7eb; color: #111827;">Selisih / Saldo Periode:</td>
            <td class="summary-val" style="color: {{ $saldoPeriode >= 0 ? '#8000FF' : '#dc2626' }};">
                Rp {{ number_format($saldoPeriode, 0, ',', '.') }}
            </td>
        </tr>
    </table>
</div>

{{-- TABEL PEMASUKAN --}}
<div class="section-title">1. RINCIAN KAS MASUK & MODAL ({{ $payments->count() }} Transaksi)</div>
<table class="data-table">
    <thead>
        <tr>
            <th width="5%" class="text-center">No</th>
            <th width="12%">Tanggal</th>
            <th width="20%">Referensi / Pesanan</th>
            <th width="28%">Pelanggan / Keterangan</th>
            <th width="12%">Metode</th>
            <th width="13%" class="text-right">Nominal</th>
            <th width="10%">Penerima</th>
        </tr>
    </thead>
    <tbody>
        @forelse($payments as $idx => $p)
            @php
                $isModal = $p->type === 'modal_awal' || !$p->order_id;
            @endphp
            <tr>
                <td class="text-center">{{ $idx + 1 }}</td>
                <td>{{ \Carbon\Carbon::parse($p->payment_date)->format('d/m/Y') }}</td>
                <td>
                    @if($isModal)
                        <span class="badge-modal">MODAL KAS KECIL</span>
                    @else
                        <b>{{ $p->order->order_number ?? '-' }}</b>
                    @endif
                </td>
                <td>
                    @if($isModal)
                        {{ $p->note ?: 'Modal Harian' }}
                    @else
                        {{ $p->order->customer->name ?? '-' }}
                        @if($p->note)
                            <span style="color:#6b7280; font-size:9px;">({{ $p->note }})</span>
                        @endif
                    @endif
                </td>
                <td>{{ $p->methodLabel() }}</td>
                <td class="text-right" style="font-weight: bold; color: #047857;">Rp {{ number_format($p->amount, 0, ',', '.') }}</td>
                <td>{{ $p->recorder->name ?? '-' }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="7" class="text-center" style="color: #9ca3af; padding: 10px;">Tidak ada transaksi pemasukan pada periode ini.</td>
            </tr>
        @endforelse
        <tr class="total-row">
            <td colspan="5" class="text-right">TOTAL KAS MASUK:</td>
            <td class="text-right" style="color: #047857;">Rp {{ number_format($totalPemasukan, 0, ',', '.') }}</td>
            <td></td>
        </tr>
    </tbody>
</table>

{{-- TABEL PENGELUARAN --}}
<div class="section-title">2. RINCIAN KAS KELUAR / PENGELUARAN ({{ $expenses->count() }} Transaksi)</div>
<table class="data-table">
    <thead>
        <tr>
            <th width="5%" class="text-center">No</th>
            <th width="12%">Tanggal</th>
            <th width="40%">Keperluan</th>
            <th width="20%">Kategori</th>
            <th width="13%" class="text-right">Nominal</th>
            <th width="10%">Pencatat</th>
        </tr>
    </thead>
    <tbody>
        @forelse($expenses as $idx => $e)
            <tr>
                <td class="text-center">{{ $idx + 1 }}</td>
                <td>{{ \Carbon\Carbon::parse($e->expense_date)->format('d/m/Y') }}</td>
                <td><b>{{ $e->keperluan }}</b></td>
                <td>{{ $e->note ?: 'Lainnya' }}</td>
                <td class="text-right" style="font-weight: bold; color: #b91c1c;">Rp {{ number_format($e->amount, 0, ',', '.') }}</td>
                <td>{{ $e->recorder->name ?? '-' }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="6" class="text-center" style="color: #9ca3af; padding: 10px;">Tidak ada transaksi pengeluaran pada periode ini.</td>
            </tr>
        @endforelse
        <tr class="total-row">
            <td colspan="4" class="text-right">TOTAL PENGELUARAN:</td>
            <td class="text-right" style="color: #b91c1c;">Rp {{ number_format($totalPengeluaran, 0, ',', '.') }}</td>
            <td></td>
        </tr>
    </tbody>
</table>

</body>
</html>
