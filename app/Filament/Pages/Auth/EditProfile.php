<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class EditProfile extends BaseEditProfile
{
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getNameFormComponent(),
                TextInput::make('username')
                    ->label('Username')
                    ->unique(ignoreRecord: true)
                    ->alphaDash()
                    ->maxLength(255)
                    ->placeholder('Contoh: owner_utama'),
                $this->getEmailFormComponent(),
                TextInput::make('phone')
                    ->label('Nomor WhatsApp / HP')
                    ->tel()
                    ->placeholder('Contoh: 081234567890')
                    ->helperText('Nomor ini digunakan untuk notifikasi sistem & persetujuan keuangan.')
                    ->maxLength(255),
                $this->getPasswordFormComponent(),
                $this->getPasswordConfirmationFormComponent(),
            ]);
    }
}
