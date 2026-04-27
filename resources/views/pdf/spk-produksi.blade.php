<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SPK Produksi - {{ $record->order->order_number }}</title>
    <style>
        @page {
            margin: 0.8cm;
        }
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 9pt;
            line-height: 1.2;
            color: #000;
            margin: 0;
            padding: 0;
        }
        .page-break {
            page-break-after: always;
        }
        .header {
            border-bottom: 2px solid #7c3aed;
            padding-bottom: 5px;
            margin-bottom: 10px;
        }
        .header-title {
            font-size: 16pt;
            font-weight: bold;
            color: #7c3aed;
            margin: 0;
        }
        .header-meta {
            font-size: 8.5pt;
            color: #333;
        }
        .section {
            margin-bottom: 12px;
        }
        .section-title {
            font-size: 10pt;
            font-weight: bold;
            background: #f3e8ff;
            color: #7c3aed;
            padding: 3px 6px;
            border-left: 4px solid #7c3aed;
            margin-bottom: 5px;
            text-transform: uppercase;
        }
        .grid {
            width: 100%;
            border-collapse: collapse;
        }
        .grid td {
            vertical-align: top;
            padding: 2px;
        }
        .info-label {
            font-weight: bold;
            color: #333;
            width: 140px;
            font-size: 9pt;
        }
        .info-value {
            font-weight: normal;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 3px;
        }
        table.data-table th, table.data-table td {
            border: 1px solid #000;
            padding: 5px;
            text-align: left;
            font-size: 8.5pt;
            vertical-align: top;
        }
        table.data-table th {
            background-color: #f3f4f6;
            color: #000;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 7.5pt;
        }
        .badge {
            display: inline-block;
            padding: 1px 4px;
            border-radius: 2px;
            font-size: 8pt;
            font-weight: bold;
        }
        .badge-primary { background: #f3e8ff; color: #7c3aed; border: 1px solid #ddd6fe; }
        .text-danger { color: #b91c1c; }
        .text-bold { font-weight: bold; }
        
        .spec-tag {
            background: #fdf2f8;
            color: #be185d;
            border: 1px solid #fbcfe8;
            padding: 1px 3px;
            border-radius: 2px;
            font-size: 7pt;
            font-weight: bold;
            margin-right: 2px;
            display: inline-block;
            margin-bottom: 2px;
        }
        .sb-tag {
            background: #f0fdf4;
            color: #166534;
            border: 1px solid #bbf7d0;
            padding: 1px 3px;
            border-radius: 2px;
            font-size: 7.5pt;
            font-weight: bold;
            display: block;
            margin-top: 2px;
        }
        .size-badge {
            display: inline-block;
            border: 1px solid #ccc;
            padding: 1px 3px;
            margin: 1px;
            font-size: 8.5pt;
            background: #fff;
            min-width: 30px;
            text-align: center;
        }
        .footer {
            margin-top: 20px;
            font-size: 7.5pt;
            color: #555;
            text-align: center;
            border-top: 1px solid #ccc;
            padding-top: 8px;
        }
        .signature-grid {
            margin-top: 20px;
            width: 100%;
        }
        .signature-box {
            text-align: center;
            width: 33%;
        }
        .signature-line {
            margin-top: 40px;
            border-top: 1px solid #000;
            width: 75%;
            margin-left: auto;
            margin-right: auto;
        }
        
        /* Halaman Desain */
        .full-design-image {
            max-width: 100%;
            max-height: 850px;
            border: 1px solid #ddd;
            display: block;
            margin: 10px auto;
        }
    </style>
</head>
<body>
    <!-- HALAMAN 1 -->
    <div class="header">
        <table style="width: 100%;">
            <tr>
                <td>
                    <h1 class="header-title">SURAT PERINTAH KERJA (SPK)</h1>
                    <div class="header-meta">
                        ORDER: <strong>{{ $record->order->order_number }}</strong> | 
                        TGL: {{ now()->format('d/m/Y') }}
                    </div>
                </td>
                <td style="text-align: right;">
                    <div style="font-size: 13pt; font-weight: bold;">{{ strtoupper($record->product_name) }}</div>
                    <div class="badge badge-primary">
                        {{ match($record->production_category) {
                            'custom' => 'CUSTOM (UKUR BADAN)',
                            'non_produksi' => 'NON-PRODUKSI',
                            'jasa' => 'JASA',
                            default => 'KONVEKSI'
                        } }}
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">1. INFO PRODUKSI & DEADLINE</div>
        <table class="grid">
            <tr>
                <td class="info-label">Pelanggan</td>
                <td class="info-value">: {{ $record->order->customer->name ?? '-' }}</td>
                <td class="info-label">Bahan Utama</td>
                <td class="info-value">: {{ $record->bahan ? ($record->bahan->material->name . ' - ' . $record->bahan->color_name) : 'Lihat Catatan' }}</td>
            </tr>
            <tr>
                <td class="info-label">Deadline Produksi</td>
                <td class="info-value text-danger text-bold">: {{ \Carbon\Carbon::parse($record->order->deadline)->format('d F Y') }}</td>
                <td class="info-label">Total Qty</td>
                <td class="info-value text-bold">: {{ $totalQuantity }} pcs</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">2. KONFIGURASI SPESIFIKASI DETAIL (PER GRUP)</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 20px;">#</th>
                    <th style="width: 45px;">Gender</th>
                    <th style="width: 150px;">Detail Model (Lengan/Saku/Kancing)</th>
                    <th>Aplikasi Bordir / Sablon & Lokasi</th>
                    <th style="width: 150px;">Ukuran & Qty</th>
                    <th style="width: 40px; text-align: center;">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($specGroups as $index => $group)
                <tr>
                    <td style="text-align: center;">{{ $loop->iteration }}</td>
                    <td style="text-align: center;"><strong>{{ $group['gender'] }}</strong></td>
                    <td>
                        <div style="margin-bottom: 3px;">
                            <span class="spec-tag">LENGAN: {{ $group['sleeve'] }}</span>
                            <span class="spec-tag">SAKU: {{ $group['pocket'] }}</span>
                            <span class="spec-tag">KANCING: {{ $group['button'] }}</span>
                            @if($group['tunic'] === 'TUNIK') <span class="spec-tag">MODEL: TUNIK</span> @endif
                        </div>
                        @if(!empty($group['requests']))
                            <div style="font-size: 7.5pt; color: #555; margin-top: 3px; border-top: 1px dashed #ccc; padding-top: 2px;">
                                <strong>REQ:</strong> {{ implode(' | ', $group['requests']) }}
                            </div>
                        @endif
                    </td>
                    <td>
                        @if(!empty($group['sablon_bordir']))
                            @foreach($group['sablon_bordir'] as $sb)
                                <span class="sb-tag">{{ $sb }}</span>
                            @endforeach
                        @else
                            <span style="color: #999; font-style: italic;">Tanpa Aplikasi</span>
                        @endif
                    </td>
                    <td>
                        @foreach($group['sizes'] as $sz => $q)
                            <div class="size-badge">{{ $sz }}:<strong>{{ $q }}</strong></div>
                        @endforeach
                    </td>
                    <td style="text-align: center; font-weight: bold; font-size: 10pt;">{{ $group['total_qty'] }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if($record->production_category === 'custom' || collect($allGroupItems)->contains(fn($i) => !empty($i->size_and_request_details['detail_custom'])))
    <div class="section">
        <div class="section-title">3. DETAIL UKURAN BADAN & NAMA (KHUSUS CUSTOM)</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 25px;">No</th>
                    <th>Nama Penerima / Nama di Baju</th>
                    <th style="width: 45px;">Size</th>
                    <th style="width: 200px;">Rincian Ukuran (LD / PB / PL / LB / LP / LPh)</th>
                    <th>Instruksi Tambahan</th>
                </tr>
            </thead>
            <tbody>
                @php $customIdx = 1; @endphp
                @foreach($allGroupItems as $gi)
                    @php $customs = $gi->size_and_request_details['detail_custom'] ?? []; @endphp
                    @foreach($customs as $u)
                        <tr>
                            <td>{{ $customIdx++ }}</td>
                            <td class="text-bold">{{ $u['nama'] ?? '-' }}</td>
                            <td style="text-align: center;">{{ $u['ukuran'] ?? $gi->size }}</td>
                            <td>
                                @if(!empty($u['LD']) || !empty($u['PB']))
                                    LD:{{ $u['LD'] ?? '-' }} | PB:{{ $u['PB'] ?? '-' }} | PL:{{ $u['PL'] ?? '-' }} | LB:{{ $u['LB'] ?? '-' }}
                                    @if(!empty($u['LP'])) | LP:{{ $u['LP'] }} @endif
                                    @if(!empty($u['LPh'])) | LPh:{{ $u['LPh'] }} @endif
                                @else - @endif
                            </td>
                            <td>{{ $u['keterangan'] ?? '-' }}</td>
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <div class="section">
        <div class="section-title">4. DAFTAR PEMBAGIAN TUGAS PRODUKSI</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 120px;">Tahap Pekerjaan</th>
                    <th style="width: 140px;">Karyawan</th>
                    <th style="width: 170px;">Qty & Rincian Ukuran</th>
                    <th>Instruksi Tambahan</th>
                </tr>
            </thead>
            <tbody>
                @foreach($record->productionTasks as $task)
                    <tr>
                        <td class="text-bold" style="background: #f9fafb;">{{ strtoupper($task->stage_name) }}</td>
                        <td class="text-bold">{{ $task->assignedTo->name ?? 'BELUM ADA' }}</td>
                        <td>
                            <span style="font-size: 10pt; font-weight: bold;">{{ $task->quantity }} pcs</span>
                            <div style="font-size: 7.5pt; color: #444; margin-top: 2px;">
                                @if(!empty($task->size_quantities))
                                    @foreach($task->size_quantities as $sz => $q)
                                        @if($q > 0) {{ $sz }}:{{ $q }}{{ !$loop->last ? ',' : '' }} @endif
                                    @endforeach
                                @else - @endif
                            </div>
                        </td>
                        <td style="font-style: italic; font-size: 8pt;">{{ $task->description ?: '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </tbody>
    </table>

    <table class="signature-grid">
        <tr>
            <td class="signature-box">
                <div style="font-size: 7.5pt;">Admin / Designer</div>
                <div class="signature-line"></div>
            </td>
            <td class="signature-box">
                <div style="font-size: 7.5pt;">Supervisor</div>
                <div class="signature-line"></div>
            </td>
            <td class="signature-box">
                <div style="font-size: 7.5pt;">Kepala Produksi</div>
                <div class="signature-line"></div>
            </td>
        </tr>
    </table>

    <div class="footer">Dicetak via Sistem Konveksio. Dokumen ini adalah instruksi resmi.</div>

    @if($record->design_image)
        <div class="page-break"></div>
        <div class="header">
            <h1 class="header-title">REFERENSI DESAIN - {{ $record->order->order_number }}</h1>
            <div class="header-meta">Produk: {{ strtoupper($record->product_name) }}</div>
        </div>
        <div class="design-container">
            <img src="{{ public_path('storage/' . $record->design_image) }}" class="full-design-image" alt="Desain Full">
        </div>
    @endif
</body>
</html>
