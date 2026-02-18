<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateOrder extends CreateRecord
{
    protected static string $resource = OrderResource::class;

    protected static ?string $title = 'Tambah Pesanan Baru';

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['shop_id'] = Filament::getTenant()->id;

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
}
