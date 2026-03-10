<?php

namespace App\Filament\Resources\PengeluaranResource\Pages;

use App\Filament\Resources\PengeluaranResource;
use Filament\Resources\Pages\ListRecords;
use App\Models\Expense;
use App\Models\ProductionTask;
use App\Models\Worker;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;

class ListPengeluaran extends ListRecords
{
    protected static string $resource = PengeluaranResource::class;

    protected string $view = 'filament.resources.pengeluaran.pages.list-pengeluaran';

    public string $periodo = 'bulan_ini';

    public function getViewData(): array
    {
        $tenantId = Filament::getTenant()?->id;
        $now = Carbon::now();

        // Determine date range
        [$dari, $sampai] = match ($this->periodo) {
            'hari_ini' => [Carbon::today(), Carbon::today()],
            'minggu_ini' => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()],
            'bulan_ini' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            'bulan_lalu' => [$now->copy()->subMonth()->startOfMonth(), $now->copy()->subMonth()->endOfMonth()],
            default => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
        };

        // ── 1. Pengeluaran Operasional (dari tabel expenses) ──
        $expenses = Expense::withoutGlobalScopes()
            ->where('shop_id', $tenantId)
            ->whereBetween('expense_date', [$dari, $sampai])
            ->get();

        $totalOperasional = $expenses->where('note', '!=', 'Kasbon Karyawan')
            ->where('note', '!=', 'Gaji/Upah')->sum('amount');
        $totalKasbonExpense = $expenses->where('note', 'Kasbon Karyawan')->sum('amount');
        $totalGajiExpense = $expenses->where('note', 'Gaji/Upah')->sum('amount');
        $totalAllExpenses = $expenses->sum('amount');

        // ── 2. Upah Tukang Borongan (dari production_tasks yang done) ──
        $totalUpahBorongan = ProductionTask::withoutGlobalScopes()
            ->where('shop_id', $tenantId)
            ->where('status', 'done')
            ->whereBetween('completed_at', [$dari, $sampai])
            ->selectRaw('SUM(wage_amount * quantity) as total')
            ->value('total') ?? 0;

        // ── 3. Total Kasbon Belum Lunas ──
        $totalKasbonWorkers = Worker::withoutGlobalScopes()->where('shop_id', $tenantId)->sum('current_cash_advance');
        $totalKasbonUsers = User::withoutGlobalScopes()->where('shop_id', $tenantId)->sum('current_cash_advance');
        $totalKasbonBelumLunas = $totalKasbonWorkers + $totalKasbonUsers;

        // ── 4. Breakdown per Kategori ──
        $breakdown = $expenses->groupBy(fn($e) => $e->note ?: 'Lainnya')
            ->map(fn($group) => $group->sum('amount'))
            ->sortDesc();

        // ── 5. Grand Total ──
        $grandTotal = $totalAllExpenses + $totalUpahBorongan;

        return [
            'totalOperasional' => $totalOperasional,
            'totalKasbonExpense' => $totalKasbonExpense,
            'totalGajiExpense' => $totalGajiExpense,
            'totalUpahBorongan' => $totalUpahBorongan,
            'totalKasbonBelumLunas' => $totalKasbonBelumLunas,
            'grandTotal' => $grandTotal,
            'breakdown' => $breakdown,
            'periodoLabel' => match ($this->periodo) {
                'hari_ini' => 'Hari Ini (' . Carbon::today()->translatedFormat('d M Y') . ')',
                'minggu_ini' => 'Minggu Ini',
                'bulan_ini' => Carbon::now()->translatedFormat('F Y'),
                'bulan_lalu' => Carbon::now()->subMonth()->translatedFormat('F Y'),
                default => Carbon::now()->translatedFormat('F Y'),
            },
        ];
    }
}
