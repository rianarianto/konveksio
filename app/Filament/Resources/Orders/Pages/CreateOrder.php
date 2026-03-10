<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Payment;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateOrder extends CreateRecord
{
    protected static string $resource = OrderResource::class;

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

    protected function afterCreate(): void
    {
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
}
