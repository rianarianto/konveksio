<?php

namespace App\Filament\Resources\Shops\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ShopForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('address')
                    ->required(),
                TextInput::make('phone')
                    ->label('No. Telepon / WhatsApp Toko')
                    ->tel()
                    ->helperText('Otomatis tersinkronisasi saat bot WhatsApp toko ini terhubung, atau bisa diisi manual.')
                    ->required(),
                TextInput::make('max_capacity_pcs')
                    ->label('Workshop Capacity (Pcs)')
                    ->numeric()
                    ->required()
                    ->default(2000),
            ]);
    }
}
