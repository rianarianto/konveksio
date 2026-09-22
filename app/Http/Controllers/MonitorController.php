<?php

namespace App\Http\Controllers;

use App\Models\OrderItem;
use App\Models\Shop;
use Illuminate\Http\Request;

class MonitorController extends Controller
{
    public function produksi(Shop $shop)
    {
        // Query base untuk order item yang valid di shop ini
        $baseQuery = OrderItem::with(['order.customer', 'productionTasks.assignedTo'])
            ->whereHas('order', function ($q) use ($shop) {
                $q->where('shop_id', $shop->id)
                  ->whereNotIn('status', ['batal', 'dibatalkan', 'selesai', 'diambil']);
            })
            ->whereHas('productionTasks'); // Sudah diatur tugas produksinya

        $allItems = $baseQuery->get();

        $inProgressList = collect();
        $antrianList = collect();

        foreach ($allItems as $item) {
            $groupItemIds = $item->getItemsInGroup()->pluck('id');
            $wo = \App\Models\WorkOrder::withoutGlobalScopes()
                ->whereIn('order_item_id', $groupItemIds)
                ->where('wo_number', 'not like', '%-R%')
                ->first();

            $tasks = $item->productionTasks;
            $allTasksDone = $tasks->isNotEmpty() && $tasks->every(fn($t) => $t->status === 'done');
            $anyTaskStarted = $tasks->contains(fn($t) => in_array($t->status, ['in_progress', 'done']));

            // Cek status WO
            $isWoCompleted = $wo && $wo->status === \App\Models\WorkOrder::STATUS_COMPLETED;
            $isWoInQc = $wo && in_array($wo->status, [
                \App\Models\WorkOrder::STATUS_QC_PREP,
                \App\Models\WorkOrder::STATUS_QC_REVIEW,
                \App\Models\WorkOrder::STATUS_QC_AKHIR,
                'QC_PERSIAPAN',
            ]);

            // Jika WO sudah selesai sepenuhnya (COMPLETED) atau order selesai, tidak perlu tampil di monitor
            if ($isWoCompleted || ($allTasksDone && !$isWoInQc && (!$wo || !$wo->has_qc_selesai))) {
                continue;
            }

            // Jika sedang diproses (ada task jalan/selesai ATAU WO sedang di tahap QC)
            if ($anyTaskStarted || $isWoInQc || ($wo && $wo->status !== \App\Models\WorkOrder::STATUS_CREATED)) {
                $inProgressList->push($item);
            } else {
                $antrianList->push($item);
            }
        }

        $sortFunc = function ($item) {
            return ($item->order->is_express ? '0' : '1') . '_' . ($item->order->deadline?->format('Y-m-d') ?? '9999-12-31');
        };

        $inProgress = $inProgressList->sortBy($sortFunc);
        $antrian = $antrianList->sortBy($sortFunc)->take(15);

        return view('monitor.produksi', compact('shop', 'inProgress', 'antrian'));
    }
}
