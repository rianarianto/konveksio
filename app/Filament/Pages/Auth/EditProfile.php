<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class EditProfile extends BaseEditProfile
{
    protected static bool $isDiscovered = false;

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
                $this->getPasswordFormComponent()
                    ->helperText('Kosongkan jika tidak ingin mengubah kata sandi lama.'),
                $this->getPasswordConfirmationFormComponent(),
            ]);
    }

    protected function getPasswordFormComponent(): \Filament\Schemas\Components\Component
    {
        return parent::getPasswordFormComponent()
            ->helperText('Kosongkan jika tidak ingin mengubah kata sandi lama.');
    }
}
