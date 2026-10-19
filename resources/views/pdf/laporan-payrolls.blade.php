<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $tab === 'bulanan' ? 'Laporan Gaji Bulanan' : 'Laporan Upah Borongan' }}</title>
    <style>
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
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
        .stat-card-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 6px;
            margin-bottom: 15px;
        }
        .stat-card {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 8px 10px;
            vertical-align: top;
        }
        .stat-label {
            font-size: 9px;
            text-transform: uppercase;
            color: #6b7280;
            font-weight: bold;
            margin-bottom: 4px;
            letter-spacing: 0.3px;
        }
        .stat-value {
            font-size: 13px;
            font-weight: bold;
            color: #111827;
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
            padding: 6px 8px;
            border: 1px solid #e5e7eb;
            font-size: 10px;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .footer {
            margin-top: 30px;
            width: 100%;
        }
        .sig-box {
            float: right;
            width: 200px;
            text-align: center;
            font-size: 11px;
        }
        .sig-space {
            height: 50px;
        }
        .clear {
            clear: both;
        }

        .watermark {
            position: fixed;
            top: 28%;
            left: 10%;
            width: 80%;
            text-align: center;
            opacity: 0.05;
            z-index: 99999;
            pointer-events: none;
        }

        .watermark img {
            width: 480px;
            height: auto;
        }

        .header-logo-cell {
            vertical-align: middle;
            padding-right: 12px;
            width: 70px;
        }

        .header-logo-cell img {
            width: 65px;
            height: auto;
            border-radius: 4px;
            display: block;
        }
    </style>
</head>
<body>
    @php
        $logoPath = public_path('images/logo.jpeg');
        $logoBase64 = file_exists($logoPath) ? 'data:image/jpeg;base64,' . base64_encode(file_get_contents($logoPath)) : null;
    @endphp

    @if($logoBase64)
        <div class="watermark">
            <img src="{{ $logoBase64 }}" alt="Watermark">
        </div>
    @endif

    <div class="header">
        <table>
            <tr>
                @if($logoBase64)
                    <td class="header-logo-cell">
                        <img src="{{ $logoBase64 }}" alt="Logo">
                    </td>
                @endif
                <td>
                    <div class="shop-name">{{ $shop->name ?? 'KONVEKSI' }}</div>
                    <div class="title">{{ $tab === 'bulanan' ? 'Laporan Gaji Bulanan Karyawan' : 'Laporan Upah Borongan Tukang' }}</div>
                    <div class="period-info">
                        @if($from || $until)
                            Rentang Pekerjaan: {{ $from ? \Illuminate\Support\Carbon::parse($from)->format('d/m/Y') : 'Awal' }} s/d {{ $until ? \Illuminate\Support\Carbon::parse($until)->format('d/m/Y') : 'Sekarang' }}
                        @else
                            Semua Pekerjaan Selesai yang Belum Dibayar
                        @endif
                    </div>
                </td>
                <td style="text-align: right; vertical-align: top;">
                    <div class="period-info">{{ $shop->address ?? '' }}</div>
                    @if(!empty($shop->phone))
                        <div class="period-info">Telp/WA: {{ $shop->phone }}</div>
                    @endif
                    <div style="font-size: 10px; color: #9ca3af; margin-top: 4px;">Dicetak: {{ now()->translatedFormat('d F Y H:i') }}</div>
                </td>
            </tr>
        </table>
    </div>

    @if($tab === 'bulanan')
        @php
            $totKotor = $workers->sum('base_salary');
            $totKasbon = $workers->sum('current_cash_advance');
            $totBersih = 0;
            foreach($workers as $w) {
                $totBersih += max(0, (int)$w->base_salary - (int)$w->current_cash_advance);
            }
        @endphp
        <table class="stat-card-table">
            <tr>
                <td class="stat-card" style="width: 25%;">
                    <div class="stat-label">Total Karyawan</div>
                    <div class="stat-value">{{ $workers->count() }} orang</div>
                </td>
                <td class="stat-card" style="width: 25%;">
                    <div class="stat-label">Total Gaji Pokok</div>
                    <div class="stat-value">Rp {{ number_format($totKotor, 0, ',', '.') }}</div>
                </td>
                <td class="stat-card" style="width: 25%;">
                    <div class="stat-label">Potongan Kasbon</div>
                    <div class="stat-value" style="color: #ef4444;">Rp {{ number_format($totKasbon, 0, ',', '.') }}</div>
                </td>
                <td class="stat-card" style="width: 25%;">
                    <div class="stat-label">Total Bersih</div>
                    <div class="stat-value" style="color: #16a34a;">Rp {{ number_format($totBersih, 0, ',', '.') }}</div>
                </td>
            </tr>
        </table>

        <table class="data-table">
            <thead>
                <tr>
                    <th class="text-center" style="width: 30px;">No</th>
                    <th>Nama Karyawan</th>
                    <th>No. Telepon</th>
                    <th class="text-right">Gaji Pokok (Rp)</th>
                    <th class="text-right">Sisa Kasbon (Rp)</th>
                    <th class="text-right">Gaji Bersih (Rp)</th>
                </tr>
            </thead>
            <tbody>
                @forelse($workers as $idx => $w)
                    @php
                        $kotor = (int) $w->base_salary;
                        $kasbon = (int) $w->current_cash_advance;
                        $bersih = max(0, $kotor - $kasbon);
                    @endphp
                    <tr>
                        <td class="text-center">{{ $idx + 1 }}</td>
                        <td><strong>{{ $w->name }}</strong></td>
                        <td>{{ $w->phone ?: '-' }}</td>
                        <td class="text-right">{{ number_format($kotor, 0, ',', '.') }}</td>
                        <td class="text-right" style="color: {{ $kasbon > 0 ? '#ef4444' : '#6b7280' }};">
                            {{ number_format($kasbon, 0, ',', '.') }}
                        </td>
                        <td class="text-right" style="font-weight: bold; color: #16a34a;">
                            {{ number_format($bersih, 0, ',', '.') }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center" style="padding: 20px;">Tidak ada data karyawan bulanan.</td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr style="background-color: #f9fafb; font-weight: bold;">
                    <td colspan="3" class="text-right">TOTAL:</td>
                    <td class="text-right">Rp {{ number_format($totKotor, 0, ',', '.') }}</td>
                    <td class="text-right" style="color:#ef4444;">Rp {{ number_format($totKasbon, 0, ',', '.') }}</td>
                    <td class="text-right" style="color:#16a34a;">Rp {{ number_format($totBersih, 0, ',', '.') }}</td>
                </tr>
            </tfoot>
        </table>
    @else
        @php
            $totPcs = 0;
            $totKotor = 0;
            $totKasbon = $workers->sum('current_cash_advance');
            $totBersih = 0;
            foreach($workers as $w) {
                $pcs = (int) $w->productionTasks->sum('quantity');
                $kotor = (int) $w->productionTasks->sum('wage_amount');
                $kasbon = (int) $w->current_cash_advance;
                $totPcs += $pcs;
                $totKotor += $kotor;
                $totBersih += max(0, $kotor - $kasbon);
            }
        @endphp
        <table class="stat-card-table">
            <tr>
                <td class="stat-card" style="width: 20%;">
                    <div class="stat-label">Total Pekerja</div>
                    <div class="stat-value">{{ $workers->count() }} orang</div>
                </td>
                <td class="stat-card" style="width: 20%;">
                    <div class="stat-label">Total Hasil</div>
                    <div class="stat-value" style="color: #2563eb;">{{ number_format($totPcs, 0, ',', '.') }} pcs</div>
                </td>
                <td class="stat-card" style="width: 20%;">
                    <div class="stat-label">Total Upah Kotor</div>
                    <div class="stat-value">Rp {{ number_format($totKotor, 0, ',', '.') }}</div>
                </td>
                <td class="stat-card" style="width: 20%;">
                    <div class="stat-label">Potongan Kasbon</div>
                    <div class="stat-value" style="color: #ef4444;">Rp {{ number_format($totKasbon, 0, ',', '.') }}</div>
                </td>
                <td class="stat-card" style="width: 20%;">
                    <div class="stat-label">Total Bersih</div>
                    <div class="stat-value" style="color: #16a34a;">Rp {{ number_format($totBersih, 0, ',', '.') }}</div>
                </td>
            </tr>
        </table>

        <table class="data-table">
            <thead>
                <tr>
                    <th class="text-center" style="width: 25px;">No</th>
                    <th>Nama Tukang</th>
                    <th class="text-center">Periode Tugas</th>
                    <th class="text-center">Pesanan</th>
                    <th class="text-center">Item</th>
                    <th class="text-center">Total Pcs</th>
                    <th class="text-right">Upah Kotor (Rp)</th>
                    <th class="text-right">Sisa Kasbon (Rp)</th>
                    <th class="text-right">Upah Bersih (Rp)</th>
                </tr>
            </thead>
            <tbody>
                @forelse($workers as $idx => $w)
                    @php
                        $tasks = $w->productionTasks;
                        $minDate = $tasks->min('completed_at');
                        $maxDate = $tasks->max('completed_at');
                        $period = '-';
                        if ($minDate && $maxDate) {
                            $f = \Illuminate\Support\Carbon::parse($minDate)->format('d/m/Y');
                            $t = \Illuminate\Support\Carbon::parse($maxDate)->format('d/m/Y');
                            $period = $f === $t ? $f : "{$f} - {$t}";
                        }
                        $pesananCount = $tasks->pluck('orderItem.order_id')->filter()->unique()->count();
                        $itemsCount = $tasks->pluck('order_item_id')->filter()->unique()->count();
                        $pcs = (int) $tasks->sum('quantity');
                        $kotor = (int) $tasks->sum('wage_amount');
                        $kasbon = (int) $w->current_cash_advance;
                        $bersih = max(0, $kotor - $kasbon);
                    @endphp
                    <tr>
                        <td class="text-center">{{ $idx + 1 }}</td>
                        <td><strong>{{ $w->name }}</strong></td>
                        <td class="text-center" style="font-size: 9px; color: #4b5563;">{{ $period }}</td>
                        <td class="text-center">{{ $pesananCount }}</td>
                        <td class="text-center">{{ $itemsCount }}</td>
                        <td class="text-center" style="font-weight: bold; color: #2563eb;">{{ number_format($pcs, 0, ',', '.') }}</td>
                        <td class="text-right">{{ number_format($kotor, 0, ',', '.') }}</td>
                        <td class="text-right" style="color: {{ $kasbon > 0 ? '#ef4444' : '#6b7280' }};">
                            {{ number_format($kasbon, 0, ',', '.') }}
                        </td>
                        <td class="text-right" style="font-weight: bold; color: #16a34a;">
                            {{ number_format($bersih, 0, ',', '.') }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center" style="padding: 20px;">Tidak ada data pekerjaan borongan belum dibayar.</td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr style="background-color: #f9fafb; font-weight: bold;">
                    <td colspan="5" class="text-right">TOTAL:</td>
                    <td class="text-center" style="color: #2563eb;">{{ number_format($totPcs, 0, ',', '.') }}</td>
                    <td class="text-right">Rp {{ number_format($totKotor, 0, ',', '.') }}</td>
                    <td class="text-right" style="color:#ef4444;">Rp {{ number_format($totKasbon, 0, ',', '.') }}</td>
                    <td class="text-right" style="color:#16a34a;">Rp {{ number_format($totBersih, 0, ',', '.') }}</td>
                </tr>
            </tfoot>
        </table>
    @endif

    <div class="footer">
        <div class="sig-box">
            <div>{{ $shop->name ?? 'Pimpinan' }}, {{ now()->format('d/m/Y') }}</div>
            <div style="margin-top: 5px; color: #6b7280;">Dibuat Oleh:</div>
            <div class="sig-space"></div>
            <div style="border-top: 1px solid #9ca3af; font-weight: bold; padding-top: 3px;">
                {{ auth()->user()->name ?? 'Owner / Bag. Keuangan' }}
            </div>
        </div>
        <div class="clear"></div>
    </div>
</body>
</html>
