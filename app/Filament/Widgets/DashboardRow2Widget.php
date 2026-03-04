<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use App\Models\Payment;
use App\Models\ProductionTask;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;
use Filament\Facades\Filament;

class DashboardRow2Widget extends Widget
{
    protected string $view = 'filament.widgets.dashboard-row2-widget';
    protected int|string|array $columnSpan = 'full';
    protected static ?int $sort = 3;

    // Sembunyikan dari Owner — mereka punya widget sendiri
    public static function canView(): bool
    {
        return auth()->check() && auth()->user()->role !== 'owner';
    }

    public function getData(): array
    {
        $tenantId = Filament::getTenant()?->id;

        // ── Cashflow ──
        $orders = Order::where('shop_id', $tenantId)->get();
        $totalPiutang = $orders->sum('remaining_balance');

        $pemasukanHariIni = Payment::whereHas('order', fn($q) => $q->where('shop_id', $tenantId))
            ->whereDate('payment_date', Carbon::today())
            ->sum('amount');

        // ── Daftar Aktivitas Terbaru ──
        // Latest updated production tasks with their order/customer info
        $tasks = ProductionTask::with([
            'orderItem.order.customer',
        ])
            ->whereHas('orderItem.order', fn($q) => $q->where('shop_id', $tenantId))
            ->latest('updated_at')
            ->take(4)
            ->get();

        $activities = $tasks->map(function ($task) {
            $order = $task->orderItem?->order;
            $customer = $order?->customer;

            return [
                'invoice' => $order?->order_number ?? '-',
                'customer_name' => $customer?->name ?? '-',
                'stage' => $task->stage_name ?? '-',
                'status' => $task->status ?? 'antrian',
            ];
        });

        return [
            'totalPiutang' => $totalPiutang,
            'pemasukanHariIni' => $pemasukanHariIni,
            'activities' => $activities,
        ];
    }
}
