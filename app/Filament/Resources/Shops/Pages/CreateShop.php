<?php

namespace App\Filament\Resources\Shops\Pages;

use App\Filament\Resources\Shops\ShopResource;
use Filament\Resources\Pages\CreateRecord;

class CreateShop extends CreateRecord
{
    protected static string $resource = ShopResource::class;

    protected function afterCreate(): void
    {
        // Automatically switch to the newly created shop
        $this->redirect($this->getResource()::getUrl('index', ['tenant' => $this->record]));
    }
}
