<?php

namespace App\Filament\Resources\Workers\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

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
                                TextEntry::make('orderItem.product_name')->label('Produk'),
                                TextEntry::make('updated_at')->label('Selesai Pada')->dateTime('d M Y'),
                            ])
                            ->columns(4)
                            ->getStateUsing(fn($record) => $record->productionTasks()->where('status', 'done')->with('orderItem')->get()->toArray()),
                    ]),
            ]);
    }
}
