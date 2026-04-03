<?php

namespace App\Filament\Resources\StoreSizes\Schemas;

use Filament\Schemas\Schema;

class StoreSizeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Schemas\Components\Section::make('Informasi Ukuran Toko')
                    ->schema([
                        \Filament\Forms\Components\TextInput::make('name')
                            ->label('Nama Ukuran (Misal: S, M, L)')
                            ->required()
                            ->maxLength(255),
                        \Filament\Forms\Components\TextInput::make('sort_order')
                            ->label('Urutan Sorting')
                            ->numeric()
                            ->placeholder('0')
                            ->helperText('Angka yang lebih kecil tampil lebih dulu (0, 1, 2, dst)'),
                        \Filament\Forms\Components\Toggle::make('is_active')
                            ->label('Aktif')
                            ->default(true),
                    ]),
            ]);
    }
}
