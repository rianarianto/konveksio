<?php

namespace App\Filament\Resources\Workers\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class WorkerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Forms\Components\Hidden::make('shop_id')
                    ->default(fn() => \Filament\Facades\Filament::getTenant()->id),
                TextInput::make('name')
                    ->label('Nama Karyawan/Tukang')
                    ->required()
                    ->maxLength(255),
                TextInput::make('phone')
                    ->label('Nomor HP')
                    ->tel()
                    ->maxLength(255),
                Toggle::make('is_active')
                    ->label('Status Aktif')
                    ->default(true)
                    ->required(),
                Select::make('wage_type')
                    ->label('Sistem Upah')
                    ->options([
                        'piece_rate' => '🔨 Borongan (Per Pcs)',
                        'monthly' => '📅 Bulanan (Gaji Pokok)',
                    ])
                    ->default('piece_rate')
                    ->required()
                    ->native(false)
                    ->live(),
                TextInput::make('base_salary')
                    ->label('Gaji Pokok (Rp)')
                    ->numeric()
                    ->prefix('Rp')
                    ->visible(fn($get) => $get('wage_type') === 'monthly')
                    ->required(fn($get) => $get('wage_type') === 'monthly'),
            ]);
    }
}
