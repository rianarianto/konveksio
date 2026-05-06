<?php

namespace App\Filament\Resources\WorkerPayrollHistoryResource\Pages;

use App\Filament\Resources\WorkerPayrollHistoryResource;
use Filament\Resources\Pages\ListRecords;

class ListWorkerPayrollHistories extends ListRecords
{
    protected static string $resource = WorkerPayrollHistoryResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
