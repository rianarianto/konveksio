<?php

namespace App\Filament\Resources\Kasbons\Pages;

use App\Filament\Resources\Kasbons\KasbonResource;
use Filament\Resources\Pages\ManageRecords;

class ManageKasbons extends ManageRecords
{
    protected static string $resource = KasbonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\ActionGroup::make([
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
                        $shopId = \Filament\Facades\Filament::getTenant()?->id ?? auth()->user()->shop_id;
                        $url = route('reports.export-pdf-kasbon', [
                            'shop_id' => $shopId,
                            'from' => $data['from'] ?? null,
                            'until' => $data['until'] ?? null,
                        ]);
                        return redirect()->away($url);
                    }),

                \Filament\Actions\Action::make('export_csv')
                    ->label('Export Excel / CSV')
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
                        $shopId = \Filament\Facades\Filament::getTenant()?->id ?? auth()->user()->shop_id;
                        $url = route('reports.export-kasbon', [
                            'shop_id' => $shopId,
                            'from' => $data['from'] ?? null,
                            'until' => $data['until'] ?? null,
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
