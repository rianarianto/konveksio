<?php

namespace App\Filament\Widgets;

use App\Models\Expense;
use App\Models\Order;
use App\Models\Payment;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Filament\Facades\Filament;
use Livewire\Attributes\On;

class KeuanganStatsWidget extends BaseWidget
{
    protected static ?int $sort = 2;
    #[On('refreshStats')]
    public function refresh()
    {
        // Just trigger re-render
    }

    protected function getStats(): array
    {
        $tenantId = Filament::getTenant()?->id;

        // 1. Total Piutang (Sisa tagihan yang > 0)
        // Hitung manual dari collection karena accessor tidak bisa dijumlah mudah via DB.
        // Tapi jika banyak, ini kurang optimal. Kita bisa hitung via Model accessor di foreach.
        $orders = Order::where('shop_id', $tenantId)->get();
        $totalPiutang = $orders->sum('remaining_balance');

        // 2. Uang Masuk Hari Ini
        $uangMasukHariIni = Payment::whereHas('order', fn($q) => $q->where('shop_id', $tenantId))
            ->whereDate('payment_date', Carbon::today())
            ->sum('amount');

        // 3. Saldo Kas Kecil Bulan Ini
        $paymentsBulanIni = Payment::whereHas('order', fn($q) => $q->where('shop_id', $tenantId))
            ->whereMonth('payment_date', Carbon::now()->month)
            ->whereYear('payment_date', Carbon::now()->year)
            ->sum('amount');

        $expensesBulanIni = Expense::where('shop_id', $tenantId)
            ->whereMonth('expense_date', Carbon::now()->month)
            ->whereYear('expense_date', Carbon::now()->year)
            ->sum('amount');

        $saldoKasKecil = $paymentsBulanIni - $expensesBulanIni;

        return [
            Stat::make('Total Piutang', 'Rp ' . number_format($totalPiutang, 0, ',', '.'))
                ->description('Siap ditagih ke Pelanggan')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('warning'),

            Stat::make('Uang Masuk (Hari Ini)', 'Rp ' . number_format($uangMasukHariIni, 0, ',', '.'))
                ->description(Carbon::today()->translatedFormat('d F Y'))
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),

            Stat::make('Saldo Kas Kecil', 'Rp ' . number_format($saldoKasKecil, 0, ',', '.'))
                ->description('Pemasukan - Pengeluaran (Bulan: ' . Carbon::now()->translatedFormat('F') . ')')
                ->descriptionIcon('heroicon-m-wallet')
                ->color('primary'),
        ];
    }
}
