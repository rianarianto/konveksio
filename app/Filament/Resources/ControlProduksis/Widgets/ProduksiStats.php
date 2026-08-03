<?php

namespace App\Filament\Resources\ControlProduksis\Widgets;

use App\Models\OrderItem;
use App\Models\ProductionStage;
use App\Models\ProductionTask;
use Filament\Widgets\Widget;

class ProduksiStats extends Widget
{
    protected string $view = 'filament.resources.control-produksi.widgets.produksi-stats';

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = '30s';

    public function getViewData(): array
    {
        $tenant = \Filament\Facades\Filament::getTenant();
        if (!$tenant) {
            return ['totalBeban' => 0, 'stages' => [], 'trend' => null];
        }

        $shopId = $tenant->id;

        // Total beban: semua qty yang masih aktif di production tasks
        $totalBeban = ProductionTask::where('shop_id', $shopId)
            ->whereIn('status', ['pending', 'in_progress'])
            ->sum('quantity');

        // Kemarin
        $totalBebanKemarin = ProductionTask::where('shop_id', $shopId)
            ->whereIn('status', ['pending', 'in_progress'])
            ->whereDate('created_at', now()->subDay())
            ->sum('quantity');

        $trend = null;
        if ($totalBebanKemarin > 0) {
            $diff = (($totalBeban - $totalBebanKemarin) / $totalBebanKemarin) * 100;
            $trend = ($diff >= 0 ? '+' : '') . number_format($diff, 1) . '% vs kemarin';
        }

        // Total items belum ada tugas (antrian)
        $antrian = OrderItem::whereHas('order', fn($q) => $q->where('shop_id', $shopId))
            ->where('design_status', 'approved')
            ->doesntHave('productionTasks')
            ->sum('quantity');

        $stages = [
            [
                'name' => 'Antrian',
                'qty' => (int) $antrian,
            ],
        ];

        $categoriesMap = [
            'Potong'       => ['Potong'],
            'Jahit'        => ['Jahit'],
            'Bordir/Sablon'=> ['Bordir', 'Sablon', 'Bordir/Sablon'],
            'Kancing'      => ['Kancing'],
            'Finishing'    => ['Finishing'],
        ];

        foreach ($categoriesMap as $categoryLabel => $stageNames) {
            // Hitung task yang aktif dikerjakan (in_progress) ATAU WorkOrder yang statusnya sedang di tahap ini
            $qty = ProductionTask::where('shop_id', $shopId)
                ->whereIn('stage_name', $stageNames)
                ->where('status', '!=', 'done')
                ->where(function ($q) use ($stageNames) {
                    $q->where('status', 'in_progress')
                      ->orWhereHas('orderItem.workOrder', function ($woQuery) use ($stageNames) {
                          $woQuery->whereIn('status', $stageNames);
                      });
                })
                ->sum('quantity');

            $stages[] = [
                'name' => $categoryLabel,
                'qty'  => (int) $qty,
            ];
        }

        // Hitung beban aktif yang sedang berada di tahap QC (QC Persiapan, QC Review, atau QC Akhir)
        $qcQty = \App\Models\WorkOrder::where('shop_id', $shopId)
            ->whereIn('status', [
                \App\Models\WorkOrder::STATUS_QC_PREP,
                \App\Models\WorkOrder::STATUS_QC_REVIEW,
                \App\Models\WorkOrder::STATUS_QC_AKHIR,
                'QC_PERSIAPAN',
            ])
            ->get()
            ->sum(function ($wo) {
                return $wo->orderItem?->quantity ?? 0;
            });

        $stages[] = [
            'name' => 'QC',
            'qty'  => (int) $qcQty,
        ];

        return [
            'totalBeban' => (int) $totalBeban,
            'stages'     => $stages,
            'trend'      => $trend,
        ];
    }
}
