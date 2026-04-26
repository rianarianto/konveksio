<?php

namespace App\Filament\Resources\ControlProduksis\Pages;

use App\Filament\Resources\ControlProduksis\ControlProduksiResource;
use App\Models\OrderItem;
use App\Models\ProductionStage;
use App\Models\ProductionTask;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class ManageControlProduksis extends ManageRecords
{
    protected static string $resource = ControlProduksiResource::class;

    protected string $view = 'filament.resources.control-produksi.pages.manage-control-produksis';

    protected function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Resources\ControlProduksis\Widgets\ProduksiStats::class,
        ];
    }

    public function getTabs(): array
    {
        return [
            'semua' => Tab::make('Semua')
                ->badge(fn() => ControlProduksiResource::getEloquentQuery()->count()),
            'siap_potong' => Tab::make('Siap Potong')
                ->modifyQueryUsing(fn(Builder $query) => $query->whereHas('productionTasks', function ($q) {
                    $q->where('stage_name', 'Potong')->where('status', 'pending');
                })),
            'sedang_jahit' => Tab::make('Sedang Jahit')
                ->modifyQueryUsing(fn(Builder $query) => $query->whereHas('productionTasks', function ($q) {
                    $q->where('stage_name', 'Jahit')->whereIn('status', ['pending', 'in_progress']);
                })),
            'siap_qc' => Tab::make('Siap QC')
                ->modifyQueryUsing(fn(Builder $query) => $query->whereHas('productionTasks', function ($q) {
                    $q->where('stage_name', 'QC')->where('status', 'pending');
                })),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            // Action telah dipindah ke ControlProduksiResource sebagai Table Action
        ];
    }
}
