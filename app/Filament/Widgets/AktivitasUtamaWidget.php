<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;
use Filament\Facades\Filament;

class AktivitasUtamaWidget extends Widget
{
    protected string $view = 'filament.widgets.aktivitas-utama-widget';
    protected int|string|array $columnSpan = 'full';
    protected static ?int $sort = 2;

    // Sembunyikan dari Owner — mereka dapat widget Finance Stats
    public static function canView(): bool
    {
        return auth()->check() && auth()->user()->role !== 'owner';
    }

    public function getData(): array
    {
        $tenantId = Filament::getTenant()?->id;

        // 1. Pesanan Masuk Hari Ini
        $countOrderToday = Order::whereDate('created_at', Carbon::today())->count();
        $countOrderYesterday = Order::whereDate('created_at', Carbon::yesterday())->count();
        $orderTrend = $this->calculateTrend($countOrderToday, $countOrderYesterday);

        // 2. Pesanan Diproses
        $countDiproses = Order::where('status', 'diproses')->count();
        $countDiprosesYesterday = Order::where('status', 'diproses')
            ->whereDate('updated_at', Carbon::yesterday())->count();
        $diprosesTrend = $this->calculateTrend($countDiproses, $countDiprosesYesterday);

        // 3. Pesanan Siap Diambil
        $countSiapAmbil = Order::where('status', 'selesai')->count();
        $countSiapAmbilYesterday = Order::where('status', 'selesai')
            ->whereDate('updated_at', Carbon::yesterday())->count();
        $siapAmbilTrend = $this->calculateTrend($countSiapAmbil, $countSiapAmbilYesterday);

        // 4. Deadline Hari Ini
        $countDeadlineToday = Order::whereDate('deadline', Carbon::today())->count();

        return [
            'orderToday' => [
                'count' => $countOrderToday,
                'trend' => $orderTrend,
                'label' => 'vs Kemarin',
            ],
            'diproses' => [
                'count' => $countDiproses,
                'trend' => $diprosesTrend,
                'label' => 'vs Kemarin',
            ],
            'siapAmbil' => [
                'count' => $countSiapAmbil,
                'trend' => $siapAmbilTrend,
                'label' => 'vs Kemarin',
            ],
            'deadlineToday' => [
                'count' => $countDeadlineToday,
                'label' => 'Today',
            ],
        ];
    }

    protected function calculateTrend($current, $previous): string
    {
        if ($previous == 0) {
            return $current > 0 ? '+100%' : '0%';
        }

        $diff = (($current - $previous) / $previous) * 100;
        $prefix = $diff >= 0 ? '+' : '';

        return $prefix . number_format($diff, 1) . '%';
    }
}
