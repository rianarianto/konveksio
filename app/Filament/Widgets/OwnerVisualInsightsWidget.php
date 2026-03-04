<?php

namespace App\Filament\Widgets;

use App\Models\Expense;
use App\Models\Order;
use App\Models\ProductionTask;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;

class OwnerVisualInsightsWidget extends Widget
{
    protected string $view = 'filament.widgets.owner-visual-insights-widget';
    protected int|string|array $columnSpan = 'full';
    protected static ?int $sort = 2;

    // Livewire state: offset bulan untuk heatmap (0 = bulan ini, 1 = depan, -1 = lalu)
    public int $heatmapMonthOffset = 0;

    public static function canView(): bool
    {
        return auth()->check() && auth()->user()->role === 'owner';
    }

    public function prevMonth(): void
    {
        $this->heatmapMonthOffset--;
    }

    public function nextMonth(): void
    {
        $this->heatmapMonthOffset++;
    }

    public function resetMonth(): void
    {
        $this->heatmapMonthOffset = 0;
    }

    public function getData(): array
    {
        $tenantId = Filament::getTenant()?->id;

        return [
            'revenueCost' => $this->getRevenueCostTrend($tenantId),
            'productionStatus' => $this->getOrderStatusOverview($tenantId),
            'deadlineHeatmap' => $this->getDeadlineHeatmap($tenantId),
        ];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 1. Revenue vs Cost — 6-month trend
    // ══════════════════════════════════════════════════════════════════════════
    private function getRevenueCostTrend(int $tenantId): array
    {
        $months = [];

        for ($i = 5; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $start = $date->copy()->startOfMonth();
            $end = $date->copy()->endOfMonth();

            // Revenue: total_price of orders in this month
            $omzet = (int) Order::where('shop_id', $tenantId)
                ->whereBetween('order_date', [$start, $end])
                ->sum('total_price');

            // Cost: expenses + wages paid
            $expenses = (int) Expense::where('shop_id', $tenantId)
                ->whereBetween('expense_date', [$start, $end])
                ->sum('amount');

            $wages = (int) (ProductionTask::where('shop_id', $tenantId)
                ->where('status', 'done')
                ->whereBetween('completed_at', [$start, $end])
                ->selectRaw('SUM(wage_amount * quantity) as total')
                ->value('total') ?? 0);

            $months[] = [
                'label' => $date->translatedFormat('M'),
                'omzet' => $omzet,
                'biaya' => $expenses + $wages,
            ];
        }

        return $months;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 2. Production Status Breakdown — donut chart data
    // ══════════════════════════════════════════════════════════════════════════
    private function getOrderStatusOverview(int $tenantId): array
    {
        $target = Carbon::now()->addMonths($this->heatmapMonthOffset);

        // Fetch counts for each order status
        $statusCounts = Order::where('shop_id', $tenantId)
            ->whereMonth('order_date', $target->month)
            ->whereYear('order_date', $target->year)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // 1. Define high-fidelity colors and labels for general overview
        $config = [
            'batal'        => ['label' => 'Batal',        'color' => '#f87171', 'priority' => 1], // Red
            'diterima'     => ['label' => 'Diterima',     'color' => '#a855f7', 'priority' => 2], // Purple
            'antrian'      => ['label' => 'Antrian',      'color' => '#818cf8', 'priority' => 3], // Indigo
            'diproses'     => ['label' => 'Diproses',     'color' => '#3b82f6', 'priority' => 4], // Blue
            'siap_diambil' => ['label' => 'Siap Diambil', 'color' => '#22c55e', 'priority' => 5], // Green
            'selesai'      => ['label' => 'Selesai',      'color' => '#2dd4bf', 'priority' => 6], // Teal
        ];

        $segments = [];

        // 2. Map counts to standardized segments
        foreach ($config as $statusKey => $meta) {
            $count = $statusCounts[$statusKey] ?? 0;
            if ($count > 0) {
                $segments[] = [
                    'stage'    => $meta['label'],
                    'pcs'      => (int) $count,
                    'priority' => $meta['priority'],
                    'color'    => $meta['color'],
                ];
            }
        }

        // 3. Handle any unexpected statuses from DB
        foreach ($statusCounts as $status => $count) {
            if (!isset($config[$status])) {
                $segments[] = [
                    'stage'    => ucfirst(str_replace('_', ' ', $status)),
                    'pcs'      => (int) $count,
                    'priority' => 10,
                    'color'    => '#94a3b8',
                ];
            }
        }

        // 4. Sort by priority for clockwise starting from top
        usort($segments, fn($a, $b) => $a['priority'] <=> $b['priority']);

        $totalOrders = array_sum(array_column($segments, 'pcs'));

        return [
            'segments' => $segments,
            'totalPcs' => $totalOrders,
        ];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 3. Deadline Heatmap — navigable month calendar
    // ══════════════════════════════════════════════════════════════════════════
    private function getDeadlineHeatmap(int $tenantId): array
    {
        $target = Carbon::now()->addMonths($this->heatmapMonthOffset);
        $today = Carbon::today();

        $deadlines = Order::where('shop_id', $tenantId)
            ->whereMonth('deadline', $target->month)
            ->whereYear('deadline', $target->year)
            ->whereNotIn('status', ['batal', 'diambil'])
            ->selectRaw('DAY(deadline) as day, COUNT(*) as count')
            ->groupBy('day')
            ->pluck('count', 'day')
            ->toArray();

        // "today" highlight only when viewing the current month
        $isCurrentMonth = $target->month === $today->month && $target->year === $today->year;

        return [
            'year'           => $target->year,
            'month'          => $target->month,
            'monthLabel'     => $target->translatedFormat('F Y'),
            'daysInMonth'    => $target->daysInMonth,
            'firstDayOfWeek' => $target->copy()->startOfMonth()->dayOfWeekIso,
            'today'          => $isCurrentMonth ? $today->day : null,
            'deadlines'      => $deadlines,
            'isCurrentMonth' => $isCurrentMonth,
        ];
    }
}
