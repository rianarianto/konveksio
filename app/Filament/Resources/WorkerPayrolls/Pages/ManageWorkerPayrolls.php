<?php

namespace App\Filament\Resources\WorkerPayrolls\Pages;

use App\Filament\Resources\WorkerPayrolls\WorkerPayrollResource;
use Filament\Resources\Pages\ManageRecords;

class ManageWorkerPayrolls extends ManageRecords
{
    protected static string $resource = WorkerPayrollResource::class;

    public function getTabs(): array
    {
        return [
            'borongan' => \Filament\Schemas\Components\Tabs\Tab::make('Upah Borongan')
                ->modifyQueryUsing(fn ($query) => $query
                    ->where('wage_type', 'piece_rate')
                    ->whereHas('productionTasks', fn($q) => $q->where('status', 'done')->where('is_paid', false))
                ),
            'bulanan' => \Filament\Schemas\Components\Tabs\Tab::make('Gaji Bulanan')
                ->modifyQueryUsing(fn ($query) => $query
                    ->where('wage_type', 'monthly')
                ),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\ActionGroup::make([
                \Filament\Actions\Action::make('export_pdf')
                    ->label('Cetak Laporan PDF')
                    ->icon('heroicon-o-printer')
                    ->color('primary')
                    ->action(function () {
                        $shopId = \Filament\Facades\Filament::getTenant()?->id ?? auth()->user()->shop_id;
                        $filters = $this->tableFilters['completed_at'] ?? [];
                        $from = $filters['completed_from'] ?? null;
                        $until = $filters['completed_until'] ?? null;
                        $tab = $this->activeTab ?? 'borongan';

                        $url = route('reports.export-pdf-payrolls', [
                            'shop_id' => $shopId,
                            'tab' => $tab,
                            'from' => $from,
                            'until' => $until,
                        ]);

                        return redirect()->away($url);
                    }),

                \Filament\Actions\Action::make('export_csv')
                    ->label('Export Excel / CSV')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->action(function () {
                        $shopId = \Filament\Facades\Filament::getTenant()?->id ?? auth()->user()->shop_id;
                        $filters = $this->tableFilters['completed_at'] ?? [];
                        $from = $filters['completed_from'] ?? null;
                        $until = $filters['completed_until'] ?? null;
                        $tab = $this->activeTab ?? 'borongan';

                        $url = route('reports.export-payrolls', [
                            'shop_id' => $shopId,
                            'tab' => $tab,
                            'from' => $from,
                            'until' => $until,
                        ]);

                        return redirect()->away($url);
                    }),
            ])
            ->label('Unduh Laporan')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('gray')
            ->button(),
        ];
    }
}
