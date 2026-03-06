<?php

namespace App\Filament\Resources\AddonOptions\Pages;

use App\Filament\Resources\AddonOptions\AddonOptionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAddonOptions extends ListRecords
{
    protected static string $resource = AddonOptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('+ Tambah Opsi'),
        ];
    }
}
