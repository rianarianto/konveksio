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
                        TextEntry::make('pending_count')
                            ->label('⏳ Antrian')
                            ->state(fn(Worker $record): string => number_format($record->pending_count, 0, ',', '.') . ' pcs')
                            ->badge()
                            ->color('warning'),

                        TextEntry::make('in_progress_count')
                            ->label('🔨 Dikerjakan')
                            ->state(fn(Worker $record): string => number_format($record->in_progress_count, 0, ',', '.') . ' pcs')
                            ->badge()
                            ->color('info'),

                        TextEntry::make('done_count')
                            ->label('✅ Selesai')
                            ->state(fn(Worker $record): string => number_format($record->done_count, 0, ',', '.') . ' pcs')
                            ->badge()
                            ->color('success'),

                        TextEntry::make('unpaid_wage')
                            ->label('💸 Upah Belum Dibayar')
                            ->state(fn(Worker $record): string => 'Rp ' . number_format($record->unpaid_wage, 0, ',', '.'))
                            ->badge()
                            ->color('danger'),

                        TextEntry::make('monthly_earned')
                            ->label('🗓️ Upah Bulan Ini')
                            ->state(fn(Worker $record): string => 'Rp ' . number_format($record->monthly_earned, 0, ',', '.'))
                            ->badge()
                            ->color('success'),

                        TextEntry::make('total_earned')
                            ->label('🏆 Total Upah (All Time)')
                            ->state(fn(Worker $record): string => 'Rp ' . number_format($record->total_earned, 0, ',', '.'))
                            ->badge()
                            ->color('primary'),
                    ]),
            ]);
    }
}
