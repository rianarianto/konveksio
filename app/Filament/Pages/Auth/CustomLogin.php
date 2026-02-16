<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\Login;
use Illuminate\Contracts\Support\Htmlable;

use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CustomLogin extends Login
{
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('email')
                    ->label('Email/Username')
                    ->placeholder('Johndoe@mail.comm')
                    ->email()
                    ->required()
                    ->autocomplete()
                    ->autofocus()
                    ->extraInputAttributes(['class' => 'custom-input']),

                TextInput::make('password')
                    ->label('Password')
                    ->placeholder('Password')
                    ->password()
                    ->revealable(filament()->arePasswordsRevealable())
                    ->required()
                    ->extraInputAttributes(['class' => 'custom-input']),

                Checkbox::make('remember')
                    ->label('Remember Me'),
            ]);
    }

    public function getHeading(): string|Htmlable|null
    {
        return 'Sign In';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Selamat datang, silahkan login dan lanjutkan pekerjaanmu.';
    }
}
