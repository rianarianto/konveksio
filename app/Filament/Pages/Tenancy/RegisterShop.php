<?php

namespace App\Filament\Pages\Tenancy;

use App\Models\Shop;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Pages\Tenancy\RegisterTenant;

class RegisterShop extends RegisterTenant
{
    public static function getLabel(): string
    {
        return 'Register Shop';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('address')
                    ->required(),
                TextInput::make('phone')
                    ->tel()
                    ->required(),
            ]);
    }

    protected function handleRegistration(array $data): Shop
    {
        $shop = Shop::create($data);

        $user = auth()->user();
        $user->shop()->associate($shop);
        $user->save();

        return $shop;
    }
}
