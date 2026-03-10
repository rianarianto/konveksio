<?php

namespace App\Filament\Resources\Keuangans\Pages;

use App\Filament\Resources\Keuangans\KasMasukResource;
use Filament\Resources\Pages\Page;
use App\Filament\Widgets\KeuanganStatsWidget;

class ListKasMasuk extends Page
{
    protected static string $resource = KasMasukResource::class;

    protected string $view = 'filament.resources.keuangans.pages.list-kas-masuk';

    protected function getHeaderWidgets(): array
    {
        return [
            KeuanganStatsWidget::class,
        ];
    }
}
