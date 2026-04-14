<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Payment;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateOrder extends CreateRecord
{
    protected static string $resource = OrderResource::class;

    protected function afterCreate(): void
    {
        $payload = $this->data['order_items_payload'] ?? null;
        
        if ($payload) {
            $items = json_decode($payload, true);
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

        $data = $this->form->getRawState();

        if (!empty($data['initial_payment_amount']) && $data['initial_payment_amount'] > 0) {
            $proofImage = $data['initial_payment_proof'] ?? null;
            if (is_array($proofImage)) {
                $proofImage = array_values($proofImage)[0] ?? null;
            }

            Payment::create([
                'order_id' => $this->record->id,
                'amount' => $data['initial_payment_amount'],
                'payment_date' => now(),
                'payment_method' => $data['initial_payment_method'] ?? 'cash',
                'proof_image' => $proofImage,
                'recorded_by' => auth()->id(),
                'note' => 'Pembayaran Awal / DP (Otomatis saat pembuatan pesanan)',
            ]);
        }
    }

    protected static ?string $title = 'Tambah Pesanan Baru';

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['shop_id'] = Filament::getTenant()->id;
        $data['status'] = 'diterima';

        return $data;
    }

    protected function getCreateFormAction(): \Filament\Actions\Action
    {
        return parent::getCreateFormAction()
            ->label('Simpan Pesanan')
            ->extraAttributes([
                'style' => 'background-color: #7F00FF !important; color: white !important;',
            ]);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
