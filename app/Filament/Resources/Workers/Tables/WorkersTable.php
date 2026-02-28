<?php

namespace App\Filament\Resources\Workers\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class WorkersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nama Karyawan')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('phone')
                    ->label('No. HP')
                    ->searchable(),

                // --- Kolom Antrian Rincian ---
                TextColumn::make('pending_count')
                    ->label('⏳ Antrian')
                    ->badge()
                    ->color(fn($state): string => $state > 0 ? 'warning' : 'gray')
                    ->formatStateUsing(fn($state) => $state . ' pcs')
                    ->sortable()
                    ->tooltip('Tugas yang belum dimulai'),

                TextColumn::make('in_progress_count')
                    ->label('🔨 Dikerjakan')
                    ->badge()
                    ->color(fn($state): string => $state > 0 ? 'info' : 'gray')
                    ->formatStateUsing(fn($state) => $state . ' pcs')
                    ->sortable()
                    ->tooltip('Tugas yang sedang dikerjakan'),

                TextColumn::make('done_count')
                    ->label('✅ Selesai')
                    ->badge()
                    ->color(fn($state): string => $state > 0 ? 'success' : 'gray')
                    ->formatStateUsing(fn($state) => $state . ' pcs')
                    ->sortable()
                    ->tooltip('Total pcs yang sudah diselesaikan'),

                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Didaftarkan')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
