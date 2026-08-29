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
            $tempPath = tempnam(sys_get_temp_dir(), 'pdf_');
            $pdf->save($tempPath);
            return response()->download($tempPath, $filename)->deleteFileAfterSend(true);
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'pdf_');
        $pdf->save($tempPath);
        return response()->file($tempPath, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }

    public function downloadSpkByOrder(Order $order, Request $request)
    {
        $wo = \App\Models\WorkOrder::whereHas('orderItem', fn($q) => $q->where('order_id', $order->id))->first();
        if (!$wo) {
            $item = $order->orderItems()->first();
            if ($item) {
                $data = $this->buildSpkData($item);
                $pdf = app('dompdf.wrapper')->loadView('pdf.spk-produksi', $data);
                $filename = 'SPK-' . str_replace('#', '', $order->order_number) . '.pdf';

                $tempPath = tempnam(sys_get_temp_dir(), 'pdf_');
                $pdf->save($tempPath);
                return response()->file($tempPath, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'inline; filename="' . $filename . '"',
                ]);
            }
            abort(404, 'Data pesanan tidak ditemukan.');
        }

        return $this->downloadSpk($wo, $request);
    }

    public function downloadSpk(\App\Models\WorkOrder $wo, Request $request)
    {
        $record = $wo->orderItem;
        if (!$record) {
            abort(404, 'Data pesanan tidak ditemukan.');
        }

        $data = $this->buildSpkData($record);

        if ($request->query('html') == 1) {
            return view('pdf.spk-produksi', $data);
        }

        $pdf = app('dompdf.wrapper')->loadView('pdf.spk-produksi', $data);

        $filename = 'SPK-' . $record->order->order_number . '-' . \Illuminate\Support\Str::slug($record->product_name) . '.pdf';

        if ($request->query('download') == 1) {
            $tempPath = tempnam(sys_get_temp_dir(), 'pdf_');
            $pdf->save($tempPath);
            return response()->download($tempPath, $filename)->deleteFileAfterSend(true);
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'pdf_');
        $pdf->save($tempPath);
        return response()->file($tempPath, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }

    protected function buildSpkData(\App\Models\OrderItem $record): array
    {
        $allGroupItems = $record->getItemsInGroup();
        $groupItemIds  = collect($allGroupItems)->pluck('id')->toArray();

        // Detect active retur FIRST so we can scope the build accordingly
        $activeReturn = \App\Models\OrderReturn::whereIn('order_item_id', $groupItemIds)
            ->where('status', '!=', 'selesai')
            ->latest('id')
            ->first();

        // For retur SPK: only iterate over the order_items listed in _raw
        if ($activeReturn) {
            $rawEntries = [];
            $breakdown  = $activeReturn->size_breakdown ?? [];
            if (is_array($breakdown) && !empty($breakdown['_raw'])) {
                $rawEntries = $breakdown['_raw'];
            }
            // Build a map: order_item_id => return_qty
            $returItemQtyMap = [];
            foreach ($rawEntries as $entry) {
                $oid = $entry['order_item_id'] ?? null;
                $qty = $entry['return_qty'] ?? 0;
                if ($oid) $returItemQtyMap[$oid] = (int) $qty;
            }

            $specGroups    = [];
            $sizes         = [];
            $totalQuantity = $activeReturn->quantity;

            $returItems = \App\Models\OrderItem::withoutGlobalScopes()
                ->whereIn('id', array_keys($returItemQtyMap))
                ->get();

            foreach ($returItems as $gi) {
                $d          = $gi->size_and_request_details ?? [];
                $returQty   = $returItemQtyMap[$gi->id] ?? 0;
                $isProduksi = in_array($gi->production_category ?? 'produksi', ['produksi', 'custom']);

                if (!$isProduksi) {
                    $gen = 'UMUM'; $slv = '-'; $pck = '-'; $btn = '-'; $tun = '-'; $clr = '-';
                } else {
                    $gen = $d['gender'] ?? 'L';
                    $slv = strtoupper($d['sleeve_model'] ?? 'PENDEK');
                    $pck = strtoupper(str_replace('_', ' ', $d['pocket_model'] ?? 'TANPA SAKU'));
                    $btn = strtoupper($d['button_model'] ?? 'BIASA');
                    $tun = !empty($d['is_tunic']) ? 'TUNIK' : 'STANDAR';
                    $clr = strtoupper($d['collar_model'] ?? 'BIASA');
                }

                $sbList = [];
                if (!empty($d['sablon_bordir'])) {
                    foreach ($d['sablon_bordir'] as $sb) {
                        $ket = !empty($sb['keterangan']) ? ' - ' . strtoupper($sb['keterangan']) : '';
                        $sbList[] = strtoupper($sb['jenis'] ?? '') . ' (' . strtoupper($sb['lokasi'] ?? '') . $ket . ')';
                    }
                } elseif (!empty($d['sablon_jenis'])) {
                    $ket = !empty($d['sablon_keterangan']) ? ' - ' . strtoupper($d['sablon_keterangan']) : '';
                    $sbList[] = strtoupper($d['sablon_jenis']) . ' (' . strtoupper($d['sablon_lokasi'] ?? '-') . $ket . ')';
                }
                sort($sbList);
                $sbStr = implode(' | ', $sbList);

                $reqList = [];
                if (!empty($d['request_tambahan']) && is_array($d['request_tambahan'])) {
                    foreach ($d['request_tambahan'] as $rt) {
                        $reqList[] = is_array($rt)
                            ? strtoupper($rt['jenis'] ?? '') . ': ' . ($rt['keterangan'] ?? '')
                            : strtoupper($rt);
                    }
                }
                sort($reqList);

                $grpLabel = trim($d['group_label'] ?? '');
                $gen      = trim($gen);
                $groupKey = "{$grpLabel}|{$gen}|{$slv}|{$pck}|{$btn}|{$tun}|{$clr}|{$sbStr}";

                if (!isset($specGroups[$groupKey])) {
                    $specGroups[$groupKey] = [
                        'group_label'  => $grpLabel,
                        'gender'       => $gen === 'UMUM' ? 'UMUM' : ($gen === 'L' ? 'PRIA' : 'WANITA'),
                        'is_produksi'  => $isProduksi,
                        'sleeve'       => $slv,
                        'pocket'       => $pck,
                        'button'       => $btn,
                        'tunic'        => $tun,
                        'collar'       => $clr,
                        'use_stock'    => false,
                        'sablon_bordir'=> $sbList,
                        'requests'     => $reqList,
                        'spec_notes'   => [],
                        'total_qty'    => 0,
                        'sizes'        => [],
                        'recipients'   => ['standard' => [], 'custom' => []],
                    ];
                }

                if (!empty($d['spec_notes']) && !in_array($d['spec_notes'], $specGroups[$groupKey]['spec_notes'])) {
                    $specGroups[$groupKey]['spec_notes'][] = $d['spec_notes'];
                }

                $sz = strtoupper($gi->size ?? 'TANPA_UKURAN');
                $specGroups[$groupKey]['total_qty']       += $returQty;
                $specGroups[$groupKey]['sizes'][$sz]       = ($specGroups[$groupKey]['sizes'][$sz] ?? 0) + $returQty;
                $sizes[$sz]                                = ($sizes[$sz] ?? 0) + $returQty;
            }

            $totalStockUsed = 0;
            $stockUnit      = 'm';
            $fmtStock       = '0';
        } else {
            // Normal (non-retur) build — use all group items
            $totalQuantity  = $allGroupItems->sum('quantity');
            $sizes          = [];
            $specGroups     = [];

            foreach ($allGroupItems as $gi) {
                $d          = $gi->size_and_request_details ?? [];
                $isProduksi = in_array($gi->production_category ?? 'produksi', ['produksi', 'custom']);

                if (!$isProduksi) {
                    $gen = 'UMUM'; $slv = '-'; $pck = '-'; $btn = '-'; $tun = '-'; $clr = '-';
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
                        $ket = !empty($sb['keterangan']) ? ' - ' . strtoupper($sb['keterangan']) : '';
                        $sbList[] = strtoupper($sb['jenis'] ?? '') . ' (' . strtoupper($sb['lokasi'] ?? '') . $ket . ')';
                    }
                } elseif (!empty($d['sablon_jenis'])) {
                    $ket = !empty($d['sablon_keterangan']) ? ' - ' . strtoupper($d['sablon_keterangan']) : '';
                    $sbList[] = strtoupper($d['sablon_jenis']) . ' (' . strtoupper($d['sablon_lokasi'] ?? '-') . $ket . ')';
                }
                sort($sbList);
                $sbStr = implode(' | ', $sbList);

                $reqList = [];
                if (!empty($d['request_tambahan']) && is_array($d['request_tambahan'])) {
                    foreach ($d['request_tambahan'] as $rt) {
                        $reqList[] = is_array($rt)
                            ? strtoupper($rt['jenis'] ?? '') . ': ' . ($rt['keterangan'] ?? '')
                            : strtoupper($rt);
                    }
                }
                sort($reqList);
                $reqStr = implode(' | ', $reqList);

                $grpLabel = trim($d['group_label'] ?? '');
                $gen      = trim($gen);
                $slv      = trim($slv); $pck = trim($pck); $btn = trim($btn); $tun = trim($tun);
                $sbStr    = trim($sbStr); $reqStr = trim($reqStr);

                $groupKey = "{$grpLabel}|{$gen}|{$slv}|{$pck}|{$btn}|{$tun}|{$clr}|{$sbStr}|{$reqStr}";

                if (!isset($specGroups[$groupKey])) {
                    $specGroups[$groupKey] = [
                        'group_label'  => $grpLabel,
                        'gender'       => $gen === 'UMUM' ? 'UMUM' : ($gen === 'L' ? 'PRIA' : 'WANITA'),
                        'is_produksi'  => $isProduksi,
                        'sleeve'       => $slv,
                        'pocket'       => $pck,
                        'button'       => $btn,
                        'tunic'        => $tun,
                        'collar'       => $clr,
                        'use_stock'    => ($stk === 'YES'),
                        'sablon_bordir'=> $sbList,
                        'requests'     => $reqList,
                        'spec_notes'   => [],
                        'total_qty'    => 0,
                        'sizes'        => [],
                        'recipients'   => ['standard' => [], 'custom' => []],
                    ];
                }

                if (!empty($d['spec_notes']) && !in_array($d['spec_notes'], $specGroups[$groupKey]['spec_notes'])) {
                    $specGroups[$groupKey]['spec_notes'][] = $d['spec_notes'];
                }

                $specGroups[$groupKey]['total_qty'] += $gi->quantity;
                $sz = strtoupper($gi->size ?? 'TANPA_UKURAN');
                $specGroups[$groupKey]['sizes'][$sz] = ($specGroups[$groupKey]['sizes'][$sz] ?? 0) + $gi->quantity;
                $sizes[$sz] = ($sizes[$sz] ?? 0) + $gi->quantity;

                if (!empty($d['detail_custom'])) {
                    foreach ($d['detail_custom'] as $u) {
                        $m = [];
                        foreach (['LD', 'PB', 'PL', 'LB', 'LP', 'LPh'] as $mk) {
                            if (!empty($u[$mk])) $m[] = "$mk:{$u[$mk]}";
                        }
                        $specGroups[$groupKey]['recipients']['custom'][] = [
                            'nama' => $u['nama'] ?? '-', 'size' => $u['ukuran'] ?? 'Custom', 'desc' => implode(' ', $m)
                        ];
                    }
                } elseif ($sz === 'CUSTOM') {
                    $m = [];
                    foreach (['LD', 'PB', 'PL', 'LB', 'LP', 'LPh'] as $mk) {
                        if (!empty($d[$mk])) $m[] = "$mk:{$d[$mk]}";
                    }
                    $specGroups[$groupKey]['recipients']['custom'][] = [
                        'nama' => $gi->recipient_name ?? '-', 'size' => 'Custom', 'desc' => implode(' ', $m)
                    ];
                } elseif (!empty($gi->recipient_name)) {
                    $specGroups[$groupKey]['recipients']['standard'][$sz][] = $gi->recipient_name;
                }
            }

            $totalStockUsed = collect($allGroupItems)->sum(fn($i) => (float) ($i->size_and_request_details['stock_qty_used'] ?? 0));
            $stockUnit = in_array($record->production_category, ['produksi', 'custom'])
                ? ($record->bahan?->material?->unit ?? 'm')
                : 'pcs';
            $fmtStock = $totalStockUsed == (int)$totalStockUsed
                ? (int)$totalStockUsed
                : number_format($totalStockUsed, 1, ',', '.');
        }

        return [
            'record'        => $record->load(['order.customer', 'productionTasks.assignedTo', 'bahan.material']),
            'totalQuantity' => $totalQuantity,
            'sizes'         => $sizes,
            'specGroups'    => $specGroups,
            'allGroupItems' => $allGroupItems,
            'activeReturn'  => $activeReturn,
            'totalStockUsed'=> $totalStockUsed ?? 0,
            'stockUnit'     => $stockUnit ?? 'm',
            'fmtStock'      => $fmtStock ?? '0',
        ];
    }
}

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
                    if (is_array($rt)) {
                        $reqList[] = strtoupper($rt['jenis'] ?? '') . ": " . ($rt['keterangan'] ?? '');
                    } else {
                        $reqList[] = strtoupper($rt);
                    }
                }
            }
            sort($reqList);
            $reqStr = implode(' | ', $reqList);

            $grpLabel = trim($d['group_label'] ?? '');
            $gen = trim($gen);
            $slv = trim($slv);
            $pck = trim($pck);
            $btn = trim($btn);
            $tun = trim($tun);
            $sbStr = trim($sbStr);
            $reqStr = trim($reqStr);

            $groupKey = "{$grpLabel}|{$gen}|{$slv}|{$pck}|{$btn}|{$tun}|{$clr}|{$sbStr}|{$reqStr}";
            
            if (!isset($specGroups[$groupKey])) {
                $specGroups[$groupKey] = [
                    'group_label' => $grpLabel,
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
                    'spec_notes' => [],
                    'total_qty' => 0,
                    'sizes' => [],
                    'recipients' => ['standard' => [], 'custom' => []],
                ];
            }
            
            if (!empty($d['spec_notes'])) {
                if (!in_array($d['spec_notes'], $specGroups[$groupKey]['spec_notes'])) {
                    $specGroups[$groupKey]['spec_notes'][] = $d['spec_notes'];
                }
            }

            $specGroups[$groupKey]['total_qty'] += $gi->quantity;
            $sz = strtoupper($gi->size ?? 'TANPA_UKURAN');
            $specGroups[$groupKey]['sizes'][$sz] = ($specGroups[$groupKey]['sizes'][$sz] ?? 0) + $gi->quantity;
            $sizes[$sz] = ($sizes[$sz] ?? 0) + $gi->quantity;

            if (!empty($d['detail_custom'])) {
                foreach ($d['detail_custom'] as $u) {
                    $m = [];
                    foreach (['LD', 'PB', 'PL', 'LB', 'LP', 'LPh'] as $mk) {
                        if (!empty($u[$mk])) $m[] = "$mk:{$u[$mk]}";
                    }
                    $specGroups[$groupKey]['recipients']['custom'][] = [
                        'nama' => $u['nama'] ?? '-',
                        'size' => $u['ukuran'] ?? 'Custom',
                        'desc' => implode(' ', $m)
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

        $groupItemIds = collect($allGroupItems)->pluck('id')->toArray();
        $activeReturn = \App\Models\OrderReturn::whereIn('order_item_id', $groupItemIds)
            ->where('status', '!=', 'selesai')
            ->latest('id')
            ->first();

        if ($activeReturn) {
            $totalQuantity = $activeReturn->quantity;
            if (!empty($activeReturn->size_breakdown) && is_array($activeReturn->size_breakdown)) {
                $sizes = [];
                foreach ($activeReturn->size_breakdown as $szKey => $szQty) {
                    if ($szKey === '_raw' || is_array($szQty)) continue;
                    $cleanSz = strtoupper(str_replace(['SIZE ', 'SIZE'], '', $szKey));
                    $sizes[$cleanSz] = (int) $szQty;
                }
            }
        }

        return [
            'record' => $record->load(['order.customer', 'productionTasks.assignedTo', 'bahan.material']),
            'totalQuantity' => $totalQuantity,
            'sizes' => $sizes,
            'specGroups' => $specGroups,
            'allGroupItems' => $allGroupItems,
            'activeReturn' => $activeReturn,
        ];
    }
}

