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
            font-size: 8.5pt;
            line-height: 1.25;
            color: #111827;
            margin: 0;
            padding: 0;
        }
        .page-break {
            page-break-after: always;
        }
        .header {
            border-bottom: 2px solid #7c3aed;
            padding-bottom: 6px;
            margin-bottom: 10px;
        }
        .header-title {
            font-size: 15pt;
            font-weight: bold;
            color: #6d28d9;
            margin: 0;
            letter-spacing: -0.3px;
        }
        .header-meta {
            font-size: 8.5pt;
            color: #4b5563;
            margin-top: 2px;
        }
        .section {
            margin-bottom: 12px;
        }
        .section-title {
            font-size: 9.5pt;
            font-weight: bold;
            background: #f3e8ff;
            color: #6d28d9;
            padding: 4px 8px;
            border-left: 4px solid #7c3aed;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .grid {
            width: 100%;
            border-collapse: collapse;
        }
        .grid td {
            vertical-align: top;
            padding: 3px 2px;
        }
        .info-label {
            font-weight: bold;
            color: #374151;
            width: 130px;
            font-size: 8.5pt;
        }
        .info-value {
            font-weight: normal;
            color: #111827;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
        }
        table.data-table th, table.data-table td {
            border: 1px solid #d1d5db;
            padding: 5px 6px;
            text-align: left;
            font-size: 8pt;
            vertical-align: top;
        }
        table.data-table th {
            background-color: #f3f4f6;
            color: #1f2937;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 7.5pt;
            letter-spacing: 0.2px;
        }
        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 8pt;
            font-weight: bold;
        }
        .badge-primary { background: #f3e8ff; color: #6d28d9; border: 1px solid #ddd6fe; }
        .text-danger { color: #dc2626; }
        .text-bold { font-weight: bold; }
        
        .variant-badge {
            background: #e0e7ff;
            color: #3730a3;
            border: 1px solid #c7d2fe;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 8pt;
            font-weight: bold;
            display: inline-block;
            margin-bottom: 4px;
        }
        .spec-tag {
            background: #fdf2f8;
            color: #9d174d;
            border: 1px solid #fbcfe8;
            padding: 1px 4px;
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
            padding: 2px 5px;
            border-radius: 3px;
            font-size: 7.5pt;
            font-weight: bold;
            display: block;
            margin-top: 2px;
        }
        .note-box {
            background: #fffcf0;
            border: 1px solid #fef3c7;
            color: #92400e;
            padding: 3px 6px;
            border-radius: 3px;
            font-size: 7.5pt;
            font-weight: bold;
            margin-top: 4px;
        }
        .size-badge {
            display: inline-block;
            border: 1px solid #d1d5db;
            padding: 2px 5px;
            margin: 1px;
            font-size: 8pt;
            background: #ffffff;
            border-radius: 3px;
            min-width: 32px;
            text-align: center;
        }
        .footer {
            margin-top: 16px;
            font-size: 7.5pt;
            color: #6b7280;
            text-align: center;
            border-top: 1px solid #e5e7eb;
            padding-top: 6px;
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
            margin-top: 36px;
            border-top: 1px solid #374151;
            width: 70%;
            margin-left: auto;
            margin-right: auto;
        }
        
        .full-design-image {
            max-width: 100%;
            max-height: 850px;
            border: 1px solid #e5e7eb;
            display: block;
            margin: 10px auto;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <!-- HALAMAN SPK PRODUKSI -->
    <div class="header">
        <table style="width: 100%;">
            <tr>
                <td>
                    <h1 class="header-title" style="{{ !empty($activeReturn) ? 'color:#dc2626;' : '' }}">
                        {{ !empty($activeReturn) ? 'SURAT PERINTAH KERJA (SPK PERBAIKAN RETUR)' : 'SURAT PERINTAH KERJA (SPK)' }}
                    </h1>
                    <div class="header-meta">
                        ORDER: <strong>{{ $record->order->order_number }}</strong> | 
                        TANGGAL CETAK: {{ now()->format('d/m/Y H:i') }}
                    </div>
                </td>
                <td style="text-align: right;">
                    <div style="font-size: 12pt; font-weight: bold; color:#111827;">{{ strtoupper($record->product_name) }}</div>
                    <div class="badge {{ !empty($activeReturn) ? 'badge-danger' : 'badge-primary' }}" style="margin-top:2px; {{ !empty($activeReturn) ? 'background:#fee2e2;color:#991b1b;border:1px solid #fca5a5;' : '' }}">
                        {{ !empty($activeReturn) ? '🔄 REVISI RETUR' : 'KATEGORI: ' . match($record->production_category) {
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

    @if(!empty($activeReturn))
    <div style="margin-bottom: 10px; padding: 8px 12px; background: #fef2f2; border: 1.5px solid #fca5a5; border-radius: 6px; color: #991b1b;">
        <div style="font-weight: bold; font-size: 10pt;">🔄 SPK PERBAIKAN RETUR — {{ $activeReturn->responsibility_type === 'customer_paid' ? 'RETUR BERBAYAR' : 'GARANSI TOKO' }}</div>
        <div style="font-size: 8.5pt; margin-top: 3px;">
            📌 <strong>Jumlah yang Perlu Diperbaiki:</strong> {{ $activeReturn->quantity }} pcs 
            @if(!empty($activeReturn->size_breakdown))
                ({{ implode(', ', array_map(fn($k,$v) => "$k: $v pcs", array_keys($activeReturn->size_breakdown), $activeReturn->size_breakdown)) }})
            @endif
            | 🎯 <strong>Tahap Pengerjaan:</strong> {{ $activeReturn->target_stage ?: 'Bordir/Sablon' }} ➔ Direct ke QC Akhir
        </div>
        <div style="font-size: 8.5pt; margin-top: 2px;">
            📝 <strong>Alasan Komplain Pelanggan:</strong> {{ $activeReturn->reason ?: $activeReturn->items_description }}
        </div>
    </div>
    @endif

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

    {{-- SECTION 1 --}}
    <div class="section">
        <div class="section-title">1. INFO PRODUKSI & DEADLINE</div>
        <table class="grid">
            <tr>
                <td class="info-label">Nama Pesanan</td>
                <td class="info-value" style="font-weight: bold; color: #6d28d9;">: {{ strtoupper($record->product_name) }}</td>
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
            @if(!empty($record->order->notes))
            <tr>
                <td class="info-label">Catatan Umum Pesanan</td>
                <td class="info-value" colspan="3" style="color:#b45309; font-weight:bold;">: {{ $record->order->notes }}</td>
            </tr>
            @endif
        </table>
    </div>

    {{-- SECTION 2 --}}
    <div class="section">
        <div class="section-title">2. KONFIGURASI SPESIFIKASI DETAIL (PER GRUP VARIAN)</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 20px; text-align:center;">#</th>
                    <th style="width: 50px; text-align:center;">Gender</th>
                    <th style="width: 170px;">Detail Model (Lengan / Saku / Kancing / Kerah)</th>
                    <th>Aplikasi (Teknik, Lokasi & Keterangan)</th>
                    <th style="width: 130px;">Ukuran & Qty</th>
                    <th style="width: 40px; text-align: center;">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($specGroups as $index => $group)
                <tr>
                    <td style="text-align: center; font-weight:bold;">{{ $loop->iteration }}</td>
                    <td style="text-align: center;"><strong>{{ $group['gender'] === 'UMUM' ? '-' : $group['gender'] }}</strong></td>
                    <td>
                        @if(!empty($group['group_label']))
                        <div class="variant-badge">
                            VARIAN: {{ strtoupper($group['group_label']) }}
                        </div>
                        @endif
                        <div style="margin-bottom: 2px;">
                            @if($group['gender'] !== 'UMUM')
                                <span class="spec-tag">LENGAN: {{ $group['sleeve'] }}</span>
                                <span class="spec-tag">SAKU: {{ $group['pocket'] }}</span>
                                <span class="spec-tag">KANCING: {{ $group['button'] }}</span>
                                <span class="spec-tag">KERAH: {{ $group['collar'] }}</span>
                                @if($group['tunic'] === 'TUNIK') <span class="spec-tag">MODEL: TUNIK</span> @endif
                            @endif
                        </div>
                        @if(!empty($group['requests']))
                            <div style="font-size: 7.5pt; color: #4b5563; margin-top: 3px; border-top: 1px dashed #d1d5db; padding-top: 2px;">
                                <strong>REQ:</strong> {{ implode(' | ', $group['requests']) }}
                            </div>
                        @endif
                        @if(!empty($group['spec_notes']))
                            <div class="note-box">
                                CATATAN: {{ implode(', ', $group['spec_notes']) }}
                            </div>
                        @endif
                    </td>
                    <td>
                        @if(!empty($group['sablon_bordir']))
                            @foreach($group['sablon_bordir'] as $sb)
                                <span class="sb-tag">{{ $sb }}</span>
                            @endforeach
                        @else
                            <span style="color: #9ca3af; font-style: italic;">Tanpa Aplikasi</span>
                        @endif
                    </td>
                    <td>
                        @foreach($group['sizes'] as $sz => $q)
                            <div class="size-badge">{{ $sz === 'TANPA_UKURAN' ? 'Tanpa Ukuran' : $sz }}:<strong>{{ $q }}</strong></div>
                        @endforeach
                    </td>
                    <td style="text-align: center; font-weight: bold; font-size: 9.5pt;">{{ $group['total_qty'] }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- SECTION 3 --}}
    <div class="section" style="border: 1px solid #7c3aed; padding: 6px; border-radius: 4px; background: #fafafa;">
        <div style="font-size: 8.5pt; font-weight: bold; color: #6d28d9; border-bottom: 1px solid #ddd6fe; margin-bottom: 6px; padding-bottom: 3px;">
            3. DAFTAR NAMA PENERIMA & DETAIL CUSTOM (PER GRUP VARIAN)
        </div>

        @php $hasAnyRecipients = false; @endphp
        @foreach($specGroups as $group)
            @php 
                $rec = $group['recipients'] ?? [];
                $hasRec = !empty($rec['standard']) || !empty($rec['custom']);
            @endphp
            
            @if($hasRec)
                @php $hasAnyRecipients = true; @endphp
                <div style="margin-bottom: 8px; {{ !$loop->last ? 'border-bottom: 1px dashed #e5e7eb; padding-bottom: 6px;' : '' }}">
                    <div style="font-size: 8pt; font-weight: bold; color: #374151; margin-bottom: 3px;">
                        VARIAN #{{ $loop->iteration }} ({{ $group['group_label'] ? strtoupper($group['group_label']) . ' - ' : '' }}{{ $group['gender'] }} - {{ $group['sleeve'] }})
                    </div>
                    
                    {{-- Standard Sizes with Names --}}
                    @if(!empty($rec['standard']))
                        @foreach($rec['standard'] as $sz => $names)
                            <div style="font-size: 7.5pt; margin-bottom: 2px;">
                                <strong style="background: #f3e8ff; padding: 1px 5px; border-radius: 2px; color: #6d28d9;">{{ $sz === 'TANPA_UKURAN' ? 'Tanpa Ukuran' : $sz }}</strong>: {{ implode(', ', $names) }}
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
                                            @if($ce['desc']) <br><span style="color: #4b5563; font-size: 7pt; font-weight: bold; margin-left: 10px;">{{ $ce['desc'] }}</span> @endif
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
            <div style="font-style: italic; color: #9ca3af; font-size: 8pt; text-align: center; padding: 6px;">
                Tidak ada data nama penerima atau ukuran custom khusus.
            </div>
        @endif
    </div>

    {{-- SECTION 4 --}}
    <div class="section">
        <div class="section-title">4. DAFTAR PEMBAGIAN TUGAS PRODUKSI</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 140px;">Karyawan</th>
                    <th>Qty & Rincian Ukuran Per Varian</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $groupedTasks = $record->productionTasks->groupBy(function($task) {
                        return strtoupper($task->stage_name);
                    });
                @endphp

                @foreach($groupedTasks as $stageName => $tasks)
                    @php
                        $firstTask = $tasks->first();
                        
                        $stageInst = '';
                        if ($firstTask->description) {
                            $lines = explode("\n", str_replace('TANPA_UKURAN', 'Tanpa Ukuran', $firstTask->description));
                            $cleanLines = [];
                            foreach ($lines as $line) {
                                $trimmed = trim($line);
                                if (!str_starts_with($trimmed, 'Pembagian Ukuran:') && !str_starts_with($trimmed, 'Baju Custom:')) {
                                    if (!empty($trimmed)) $cleanLines[] = e($trimmed);
                                }
                            }
                            $stageInst = implode(" | ", $cleanLines);
                        }
                    @endphp

                    {{-- Stage Sub-Header Banner (Full Width, Page-Break Safe) --}}
                    <tr style="background: #f3e8ff; border-top: 1.5px solid #7c3aed; page-break-inside: avoid;">
                        <td colspan="2" style="padding: 6px 10px; font-weight: bold; font-size: 9pt; color: #5b21b6;">
                            TAHAP: {{ str_replace('_', ' ', $stageName) }}
                            @if(!empty($stageInst))
                                <span style="font-weight: bold; color: #1e293b; font-size: 8.5pt; margin-left: 10px;">
                                    (Instruksi: {{ $stageInst }})
                                </span>
                            @endif
                        </td>
                    </tr>

                    @foreach($tasks as $task)
                        @php
                            $taskVariantBreakdown = [];
                            if (!empty($task->size_quantities)) {
                                $taskSizes = $task->size_quantities;
                                $taskCustomRecipients = $taskSizes['_custom_recipients'] ?? [];

                                foreach ($allGroupItems as $gi) {
                                    $d = $gi->size_and_request_details ?? [];
                                    $groupLabel = trim($d['group_label'] ?? '');
                                    $gender = strtoupper($d['gender'] ?? 'L');
                                    $genderLabel = ($gender === 'P' || $gender === 'PEREMPUAN') ? 'PEREMPUAN' : 'LAKI-LAKI';

                                    $vTitle = $groupLabel ? strtoupper($groupLabel) : $genderLabel;
                                    $vKey = $vTitle . '|' . $genderLabel;

                                    $giSize = strtoupper($gi->size ?? 'TANPA_UKURAN');

                                    if ($giSize === 'CUSTOM' || $gi->production_category === 'custom') {
                                        if (isset($taskSizes['CUSTOM']) && $taskSizes['CUSTOM'] > 0) {
                                            $customDetails = $d['detail_custom'] ?? [];
                                            if (!empty($customDetails)) {
                                                foreach ($customDetails as $cd) {
                                                    $cName = trim($cd['nama'] ?? '');
                                                    if (empty($taskCustomRecipients) || in_array($cName, $taskCustomRecipients)) {
                                                        if (!isset($taskVariantBreakdown[$vKey])) {
                                                            $taskVariantBreakdown[$vKey] = [
                                                                'title' => $vTitle,
                                                                'gender' => $genderLabel,
                                                                'sizes' => [],
                                                                'customs' => [],
                                                            ];
                                                        }
                                                        $taskVariantBreakdown[$vKey]['customs'][] = $cName ?: 'Custom';
                                                    }
                                                }
                                            } else {
                                                $cName = trim($gi->recipient_name ?? 'Custom');
                                                if (empty($taskCustomRecipients) || in_array($cName, $taskCustomRecipients)) {
                                                    if (!isset($taskVariantBreakdown[$vKey])) {
                                                        $taskVariantBreakdown[$vKey] = [
                                                            'title' => $vTitle,
                                                            'gender' => $genderLabel,
                                                            'sizes' => [],
                                                            'customs' => [],
                                                        ];
                                                    }
                                                    $taskVariantBreakdown[$vKey]['customs'][] = $cName;
                                                }
                                            }
                                        }
                                    } else {
                                        $taskQtyForSize = $taskSizes[$giSize] ?? 0;
                                        if ($taskQtyForSize > 0) {
                                            $allocatedQty = (int) $taskQtyForSize;
                                            if (isset($taskSizes['_genders'][$giSize])) {
                                                $gList = $taskSizes['_genders'][$giSize];
                                                if ($gender === 'P' || $gender === 'PEREMPUAN') {
                                                    $gAlloc = (int) ($gList['P'] ?? $gList['PEREMPUAN'] ?? $gList['WANITA'] ?? 0);
                                                } else {
                                                    $gAlloc = (int) ($gList['L'] ?? $gList['LAKI-LAKI'] ?? $gList['PRIA'] ?? 0);
                                                }
                                                if ($gAlloc > 0) $allocatedQty = $gAlloc;
                                            }
                                            
                                            if ($allocatedQty > 0) {
                                                if (!isset($taskVariantBreakdown[$vKey])) {
                                                    $taskVariantBreakdown[$vKey] = [
                                                        'title' => $vTitle,
                                                        'gender' => $genderLabel,
                                                        'sizes' => [],
                                                        'customs' => [],
                                                    ];
                                                }
                                                $taskVariantBreakdown[$vKey]['sizes'][$giSize] = ($taskVariantBreakdown[$vKey]['sizes'][$giSize] ?? 0) + $allocatedQty;
                                            }
                                        }
                                    }
                                }
                            }
                        @endphp

                        <tr style="page-break-inside: avoid;">
                            <td class="text-bold" style="vertical-align:top; font-size:8.5pt; color:#1e293b; padding: 6px;">
                                {{ $task->assignedTo->name ?? 'BELUM ADA' }}
                            </td>

                            <td style="vertical-align:top; padding: 6px;">
                                <span style="font-size: 9pt; font-weight: bold; color:#111827;">{{ $task->quantity }} pcs</span>
                                @if(!empty($taskVariantBreakdown))
                                    @foreach($taskVariantBreakdown as $tv)
                                        <div style="margin-top: 5px; padding-left: 8px; border-left: 3px solid #6366f1;">
                                            <div style="font-size: 8pt; color: #4338ca; font-weight: bold;">
                                                VARIAN: {{ $tv['title'] }} ({{ $tv['gender'] }})
                                            </div>
                                            @if(!empty($tv['sizes']))
                                                <table style="border-collapse: collapse; margin-top: 3px; border: none !important;">
                                                    <tr style="border: none !important;">
                                                        <td style="padding: 0 4px 0 0; vertical-align: middle; font-size: 8pt; font-weight: bold; color: #334155; white-space: nowrap; border: none !important; background: transparent !important;">
                                                            Ukuran Standar:
                                                        </td>
                                                        @foreach($tv['sizes'] as $sz => $q)
                                                            <td style="padding: 0 3px; vertical-align: middle; border: none !important; background: transparent !important;">
                                                                <div style="background: #f1f5f9; color: #0f172a; padding: 1px 6px; border-radius: 3px; border: 1px solid #cbd5e1; font-weight: 700; font-size: 7.5pt; white-space: nowrap;">
                                                                    {{ $sz === 'TANPA_UKURAN' ? 'Tanpa Ukuran' : $sz }}: {{ $q }} pcs
                                                                </div>
                                                            </td>
                                                        @endforeach
                                                    </tr>
                                                </table>
                                            @endif
                                            @if(!empty($tv['customs']))
                                                @php
                                                    $uniqueCustoms = array_values(array_unique($tv['customs']));
                                                    $chunks = array_chunk($uniqueCustoms, 3);
                                                @endphp
                                                <div style="font-size: 7.5pt; color: #047857; font-weight: bold; margin-top: 3px;">
                                                    Daftar Penerima Custom ({{ count($uniqueCustoms) }} pcs):
                                                </div>
                                                <table style="width: 100%; border-collapse: collapse; margin-top: 2px;">
                                                    @foreach($chunks as $cRow)
                                                        <tr>
                                                            @foreach($cRow as $cName)
                                                                <td style="width: 33.33%; padding: 2px 6px; font-size: 7.5pt; color: #15803d; border: 1px solid #a7f3d0; background: #f0fdf4; font-weight: 600; vertical-align: middle;">
                                                                    • {{ $cName }}
                                                                </td>
                                                            @endforeach
                                                            @for($i = count($cRow); $i < 3; $i++)
                                                                <td style="width: 33.33%; border: none;"></td>
                                                            @endfor
                                                        </tr>
                                                    @endforeach
                                                </table>
                                            @endif
                                        </div>
                                    @endforeach
                                @else
                                    <div style="font-size: 7.5pt; color: #444; margin-top: 2px;">
                                        @if(!empty($task->size_quantities))
                                            @foreach($task->size_quantities as $sz => $q)
                                                @if(!str_starts_with($sz, '_') && $q > 0) {{ $sz === 'TANPA_UKURAN' ? 'Tanpa Ukuran' : $sz }}:{{ $q }}{{ !$loop->last ? ',' : '' }} @endif
                                            @endforeach
                                        @else - @endif
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- TANDA TANGAN --}}
    <table class="signature-grid">
        <tr>
            <td class="signature-box">
                <div style="font-size: 7.5pt; color:#4b5563;">Admin / Designer</div>
                <div class="signature-line"></div>
            </td>
            <td class="signature-box">
                <div style="font-size: 7.5pt; color:#4b5563;">Supervisor Produksi</div>
                <div class="signature-line"></div>
            </td>
            <td class="signature-box">
                <div style="font-size: 7.5pt; color:#4b5563;">Kepala Produksi</div>
                <div class="signature-line"></div>
            </td>
        </tr>
    </table>

    <div class="footer">Dicetak via Sistem {{ $record->order->shop->name ?? 'Konveksio' }}. Dokumen ini adalah instruksi resmi produksi.</div>

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
            <div class="header-meta">PRODUK: {{ strtoupper($record->product_name) }}</div>
        </div>
        <div class="design-container">
            <img src="{{ $base64Image }}" class="full-design-image" alt="Desain Full">
        </div>
    @endif
</body>
</html>
