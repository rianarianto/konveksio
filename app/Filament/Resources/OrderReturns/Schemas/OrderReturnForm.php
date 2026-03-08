<?php

namespace App\Filament\Resources\OrderReturns\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class OrderReturnForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components(static::getComponents());
    }

    public static function getComponents(bool $skipContextFields = false): array
    {
        $components = [];

        if (!$skipContextFields) {
            $components[] = Select::make('shop_id')
                ->label('Toko')
                ->relationship('shop', 'name')
                ->default(fn () => \Filament\Facades\Filament::getTenant()?->id)
                ->required();

            $components[] = Select::make('order_id')
                ->label('Pesanan')
                ->relationship('order', 'order_number')
                ->searchable()
                ->preload()
                ->required();
        }

        return array_merge($components, [
            DatePicker::make('return_date')
                ->label('Tanggal Retur')
                ->default(now())
                ->required(),
            TextInput::make('quantity')
                ->label('Jumlah (Pcs)')
                ->required()
                ->numeric()
                ->default(1),
            Select::make('status')
                ->label('Status')
                ->options([
                    'pending' => 'Pending',
                    'diproses' => 'Diproses',
                    'selesai' => 'Selesai',
                ])
                ->required()
                ->default('pending'),
            Textarea::make('items_description')
                ->label('Barang yang Diretur')
                ->placeholder('Misal: Kaos XL warna merah luntur')
                ->required()
                ->columnSpanFull(),
            Textarea::make('reason')
                ->label('Alasan Retur')
                ->placeholder('Misal: Jahitan di ketiak lepas, sablon miring, dll.')
                ->columnSpanFull(),
        ]);
    }
}
