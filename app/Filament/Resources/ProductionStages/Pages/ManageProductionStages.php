<?php

namespace App\Filament\Resources\ProductionStages\Pages;

use App\Filament\Resources\ProductionStages\ProductionStageResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageProductionStages extends ManageRecords
{
    protected static string $resource = ProductionStageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->mutateFormDataUsing(function (array $data): array {
                    $data['shop_id'] = \Filament\Facades\Filament::getTenant()->id;
                    return $data;
                }),
        ];
    }
}
