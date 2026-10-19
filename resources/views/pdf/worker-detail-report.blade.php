<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Rincian Karyawan - {{ $worker->name }}</title>
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
        .section-title {
            font-size: 12px;
            font-weight: bold;
            color: #1f2937;
            margin-top: 15px;
            margin-bottom: 8px;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .summary-box {
            width: 100%;
            margin-bottom: 15px;
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
            padding: 5px 8px;
            font-size: 11px;
        }
        .summary-val {
            font-weight: bold;
            font-size: 12px;
            text-align: right;
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
        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 9px;
            font-weight: bold;
        }
        .badge-success { background: #dcfce7; color: #166534; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-info { background: #e0f2fe; color: #075985; }
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
            width: 25%;
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
                    <div class="title">Rincian Riwayat Karyawan & Upah</div>
                    <div class="period-info">
                        Nama: <strong>{{ $worker->name }}</strong> • {{ $worker->phone ?: 'No HP: -' }} • Status: {{ $worker->is_active ? 'Aktif' : 'Nonaktif' }}
                    </div>
                </td>
                <td style="text-align: right; vertical-align: top;">
                    <div class="period-info">{{ $shop->address ?? '' }}</div>
                    <div style="font-size: 10px; color: #9ca3af; margin-top: 4px;">Dicetak: {{ now()->translatedFormat('d F Y H:i') }}</div>
                </td>
            </tr>
        </table>
    </div>

    <!-- 1. Ringkasan Upah & Kasbon (Tampilan Baris Baru / Card Vertikal) -->
    <table class="stat-card-table">
        <tr>
            <td class="stat-card">
                <div class="stat-label">Sistem Upah</div>
                <div class="stat-value" style="color: #4b5563;">{{ $worker->wage_type === 'monthly' ? 'Gaji Bulanan' : 'Upah Borongan' }}</div>
            </td>
            <td class="stat-card">
                <div class="stat-label">Upah Belum Dibayar</div>
                <div class="stat-value" style="color: #ef4444;">Rp {{ number_format($worker->unpaid_wage, 0, ',', '.') }}</div>
            </td>
            <td class="stat-card">
                <div class="stat-label">Upah Bulan Ini</div>
                <div class="stat-value" style="color: #16a34a;">Rp {{ number_format($worker->monthly_earned, 0, ',', '.') }}</div>
            </td>
            <td class="stat-card">
                <div class="stat-label">Total Upah All-Time</div>
                <div class="stat-value" style="color: #8000FF;">Rp {{ number_format($worker->total_earned, 0, ',', '.') }}</div>
            </td>
        </tr>
        <tr>
            <td class="stat-card">
                <div class="stat-label">Sisa Kasbon Saat Ini</div>
                <div class="stat-value" style="color: {{ $worker->current_cash_advance > 0 ? '#ef4444' : '#6b7280' }};">
                    Rp {{ number_format($worker->current_cash_advance, 0, ',', '.') }}
                </div>
            </td>
            <td class="stat-card">
                <div class="stat-label">Limit Maksimal Kasbon</div>
                <div class="stat-value" style="color: #4b5563;">Rp {{ number_format($worker->max_cash_advance, 0, ',', '.') }}</div>
            </td>
            <td class="stat-card">
                <div class="stat-label">Pekerjaan Selesai</div>
                <div class="stat-value" style="color: #2563eb;">{{ number_format($worker->done_count, 0, ',', '.') }} pcs</div>
            </td>
            <td class="stat-card">
                <div class="stat-label">Antrian & Dikerjakan</div>
                <div class="stat-value" style="color: #d97706;">{{ number_format($worker->pending_count + $worker->in_progress_count, 0, ',', '.') }} pcs</div>
            </td>
        </tr>
    </table>

    <!-- 2. Riwayat Pekerjaan & Upah -->
    <div class="section-title">Riwayat Pekerjaan & Upah Produksi (Total: {{ $worker->productionTasks->count() }})</div>
    <table class="data-table">
        <thead>
            <tr>
                <th class="text-center" style="width: 25px;">No</th>
                <th>No. Pesanan</th>
                <th>Produk</th>
                <th>Tahap</th>
                <th class="text-center">Qty</th>
                <th class="text-right">Upah (Rp)</th>
                <th class="text-center">Status Kerja</th>
                <th class="text-center">Status Upah</th>
                <th>Tgl Selesai</th>
            </tr>
        </thead>
        <tbody>
            @forelse($worker->productionTasks as $idx => $task)
                <tr>
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td><strong>{{ $task->orderItem?->order?->order_number ?? '-' }}</strong></td>
                    <td>{{ $task->orderItem?->product_name ?? '-' }}</td>
                    <td>{{ $task->stage_name }}</td>
                    <td class="text-center" style="font-weight: bold; color: #2563eb;">{{ $task->quantity }} pcs</td>
                    <td class="text-right" style="font-weight: bold; color: #16a34a;">{{ number_format($task->wage_amount, 0, ',', '.') }}</td>
                    <td class="text-center">
                        @if($task->status === 'done')
                            <span class="badge badge-success">Selesai</span>
                        @elseif($task->status === 'in_progress')
                            <span class="badge badge-info">Dikerjakan</span>
                        @else
                            <span class="badge badge-warning">Antrian</span>
                        @endif
                    </td>
                    <td class="text-center">
                        @if($task->is_paid)
                            <span class="badge badge-success">Lunas</span>
                        @else
                            <span class="badge badge-danger">Belum Dibayar</span>
                        @endif
                    </td>
                    <td>{{ $task->completed_at ? \Illuminate\Support\Carbon::parse($task->completed_at)->format('d/m/Y H:i') : '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="text-center" style="padding: 15px;">Belum ada riwayat pekerjaan untuk karyawan ini.</td>
                </tr>
            @endforelse
        </tbody>
        @if($worker->productionTasks->isNotEmpty())
            <tfoot>
                <tr style="background-color: #f9fafb; font-weight: bold;">
                    <td colspan="4" class="text-right">TOTAL UPAH DARI PEKERJAAN:</td>
                    <td class="text-center" style="color:#2563eb;">{{ number_format($worker->productionTasks->sum('quantity'), 0, ',', '.') }} pcs</td>
                    <td class="text-right" style="color:#16a34a;">Rp {{ number_format($worker->productionTasks->sum('wage_amount'), 0, ',', '.') }}</td>
                    <td colspan="3"></td>
                </tr>
            </tfoot>
        @endif
    </table>

    <!-- 3. Riwayat Kasbon & Pelunasan -->
    <div class="section-title">Riwayat Kasbon & Pelunasan</div>
    <table class="data-table">
        <thead>
            <tr>
                <th class="text-center" style="width: 25px;">No</th>
                <th>Tanggal</th>
                <th>Jenis</th>
                <th class="text-right">Nominal (Rp)</th>
                <th class="text-center">Status</th>
                <th>Catatan / Keterangan</th>
            </tr>
        </thead>
        <tbody>
            @forelse($worker->cashAdvances as $idx => $k)
                <tr>
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td>{{ \Illuminate\Support\Carbon::parse($k->date)->format('d/m/Y') }}</td>
                    <td>
                        @if($k->type === 'loan')
                            <span class="badge badge-danger">Pinjaman (Kasbon)</span>
                        @else
                            <span class="badge badge-success">Pelunasan</span>
                        @endif
                    </td>
                    <td class="text-right" style="font-weight: bold; color: {{ $k->type === 'loan' ? '#ef4444' : '#16a34a' }};">
                        {{ ($k->type === 'loan' ? '+ ' : '- ') . number_format($k->amount, 0, ',', '.') }}
                    </td>
                    <td class="text-center">
                        @if($k->status === 'approved')
                            <span class="badge badge-success">Disetujui</span>
                        @elseif($k->status === 'pending')
                            <span class="badge badge-warning">Pending</span>
                        @else
                            <span class="badge badge-danger">Ditolak</span>
                        @endif
                    </td>
                    <td>{{ $k->note ?: '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center" style="padding: 15px;">Tidak ada riwayat kasbon.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        <div class="sig-box">
            <div>{{ $shop->name ?? 'Pimpinan' }}, {{ now()->format('d/m/Y') }}</div>
            <div style="margin-top: 5px; color: #6b7280;">Dibuat Oleh:</div>
            <div class="sig-space"></div>
            <div style="border-top: 1px solid #9ca3af; font-weight: bold; padding-top: 3px;">
                {{ auth()->user()->name ?? 'Owner / Admin' }}
            </div>
        </div>
        <div class="clear"></div>
    </div>
</body>
</html>
