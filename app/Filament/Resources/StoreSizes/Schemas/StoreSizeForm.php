<?php

namespace App\Filament\Resources\StoreSizes\Schemas;

use Filament\Schemas\Schema;

class StoreSizeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Schemas\Components\Section::make('Informasi Size Toko')
                    ->schema([
                        \Filament\Forms\Components\TextInput::make('name')
                            ->label('Nama Size (Misal: S, M, L, XL)')
                            ->required()
                            ->maxLength(255),
                        \Filament\Forms\Components\TextInput::make('size_details')
                            ->label('Detail Ukuran')
                            ->placeholder('Misal: LD: 50cm, P: 70cm')
                            ->helperText('Gunakan koma untuk memisahkan detail'),
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
