<?php

namespace App\Filament\Resources\DesignTasks\Pages;

use App\Filament\Resources\DesignTasks\DesignTaskResource;
use Filament\Actions\CreateAction;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Database\Eloquent\Builder;

class ManageDesignTasks extends ManageRecords
{
    protected static string $resource = DesignTaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'antrian' => Tab::make('Antrian Tugas')
                ->icon('heroicon-m-clock')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('design_status', ['pending', 'uploaded'])),
            'selesai' => Tab::make('Selesai')
                ->icon('heroicon-m-check-circle')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('design_status', 'approved')),
        ];
    }
}
