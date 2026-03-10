<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),

                TextInput::make('email')
                    ->label('Email Address')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),

                TextInput::make('password')
                    ->password()
                    ->revealable()
                    ->required(fn(string $operation): bool => $operation === 'create')
                    ->dehydrated(fn($state) => filled($state))
                    ->dehydrateStateUsing(fn($state) => Hash::make($state))
                    ->maxLength(255)
                    ->helperText('Kosongkan jika tidak ingin mengubah password.'),

                Select::make('role')
                    ->options([
                        'admin' => 'Admin Toko',
                        'designer' => 'Designer',
                    ])
                    ->default('admin')
                    ->required()
                    ->native(false),

                Select::make('wage_type')
                    ->label('Sistem Gaji')
                    ->options([
                        'monthly' => '📅 Bulanan (Gaji Pokok)',
                        'piece_rate' => '🔨 Borongan (Hanya jika ada tugas)',
                    ])
                    ->default('monthly')
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
