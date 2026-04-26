<?php

namespace App\Filament\Resources\DesignTasks\Pages;

use App\Filament\Resources\DesignTasks\DesignTaskResource;
use App\Filament\Resources\DesignTasks\Widgets\DesignHistoryTableWidget;
use Filament\Resources\Pages\ManageRecords;

class ManageDesignTasks extends ManageRecords
{
    protected static string $resource = DesignTaskResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getFooterWidgets(): array
    {
        return [
            DesignHistoryTableWidget::class,
        ];
    }
}
