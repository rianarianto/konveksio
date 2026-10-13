<?php

namespace App\Filament\Resources\WageCorrections\Pages;

use App\Filament\Resources\WageCorrections\WageCorrectionResource;
use Filament\Resources\Pages\ListRecords;

class ListWageCorrections extends ListRecords
{
    protected static string $resource = WageCorrectionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
