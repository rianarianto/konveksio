<?php

namespace App\Filament\Resources\Workers\Schemas;

use Filament\Schemas\Components\Section;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use App\Models\Worker;

class WorkerInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Karyawan')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('name')
                            ->label('Nama'),
                        TextEntry::make('phone')
                            ->label('No. HP')
                            ->placeholder('-'),
                        IconEntry::make('is_active')
                            ->label('Status Aktif')
                            ->boolean(),
                        TextEntry::make('shop.name')
                            ->label('Toko'),
                    ]),

                Section::make('💰 Ringkasan Upah')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('monthly_earned')
                            ->label('Upah Bulan Ini')
                            ->state(fn(Worker $record): string => 'Rp ' . number_format($record->monthly_earned, 0, ',', '.'))
                            ->badge()
                            ->color('success'),

                        TextEntry::make('total_earned')
                            ->label('Total Upah (All Time)')
                            ->state(fn(Worker $record): string => 'Rp ' . number_format($record->total_earned, 0, ',', '.'))
                            ->badge()
                            ->color('primary'),

                        TextEntry::make('done_count')
                            ->label('Total Pcs Selesai')
                            ->state(fn(Worker $record): string => number_format($record->done_count, 0, ',', '.') . ' pcs')
                            ->badge()
                            ->color('info'),
                    ]),

                Section::make('⏳ Antrian Tugas (Pending)')
                    ->collapsible()
                    ->schema([
                        RepeatableEntry::make('productionTasks')
                            ->label('')
                            ->schema([
                                TextEntry::make('stage_name')->label('Tahap'),
                                TextEntry::make('quantity')->label('Qty')->suffix(' pcs')->badge()->color('warning'),
                                TextEntry::make('orderItem.product_name')->label('Produk'),
                                TextEntry::make('created_at')->label('Ditugaskan')->dateTime('d M Y'),
                            ])
                            ->columns(4)
                            ->getStateUsing(fn($record) => $record->productionTasks()->where('status', 'pending')->with('orderItem')->get()->toArray()),
                    ]),

                Section::make('🔨 Sedang Dikerjakan (In Progress)')
                    ->collapsible()
                    ->schema([
                        RepeatableEntry::make('productionTasksInProgress')
                            ->label('')
                            ->schema([
                                TextEntry::make('stage_name')->label('Tahap'),
                                TextEntry::make('quantity')->label('Qty')->suffix(' pcs')->badge()->color('info'),
                                TextEntry::make('orderItem.product_name')->label('Produk'),
                                TextEntry::make('updated_at')->label('Mulai Dikerjakan')->dateTime('d M Y'),
                            ])
                            ->columns(4)
                            ->getStateUsing(fn($record) => $record->productionTasks()->where('status', 'in_progress')->with('orderItem')->get()->toArray()),
                    ]),

                Section::make('✅ Riwayat Pekerjaan Selesai')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        RepeatableEntry::make('productionTasksDone')
                            ->label('')
                            ->schema([
                                TextEntry::make('stage_name')->label('Tahap'),
                                TextEntry::make('quantity')->label('Qty')->suffix(' pcs')->badge()->color('success'),
                                TextEntry::make('wage_amount')->label('Upah/pcs')->money('IDR'),
                                TextEntry::make('orderItem.product_name')->label('Produk'),
                                TextEntry::make('completed_at')->label('Selesai Pada')->dateTime('d M Y, H:i'),
                            ])
                            ->columns(3)
                            ->getStateUsing(fn($record) => $record->productionTasks()->where('status', 'done')->with('orderItem')->latest('completed_at')->get()->toArray()),
                    ]),
            ]);
    }
}

