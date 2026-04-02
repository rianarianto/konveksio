<?php

namespace App\Filament\Resources\StoreSizes\Pages;

use App\Filament\Resources\StoreSizes\StoreSizeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditStoreSize extends EditRecord
{
    protected static string $resource = StoreSizeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
