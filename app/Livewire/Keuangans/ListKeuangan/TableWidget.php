<?php

namespace App\Livewire\Keuangans\ListKeuangan;

use Filament\Actions\BulkActionGroup;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseTableWidget;
use Illuminate\Database\Eloquent\Builder;
use yes;

class TableWidget extends BaseTableWidget
{
    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => yes::query())
            ->columns([
                //
            ])
            ->filters([
                //
            ])
            ->headerActions([
                //
            ])
            ->recordActions([
                //
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    //
                ]),
            ]);
    }
}
