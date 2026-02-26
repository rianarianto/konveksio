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
                    ->default(fn () => \Filament\Facades\Filament::getTenant()->id),
                TextInput::make('name')
                    ->label('Nama Karyawan/Tukang')
                    ->required()
                    ->maxLength(255),
                Select::make('category')
                    ->label('Kategori / Posisi')
                    ->options([
                        'Jahit' => 'Jahit',
                        'Sablon' => 'Sablon',
                        'Bordir' => 'Bordir',
                        'Potong' => 'Potong',
                        'Obras' => 'Obras',
                        'Finishing' => 'Finishing',
                        'Packing' => 'Packing',
                    ])
                    ->searchable()
                    ->required(),
                TextInput::make('phone')
                    ->label('Nomor HP')
                    ->tel()
                    ->maxLength(255),
                Toggle::make('is_active')
                    ->label('Status Aktif')
                    ->default(true)
                    ->required(),
            ]);
    }
}
