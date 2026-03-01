<?php

namespace App\Filament\Resources\Keuangans\Pages;

use App\Filament\Resources\Keuangans\KeuanganResource;
use Filament\Resources\Pages\Page;
use App\Filament\Widgets\KeuanganStatsWidget;

class ListKeuangan extends Page
{
    protected static string $resource = KeuanganResource::class;
    
    protected string $view = 'filament.resources.keuangans.pages.list-keuangan';

    protected function getHeaderWidgets(): array
    {
        return [
            KeuanganStatsWidget::class,
        ];
    }
}
