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

    /**
     * When the IntegratedOrderItemsTable updates the order subtotal in DB,
     * it dispatches this event so we can refresh the form fields.
     */
    #[On('refreshOrderSummary')]
    public function handleRefreshOrderSummary(int $subtotal): void
    {
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
            Action::make('download_receipt')
                ->label('Download Kuitansi')
                ->icon('heroicon-o-document-arrow-down')
                ->color('primary')
                ->url(fn() => route('orders.receipt', $this->record))
                ->openUrlInNewTab(),
            Action::make('create_return')
                ->label('Catat Retur')
                ->icon('heroicon-o-arrow-path')
                ->form(\App\Filament\Resources\OrderReturns\Schemas\OrderReturnForm::getComponents(true))
                ->action(function (array $data): void {
                    $this->record->returns()->create($data);
                    \Filament\Notifications\Notification::make()
                        ->title('Retur Berhasil Dicatat')
                        ->success()
                        ->send();
                })
                ->modalHeading('Catat Retur Pesanan')
                ->modalSubmitActionLabel('Simpan Retur'),
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

        // Prefill virtual initial payment fields from the first payment record
        $firstPayment = Payment::where('order_id', $this->record->id)
            ->orderBy('payment_date', 'asc')
            ->orderBy('id', 'asc')
            ->first();

        if ($firstPayment) {
            $data['initial_payment_amount'] = $firstPayment->amount;
            $data['initial_payment_method'] = $firstPayment->payment_method;
            $data['initial_payment_proof'] = $firstPayment->proof_image;
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Jika pesanan masih draft, dan sudah ada item pesanan atau ada nominal DP yang diinput,
        // maka otomatis ubah statusnya menjadi 'diterima' agar masuk ke meja desain/produksi.
        if ($this->record->status === 'draft') {
            $hasItems = $this->record->orderItems()->exists();
            $hasInitialPayment = (int) ($data['initial_payment_amount'] ?? 0) > 0;

            if ($hasItems || $hasInitialPayment) {
                $data['status'] = 'diterima';
            }
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $data = $this->form->getRawState();

        if (isset($data['initial_payment_amount'])) {
            $proofImage = $data['initial_payment_proof'] ?? null;
            if (is_array($proofImage)) {
                $proofImage = array_values($proofImage)[0] ?? null;
            }

            $firstPayment = Payment::where('order_id', $this->record->id)
                ->orderBy('payment_date', 'asc')
                ->orderBy('id', 'asc')
                ->first();

            if ($firstPayment) {
                $firstPayment->update([
                    'amount' => (int) $data['initial_payment_amount'],
                    'payment_method' => $data['initial_payment_method'] ?? $firstPayment->payment_method,
                    'proof_image' => array_key_exists('initial_payment_proof', $data) ? $proofImage : $firstPayment->proof_image,
                ]);
            } elseif ($data['initial_payment_amount'] > 0) {
                Payment::create([
                    'order_id' => $this->record->id,
                    'amount' => (int) $data['initial_payment_amount'],
                    'payment_date' => now(),
                    'payment_method' => $data['initial_payment_method'] ?? 'cash',
                    'proof_image' => $proofImage,
                    'recorded_by' => auth()->id(),
                    'note' => 'Pembayaran Awal / DP (Otomatis dari form pesanan)',
                ]);
            }
        }
    }
}
