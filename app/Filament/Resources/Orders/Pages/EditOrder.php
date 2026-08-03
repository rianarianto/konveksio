<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Payment;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\EditRecord;
use Filament\Actions\Action;
use Barryvdh\DomPDF\Facade\Pdf;
use Livewire\Attributes\On;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    protected function getSaveFormAction(): \Filament\Actions\Action
    {
        return parent::getSaveFormAction()
            ->label('Simpan Pesanan');
    }

    /**
     * When the IntegratedOrderItemsTable updates the order subtotal in DB,
     * it dispatches this event so we can refresh the form fields.
     */
    #[On('refreshOrderSummary')]
    public function handleRefreshOrderSummary(?int $subtotal = null): void
    {
        $this->record->refresh();
        $subtotal = $subtotal ?? (int) ($this->record->subtotal ?? 0);
        $this->data['subtotal'] = $subtotal;

        // Recalculate total price
        $tax = (int) ($this->data['tax'] ?? 0);
        $shipping = (int) ($this->data['shipping_cost'] ?? 0);
        $discount = (int) ($this->data['discount'] ?? 0);
        $expressFee = 0;
        if (!empty($this->data['is_express'])) {
            $expressFee = (int) ($this->data['express_fee'] ?? 0);
        }
        $this->data['total_price'] = max(0, $subtotal + $tax + $shipping + $expressFee - $discount);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('deliver_order')
                ->label('Serahkan Pesanan')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->form(\App\Filament\Resources\Orders\Schemas\OrderDeliveryForm::getComponents())
                ->action(function (array $data): void {
                    \App\Filament\Resources\Orders\Schemas\OrderDeliveryForm::processDelivery($this->record, $data);
                    $this->redirect(OrderResource::getUrl('edit', ['record' => $this->record]));
                })
                ->visible(fn(): bool => \App\Filament\Resources\Orders\Schemas\OrderDeliveryForm::isVisible($this->record)),
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
                    $data['shop_id'] = $this->record->shop_id;
                    $this->record->returns()->create($data);
                    \Filament\Notifications\Notification::make()
                        ->title('Retur Berhasil Dicatat')
                        ->success()
                        ->send();
                    $this->redirect(OrderResource::getUrl('edit', ['record' => $this->record]));
                })
                ->modalHeading('Catat Retur Pesanan')
                ->modalSubmitActionLabel('Simpan')
                ->visible(fn(): bool => $this->record->status === 'selesai'),
            DeleteAction::make()
                ->visible(fn() => auth()->user()->role === 'owner'),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['shop_id'] = Filament::getTenant()?->id;
 
        // Prefill virtual customer fields
        if (!empty($data['customer_id'])) {
            $customer = \App\Models\Customer::find($data['customer_id']);
            if ($customer) {
                $data['customer_phone'] = $customer->phone;
                $data['customer_address'] = $customer->address;
            }
        }
 
        return $data;
    }
 
    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Jika pesanan masih draft, dan sudah ada item pesanan,
        // maka otomatis ubah statusnya menjadi 'diterima' agar masuk ke meja desain/produksi.
        if ($this->record->status === 'draft') {
            $hasItems = $this->record->orderItems()->exists();
 
            if ($hasItems) {
                $data['status'] = 'diterima';
            }
        }
 
        return $data;
    }
 
    protected function afterSave(): void
    {
        $data = $this->form->getRawState();
 
        // Refresh Summary Relation Manager
        $this->dispatch('refreshOrderSummary', subtotal: (int) ($data['subtotal'] ?? 0));
    }
}
