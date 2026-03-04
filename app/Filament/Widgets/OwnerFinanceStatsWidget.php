<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductionTask;
use Filament\Widgets\Widget;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;

class OwnerFinanceStatsWidget extends Widget
{
    protected string $view = 'filament.widgets.owner-finance-stats-widget';
    protected int|string|array $columnSpan = 'full';
    protected static ?int $sort = 1;

    // ── Hanya tampil untuk Owner ──────────────────────────────────────────────
    public static function canView(): bool
    {
        return auth()->check() && auth()->user()->role === 'owner';
    }

    public function getData(): array
    {
        $tenant = Filament::getTenant();
        $tenantId = $tenant->id;
        $maxCapacity = $tenant->max_capacity_pcs ?? 2000;

        // ══════════════════════════════════════════════════════════════════════
        // CARD 1: REVENUE & PROFITABILITY
        // ══════════════════════════════════════════════════════════════════════
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();
        $startOfLastMonth = Carbon::now()->subMonth()->startOfMonth();
        $endOfLastMonth = Carbon::now()->subMonth()->endOfMonth();

        $totalOmzet = Order::where('shop_id', $tenantId)
            ->whereBetween('order_date', [$startOfMonth, $endOfMonth])
            ->sum('total_price');

        $prevOmzet = Order::where('shop_id', $tenantId)
            ->whereBetween('order_date', [$startOfLastMonth, $endOfLastMonth])
            ->sum('total_price');

        // Trend omzet: positif = naik = hijau
        $omzetTrendPct = $prevOmzet > 0
            ? round((($totalOmzet - $prevOmzet) / $prevOmzet) * 100, 1)
            : ($totalOmzet > 0 ? 100 : 0);
        $omzetTrendUp = $omzetTrendPct >= 0;
        $omzetTrendLabel = ($omzetTrendUp ? '+' : '') . $omzetTrendPct . '% vs bln lalu';

        // ══════════════════════════════════════════════════════════════════════
        // CARD 2: PIUTANG MACET
        // ══════════════════════════════════════════════════════════════════════
        $macetOrders = Order::where('shop_id', $tenantId)
            ->where('deadline', '<', Carbon::today())
            ->with('payments')
            ->get()
            ->filter(fn($o) => $o->remaining_balance > 0);

        $totalPiutangMacet = $macetOrders->sum(fn($o) => $o->remaining_balance);
        $countMacetCustomers = $macetOrders->pluck('customer_id')->unique()->count();

        // Piutang macet bulan lalu (lewat deadline bulan lalu, belum lunas)
        $macetLastMonth = Order::where('shop_id', $tenantId)
            ->where('deadline', '<', Carbon::today()->subMonth())
            ->with('payments')
            ->get()
            ->filter(fn($o) => $o->remaining_balance > 0)
            ->sum(fn($o) => $o->remaining_balance);

        // INVERSI: piutang naik = MERAH (kabar buruk)
        $piutangTrendPct = $macetLastMonth > 0
            ? round((($totalPiutangMacet - $macetLastMonth) / $macetLastMonth) * 100, 1)
            : ($totalPiutangMacet > 0 ? 100 : 0);
        $piutangDanger = $piutangTrendPct > 0; // naik = bahaya
        $piutangTrendLabel = ($piutangTrendPct >= 0 ? '+' : '') . $piutangTrendPct . '% vs bln lalu';

        // ══════════════════════════════════════════════════════════════════════
        // CARD 3: BEBAN WORKSHOP
        // ══════════════════════════════════════════════════════════════════════
        $activeOrders = Order::where('shop_id', $tenantId)
            ->where('status', 'dikerjakan')
            ->with('orderItems')
            ->get();

        $totalPcsAktif = $activeOrders->sum(fn($o) => $o->orderItems->sum('quantity'));
        $activeOrderCount = $activeOrders->count();
        $occupancyPct = $maxCapacity > 0
            ? min(100, round(($totalPcsAktif / $maxCapacity) * 100, 1))
            : 0;

        return [
            'omzet' => [
                'total' => $totalOmzet,
                'trend_pct' => $omzetTrendPct,
                'trend_up' => $omzetTrendUp,
                'trend_label' => $omzetTrendLabel,
            ],
            'piutang_macet' => [
                'total' => $totalPiutangMacet,
                'count_customers' => $countMacetCustomers,
                'trend_pct' => $piutangTrendPct,
                'danger' => $piutangDanger,
                'trend_label' => $piutangTrendLabel,
            ],
            'workshop' => [
                'active_orders' => $activeOrderCount,
                'total_pcs' => $totalPcsAktif,
                'max_capacity' => $maxCapacity,
                'occupancy_pct' => $occupancyPct,
            ],
        ];
    }
}
