<?php

namespace App\Filament\Resources\ControlProduksis\Widgets;

use App\Models\OrderItem;
use App\Models\ProductionStage;
use App\Models\ProductionTask;
use Filament\Widgets\Widget;

class ProduksiStats extends Widget
{
    protected string $view = 'filament.resources.control-produksi.widgets.produksi-stats';

    protected int | string | array $columnSpan = 'full';

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

        // Setiap stage: hitung qty pending/in_progress
        $allStages = ProductionStage::orderBy('order_sequence')->get();

        $stages = [
            [
                'name' => 'Antrian',
                'qty'  => (int) $antrian,
            ],
        ];

        foreach ($allStages as $stage) {
            $qty = ProductionTask::where('shop_id', $shopId)
                ->where('stage_name', $stage->name)
                ->whereIn('status', ['pending', 'in_progress'])
                ->sum('quantity');

            $stages[] = [
                'name' => $stage->name,
                'qty'  => (int) $qty,
            ];
        }

        return [
            'totalBeban' => (int) $totalBeban,
            'stages'     => $stages,
            'trend'      => $trend,
        ];
    }
}
