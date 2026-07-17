<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Actions\Action;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('deliver_order')
                ->label('Serahkan Pesanan')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->form([
                    \Filament\Forms\Components\FileUpload::make('pickup_proof')
                        ->label('Foto Bukti Pengambilan / Penyerahan')
                        ->image()
                        ->extraInputAttributes(['capture' => 'camera'])
                        ->disk('public')
                        ->directory('pickup-proofs')
                        ->required(),
                    \Filament\Forms\Components\Textarea::make('pickup_note')
                        ->label('Catatan Penyerahan')
                        ->rows(3)
                        ->placeholder('Masukkan nama pengambil, kurir, atau info lainnya jika ada...'),
                ])
                ->action(function (array $data): void {
                    $this->record->update([
                        'status' => 'selesai',
                        'pickup_proof' => $data['pickup_proof'],
                        'pickup_note' => $data['pickup_note'] ?? null,
                        'pickup_at' => now(),
                    ]);
                    \Filament\Notifications\Notification::make()
                        ->title('Pesanan Telah Diserahkan!')
                        ->body("Pesanan #{$this->record->order_number} telah selesai dan diserahkan.")
                        ->success()
                        ->send();
                    $this->redirect(OrderResource::getUrl('view', ['record' => $this->record]));
                })
                ->visible(fn(): bool => $this->record->status === 'siap_diambil'),
            Action::make('print_receipt')
                ->label('Cetak Kuitansi')
                ->icon('heroicon-o-printer')
                ->color('primary')
                ->url(fn() => route('orders.receipt', $this->record))
                ->openUrlInNewTab(),
            Action::make('create_return')
                ->label('Catat Retur')
                ->icon('heroicon-o-arrow-uturn-left')
                ->form(\App\Filament\Resources\OrderReturns\Schemas\OrderReturnForm::getComponents(true))
                ->action(function (array $data): void {
                    $this->record->returns()->create($data);
                    \Filament\Notifications\Notification::make()
                        ->title('Retur Berhasil Dicatat')
                        ->success()
                        ->send();
                })
                ->modalHeading('Catat Retur Pesanan')
                ->modalSubmitActionLabel('Simpan'),
        ];
    }
}
