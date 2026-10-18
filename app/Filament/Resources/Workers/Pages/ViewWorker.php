<?php

namespace App\Filament\Resources\Workers\Pages;

use App\Filament\Resources\Workers\WorkerResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewWorker extends ViewRecord
{
    protected static string $resource = WorkerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\ActionGroup::make([
                \Filament\Actions\Action::make('export_pdf')
                    ->label('Cetak Rincian PDF')
                    ->icon('heroicon-o-printer')
                    ->color('primary')
                    ->action(function () {
                        $url = route('reports.export-worker-detail', [
                            'worker_id' => $this->record->id,
                            'type' => 'pdf',
                        ]);
                        return redirect()->away($url);
                    }),

                \Filament\Actions\Action::make('export_csv')
                    ->label('Export Rincian Excel / CSV')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->action(function () {
                        $url = route('reports.export-worker-detail', [
                            'worker_id' => $this->record->id,
                            'type' => 'csv',
                        ]);
                        return redirect()->away($url);
                    }),
            ])
            ->label('Unduh Rincian')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('gray')
            ->button(),

            EditAction::make(),
        ];
    }
}
