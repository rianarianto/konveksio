<?php

namespace App\Filament\Resources\PrintTypes\Pages;

use App\Filament\Resources\PrintTypes\PrintTypeResource;
use Filament\Actions\CreateAction;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Database\Eloquent\Builder;

class ManagePrintTypes extends ManageRecords
{
    protected static string $resource = PrintTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'jenis' => Tab::make('🎨 Jenis / Teknik')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('category', 'jenis'))
                ->badge(fn() => PrintTypeResource::getEloquentQuery()->where('category', 'jenis')->count()),
            'lokasi' => Tab::make('📍 Lokasi / Titik')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('category', 'lokasi'))
                ->badge(fn() => PrintTypeResource::getEloquentQuery()->where('category', 'lokasi')->count()),
        ];
    }
}
