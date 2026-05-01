<?php

namespace App\Filament\Resources\Orders\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use App\Models\Order;

class SummaryRelationManager extends RelationManager
{
    protected static string $relationship = 'orderItems';
    protected static ?string $title = 'Ringkasan Pesanan';
    protected static string|\BackedEnum|null $icon = null;

    /**
     * Listen to the refresh event from IntegratedOrderItemsTable
     */
    #[\Livewire\Attributes\On('refreshOrderSummary')]
    public function refreshSummary(): void
    {
        $this->getOwnerRecord()->refresh();
    }

    /**
     * Override render method to show a custom view instead of a table.
     * This prevents the "repeating" issue.
     */
    public function render(): View
    {
        return view('filament.resources.orders.summary-tab', [
            'orderRecord' => $this->getOwnerRecord(),
        ]);
    }

    public function table(Table $table): Table
    {
        // We still need to define a basic table structure, 
        // but it won't be used because we overridden render()
        return $table->columns([]);
    }
}
