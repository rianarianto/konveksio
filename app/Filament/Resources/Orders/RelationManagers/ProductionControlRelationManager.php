<?php

namespace App\Filament\Resources\Orders\RelationManagers;

use App\Models\OrderItem;
use App\Models\ProductionTask;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Tables\Grouping\Group as TableGroup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

class ProductionControlRelationManager extends RelationManager
{
    protected static string $relationship = 'orderItems';

    protected static ?string $title = 'Monitoring Produksi';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-presentation-chart-line';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('product_name')
            ->columns([
                TextColumn::make('product_name')
                    ->label('Produk')
                    ->weight('bold')
                    ->description(fn(OrderItem $record): string => match ($record->production_category) {
                        'custom' => '🧵 Konveksi (Ukur Badan)',
                        'non_produksi' => '📦 Baju Jadi',
                        'jasa' => '🔧 Jasa',
                        default => '🏭 Konveksi',
                    }),

                TextColumn::make('recipient_name')
                    ->label('Penerima')
                    ->placeholder('-'),

                TextColumn::make('quantity')
                    ->label('Qty')
                    ->numeric(),

                TextColumn::make('progress_status')
                    ->label('Status Produksi')
                    ->badge()
                    ->state(function (OrderItem $record) {
                        $tasks = $record->productionTasks;
                        if ($tasks->count() === 0) return 'Belum Diatur';
                        if ($tasks->where('status', '!=', 'done')->count() === 0) return 'Selesai';
                        if ($tasks->where('status', 'in_progress')->count() > 0) return 'Diproses';
                        return 'Antrian';
                    })
                    ->color(fn(string $state): string => match ($state) {
                        'Belum Diatur' => 'gray',
                        'Antrian' => 'warning',
                        'Diproses' => 'info',
                        'Selesai' => 'success',
                        default => 'gray',
                    })
                    ->description(function (OrderItem $record) {
                        $tasks = $record->productionTasks;
                        if ($tasks->isEmpty()) return null;
                        $activeTask = $tasks->firstWhere('status', 'in_progress');
                        if ($activeTask) return '🔨 ' . $activeTask->stage_name . ' — ' . ($activeTask->assignedTo?->name ?? '-');
                        $pendingTask = $tasks->where('status', 'pending')->sortBy('id')->first();
                        if ($pendingTask) return '⏳ Menunggu: ' . $pendingTask->stage_name;
                        return null;
                    }),
            ])
            ->actions([
                Action::make('go_to_control')
                    ->label('Detail Produksi')
                    ->icon('heroicon-o-magnifying-glass-circle')
                    ->color('primary')
                    ->url(fn (OrderItem $record): string => \App\Filament\Resources\ControlProduksis\ControlProduksiResource::getUrl('index', [
                        'tableSearch' => $record->order->order_number
                    ])),
            ])
            ->headerActions([
                Action::make('open_main_control')
                    ->label('Buka Control Produksi Utama')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn (): string => \App\Filament\Resources\ControlProduksis\ControlProduksiResource::getUrl()),
            ])
            ->defaultSort('id', 'asc')
            ->paginated(false);
    }
}
