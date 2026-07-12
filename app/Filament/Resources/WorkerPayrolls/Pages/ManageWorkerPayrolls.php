<?php

namespace App\Filament\Resources\WorkerPayrolls\Pages;

use App\Filament\Resources\WorkerPayrolls\WorkerPayrollResource;
use Filament\Resources\Pages\ManageRecords;

class ManageWorkerPayrolls extends ManageRecords
{
    protected static string $resource = WorkerPayrollResource::class;

    public function getTabs(): array
    {
        return [
            'borongan' => \Filament\Schemas\Components\Tabs\Tab::make('Upah Borongan')
                ->modifyQueryUsing(fn ($query) => $query
                    ->where('wage_type', 'piece_rate')
                    ->whereHas('productionTasks', fn($q) => $q->where('status', 'done')->where('is_paid', false))
                ),
            'bulanan' => \Filament\Schemas\Components\Tabs\Tab::make('Gaji Bulanan')
                ->modifyQueryUsing(fn ($query) => $query
                    ->where('wage_type', 'monthly')
                ),
        ];
    }
}
