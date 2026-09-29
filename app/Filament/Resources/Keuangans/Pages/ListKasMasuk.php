<?php

namespace App\Filament\Resources\Keuangans\Pages;

use App\Filament\Resources\Keuangans\KasMasukResource;
use Filament\Resources\Pages\Page;
use App\Filament\Widgets\KeuanganStatsWidget;

class ListKasMasuk extends Page
{
    protected static string $resource = KasMasukResource::class;

    protected string $view = 'filament.resources.keuangans.pages.list-kas-masuk';

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('export_csv')
                ->label('Export Excel/CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->form([
                    \Filament\Forms\Components\DatePicker::make('from')
                        ->label('Dari Tanggal')
                        ->default(now()->startOfMonth()),
                    \Filament\Forms\Components\DatePicker::make('until')
                        ->label('Sampai Tanggal')
                        ->default(now()),
                ])
                ->action(function (array $data) {
                    $url = route('reports.export-pemasukan', [
                        'from' => $data['from'] ?? null,
                        'until' => $data['until'] ?? null,
                    ]);
                    return redirect()->away($url);
                }),

            \Filament\Actions\Action::make('export_pdf')
                ->label('Cetak Laporan PDF')
                ->icon('heroicon-o-printer')
                ->color('primary')
                ->form([
                    \Filament\Forms\Components\DatePicker::make('from')
                        ->label('Dari Tanggal')
                        ->default(now()->startOfMonth()),
                    \Filament\Forms\Components\DatePicker::make('until')
                        ->label('Sampai Tanggal')
                        ->default(now()),
                ])
                ->action(function (array $data) {
                    $url = route('reports.export-pdf', [
                        'from' => $data['from'] ?? null,
                        'until' => $data['until'] ?? null,
                    ]);
                    return redirect()->away($url);
                }),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            KeuanganStatsWidget::class,
        ];
    }
}
