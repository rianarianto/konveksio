<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Payment;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\EditRecord;
use Filament\Actions\Action;
use Barryvdh\DomPDF\Facade\Pdf;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

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
                // Jangan override dengan null jika sebelumnya ada gambar dan user tidak mengupload baru
                // Tapi kalau user klik hapus gambar, $proofImage mungkin string kosong atau null di raw state? 
                // Di Filament jika dihapus biasanya state di-set null. $data['initial_payment_proof'] bernilai null.
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

        // Sync Spreadsheet Items
        $payload = $data['order_items_payload'] ?? null;
        if ($payload) {
            $items = json_decode($payload, true);
            
            // Wipe and re-create (simplest stateless sync)
            $this->record->orderItems()->delete();
            
            foreach ($items as $item) {
                $this->record->orderItems()->create([
                    'product_name' => $item['product_name'] ?? '',
                    'production_category' => $item['production_category'] ?? 'produksi',
                    'size' => $item['size'] ?? 'M',
                    'bahan_baju' => $item['bahan_baju'] ?? null,
                    'price' => $item['price'] ?? 0,
                    'quantity' => $item['quantity'] ?? 1,
                    'shop_id' => $this->record->shop_id,
                ]);
            }
        }
    }
}
