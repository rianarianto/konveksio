<?php

namespace App\Filament\Resources\StoreSizes\Pages;

use App\Filament\Resources\StoreSizes\StoreSizeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListStoreSizes extends ListRecords
{
    protected static string $resource = StoreSizeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
