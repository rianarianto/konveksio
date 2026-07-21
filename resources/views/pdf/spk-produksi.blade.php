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
                            'custom' => 'PRODUKSI',
                            'non_produksi' => 'NON-PRODUKSI',
                            'jasa' => 'JASA',
                            default => 'PRODUKSI'
                        } }}
                    </div>
                </td>
            </tr>
        </table>
    </div>

    @php
        $totalStockUsed = collect($allGroupItems)->sum(function($i) {
            return (float) ($i->size_and_request_details['stock_qty_used'] ?? 0);
        });
        $stockUnit = 'pcs';
        if (in_array($record->production_category, ['produksi', 'custom'])) {
            $stockUnit = $record->bahan?->material?->unit ?? 'm';
        }
        $fmtStock = $totalStockUsed == (int)$totalStockUsed ? (int)$totalStockUsed : number_format($totalStockUsed, 1, ',', '.');
    @endphp

    <div class="section">
        <div class="section-title">1. INFO PRODUKSI & DEADLINE</div>
        <table class="grid">
            <tr>
                <td class="info-label">Nama Pesanan</td>
                <td class="info-value" style="font-weight: bold; color: #7c3aed;">: {{ strtoupper($record->product_name) }}</td>
                <td class="info-label">Total Qty</td>
                <td class="info-value text-bold">: {{ $totalQuantity }} pcs</td>
            </tr>
            <tr>
                <td class="info-label">Pelanggan</td>
                <td class="info-value">: {{ $record->order->customer->name ?? '-' }}</td>
                <td class="info-label">Deadline Produksi</td>
                <td class="info-value text-danger text-bold">: {{ \Carbon\Carbon::parse($record->order->deadline)->format('d F Y') }}</td>
            </tr>
            <tr>
                @if(in_array($record->production_category, ['produksi', 'custom']))
                    <td class="info-label">Bahan Utama</td>
                    <td class="info-value" colspan="3">
                        : {{ $record->bahan ? ($record->bahan->material->name . ' - ' . $record->bahan->color_name) : 'Lihat Catatan' }}
                        @if($totalStockUsed > 0)
                            <span style="color: #d97706; font-weight: bold; margin-left: 4px;">(Stok {{ $fmtStock }}{{ $stockUnit }})</span>
                        @endif
                    </td>
                @elseif($record->production_category === 'non_produksi')
                    <td class="info-label">Produk Katalog</td>
                    <td class="info-value" colspan="3">
                        : {{ $record->size_and_request_details['supplier_product'] ? (\App\Models\Product::find($record->size_and_request_details['supplier_product'])?->name ?? 'Non-Produksi') : 'Non-Produksi' }}
                        @if($totalStockUsed > 0)
                            <span style="color: #d97706; font-weight: bold; margin-left: 4px;">(Ambil Stok: {{ $fmtStock }} pcs)</span>
                        @endif
                    </td>
                @else
                    <td class="info-label">Jenis Layanan</td>
                    <td class="info-value" colspan="3">: Jasa Makloon / Tanpa Bahan</td>
                @endif
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
                    <th>Aplikasi (Titik & Ket)</th>
                    <th style="width: 150px;">Ukuran & Qty</th>
                    <th style="width: 40px; text-align: center;">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($specGroups as $index => $group)
                <tr>
                    <td style="text-align: center;">{{ $loop->iteration }}</td>
                    <td style="text-align: center;"><strong>{{ $group['gender'] === 'UMUM' ? '-' : $group['gender'] }}</strong></td>
                    <td>
                        <div style="margin-bottom: 3px;">
                            @if($group['gender'] !== 'UMUM')
                                <span class="spec-tag">LENGAN: {{ $group['sleeve'] }}</span>
                                <span class="spec-tag">SAKU: {{ $group['pocket'] }}</span>
                                <span class="spec-tag">KANCING: {{ $group['button'] }}</span>
                                <span class="spec-tag">KERAH: {{ $group['collar'] }}</span>
                                @if($group['tunic'] === 'TUNIK') <span class="spec-tag">MODEL: TUNIK</span> @endif
                            @endif
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
                            <div class="size-badge">{{ $sz === 'TANPA_UKURAN' ? 'Tanpa Ukuran' : $sz }}:<strong>{{ $q }}</strong></div>
                        @endforeach
                    </td>
                    <td style="text-align: center; font-weight: bold; font-size: 10pt;">{{ $group['total_qty'] }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="section" style="border: 1px solid #7c3aed; padding: 6px; border-radius: 4px; background: #fafafa; min-height: 40px;">
        <div style="font-size: 8.5pt; font-weight: bold; color: #7c3aed; border-bottom: 1px solid #7c3aed; margin-bottom: 4px; padding-bottom: 2px;">
            3. DAFTAR NAMA PENERIMA & DETAIL CUSTOM (PER GRUP)
        </div>

        @php $hasAnyRecipients = false; @endphp
        @foreach($specGroups as $group)
            @php 
                $rec = $group['recipients'] ?? [];
                $hasRec = !empty($rec['standard']) || !empty($rec['custom']);
            @endphp
            
            @if($hasRec)
                @php $hasAnyRecipients = true; @endphp
                <div style="margin-bottom: 8px; {{ !$loop->last ? 'border-bottom: 1px dashed #ddd; padding-bottom: 6px;' : '' }}">
                    <div style="font-size: 8pt; font-weight: bold; color: #555; margin-bottom: 3px;">
                        GRUP #{{ $loop->iteration }} ({{ $group['gender'] }} - {{ $group['sleeve'] }})
                    </div>
                    
                    {{-- Standard Sizes with Names --}}
                    @if(!empty($rec['standard']))
                        @foreach($rec['standard'] as $sz => $names)
                            <div style="font-size: 7.5pt; margin-bottom: 2px;">
                                <strong style="background: #f3e8ff; padding: 0 4px; border-radius: 2px; color: #7c3aed;">{{ $sz === 'TANPA_UKURAN' ? 'Tanpa Ukuran' : $sz }}</strong>: {{ implode(', ', $names) }}
                            </div>
                        @endforeach
                    @endif

                    {{-- Custom Measurements --}}
                    @if(!empty($rec['custom']))
                        <table style="width: 100%; border: none; border-collapse: collapse;">
                            @foreach(array_chunk($rec['custom'], 2) as $row)
                                <tr>
                                    @foreach($row as $ce)
                                        <td style="width: 50%; border: none; padding: 2px 0; vertical-align: top; font-size: 7.5pt;">
                                            <span class="text-bold">• {{ $ce['nama'] }}</span> ({{ $ce['size'] }}) 
                                            @if($ce['desc']) <br><span style="color: #666; font-size: 7pt; font-style: italic; margin-left: 10px;">{{ $ce['desc'] }}</span> @endif
                                        </td>
                                    @endforeach
                                    @if(count($row) < 2) <td style="width: 50%; border: none;"></td> @endif
                                </tr>
                            @endforeach
                        </table>
                    @endif
                </div>
            @endif
        @endforeach

        @if(!$hasAnyRecipients)
            <div style="font-style: italic; color: #999; font-size: 8pt; text-align: center; padding: 10px;">
                Tidak ada data nama penerima atau ukuran custom khusus.
            </div>
        @endif
    </div>
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
                        <td class="text-bold" style="background: #f9fafb;">{{ strtoupper(str_replace('_', ' ', $task->stage_name)) }}</td>
                        <td class="text-bold">{{ $task->assignedTo->name ?? 'BELUM ADA' }}</td>
                        <td>
                            <span style="font-size: 10pt; font-weight: bold;">{{ $task->quantity }} pcs</span>
                            <div style="font-size: 7.5pt; color: #444; margin-top: 2px;">
                                @if(!empty($task->size_quantities))
                                    @foreach($task->size_quantities as $sz => $q)
                                        @if(!str_starts_with($sz, '_') && $q > 0) {{ $sz === 'TANPA_UKURAN' ? 'Tanpa Ukuran' : $sz }}:{{ $q }}{{ !$loop->last ? ',' : '' }} @endif
                                    @endforeach
                                @else - @endif
                            </div>
                        </td>
                        <td>
                            @if($task->description)
                                @php
                                    $lines = explode("\n", $task->description);
                                @endphp
                                @foreach($lines as $line)
                                    @if(str_starts_with(trim($line), 'Pembagian Ukuran:') || str_starts_with(trim($line), 'Baju Custom:'))
                                        <div style="font-size: 7.5pt; color: #6b7280; margin-top: 3px;">{{ $line }}</div>
                                    @else
                                        <div style="font-size: 8pt; font-weight: bold; color: #1f2937; font-style: italic; margin-bottom: 2px;">{{ $line }}</div>
                                    @endif
                                @endforeach
                            @else
                                -
                            @endif
                        </td>
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

    <div class="footer">Dicetak via Sistem {{ $record->order->shop->name ?? 'Konveksio' }}. Dokumen ini adalah instruksi resmi.</div>

    @php
        $base64Image = null;
        if ($record->design_image && file_exists(public_path('storage/' . $record->design_image))) {
            $imagePath = public_path('storage/' . $record->design_image);
            $imageData = base64_encode(file_get_contents($imagePath));
            $imageType = pathinfo($imagePath, PATHINFO_EXTENSION);
            $base64Image = 'data:image/' . $imageType . ';base64,' . $imageData;
        }
    @endphp

    @if($base64Image)
        <div class="page-break"></div>
        <div class="header">
            <h1 class="header-title">REFERENSI DESAIN - {{ $record->order->order_number }}</h1>
            <div class="header-meta">Produk: {{ strtoupper($record->product_name) }}</div>
        </div>
        <div class="design-container">
            <img src="{{ $base64Image }}" class="full-design-image" alt="Desain Full">
        </div>
    @endif
</body>
</html>
