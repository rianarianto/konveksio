<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;


class PDFController extends Controller
{
    public function downloadReceipt(Order $order, Request $request)
    {
        // Load relationships needed for the receipt
        $order->load(['customer', 'shop', 'orderItems', 'payments']);

        $pdf = app('dompdf.wrapper')->loadView('pdf.receipt', [
            'order' => $order,
        ]);

        $filename = 'Kuitansi-' . str_replace('#', '', $order->order_number) . '.pdf';

        if ($request->query('download') == 1) {
            $pdfOutput = $pdf->output();
            return response($pdfOutput, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);
        }

        $pdfOutput = $pdf->output();
        return response($pdfOutput, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline',
        ]);
    }

    public function downloadSpk(\App\Models\WorkOrder $wo, Request $request)
    {
        $record = $wo->orderItem;
        if (!$record) {
            abort(404, 'Data pesanan tidak ditemukan.');
        }

        // Aggregate data for PDF (sama persis dengan ControlProduksiResource)
        $totalQuantity = \App\Models\OrderItem::where('order_id', $record->order_id)
            ->where('product_name', $record->product_name)
            ->where('design_status', 'approved')
            ->sum('quantity');

        $allGroupItems = \App\Models\OrderItem::where('order_id', $record->order_id)
            ->where('product_name', $record->product_name)
            ->where('design_status', 'approved')
            ->get();

        $sizes = [];
        $specGroups = [];

        foreach ($allGroupItems as $gi) {
            $d = $gi->size_and_request_details ?? [];
            $isProduksi = in_array($gi->production_category ?? 'produksi', ['produksi', 'custom']);
            
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
            
            $stk = !empty($d['use_stock']) ? 'YES' : 'NO';
            
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

            $groupKey = "{$gen}|{$slv}|{$pck}|{$btn}|{$tun}|{$clr}|{$sbStr}|{$reqStr}";
            
            if (!isset($specGroups[$groupKey])) {
                $specGroups[$groupKey] = [
                    'gender' => $gen === 'UMUM' ? 'UMUM' : ($gen === 'L' ? 'PRIA' : 'WANITA'),
                    'is_produksi' => $isProduksi,
                    'sleeve' => $slv,
                    'pocket' => $pck,
                    'button' => $btn,
                    'tunic' => $tun,
                    'collar' => $clr,
                    'use_stock' => ($stk === 'YES'),
                    'sablon_bordir' => $sbList,
                    'requests' => $reqList,
                    'total_qty' => 0,
                    'sizes' => [],
                    'recipients' => ['standard' => [], 'custom' => []],
                ];
            }
            
            $specGroups[$groupKey]['total_qty'] += $gi->quantity;
            $sz = strtoupper($gi->size ?? 'TANPA_UKURAN');
            $specGroups[$groupKey]['sizes'][$sz] = ($specGroups[$groupKey]['sizes'][$sz] ?? 0) + $gi->quantity;

            if (!empty($d['detail_custom'])) {
                foreach ($d['detail_custom'] as $u) {
                    $specGroups[$groupKey]['recipients']['custom'][] = [
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
                $specGroups[$groupKey]['recipients']['custom'][] = [
                    'nama' => $gi->recipient_name ?? '-',
                    'size' => 'Custom',
                    'desc' => implode(' ', $m)
                ];
            } elseif (!empty($gi->recipient_name)) {
                $specGroups[$groupKey]['recipients']['standard'][$sz][] = $gi->recipient_name;
            }
        }

        $data = [
            'record' => $record->load(['order.customer', 'productionTasks.assignedTo', 'bahan.material']),
            'totalQuantity' => $totalQuantity,
            'sizes' => $sizes,
            'specGroups' => $specGroups,
            'allGroupItems' => $allGroupItems,
        ];

        if ($request->query('html') == 1) {
            return view('pdf.spk-produksi', $data);
        }

        $pdf = app('dompdf.wrapper')->loadView('pdf.spk-produksi', $data);

        $filename = 'SPK-' . $record->order->order_number . '-' . \Illuminate\Support\Str::slug($record->product_name) . '.pdf';

        if ($request->query('download') == 1) {
            $pdfOutput = $pdf->output();
            return response($pdfOutput, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);
        }

        $pdfOutput = $pdf->output();
        return response($pdfOutput, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline',
        ]);
    }
}

