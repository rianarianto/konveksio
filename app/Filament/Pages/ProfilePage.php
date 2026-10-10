<?php

namespace App\Filament\Pages;

use Filament\Facades\Filament;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class ProfilePage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-circle';

    protected static ?string $navigationLabel = 'Profil Saya';

    protected static ?string $title = 'Profil Pengguna';

    protected static ?string $slug = 'profil';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.profile-page';

    public ?array $data = [];

    public function mount(): void
    {
        $user = auth()->user();

        $this->form->fill([
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'phone' => $user->phone,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Informasi Akun')
                    ->description('Perbarui informasi profil dan kontak Anda.')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama Lengkap')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('username')
                            ->label('Username')
                            ->required()
                            ->unique(table: 'users', column: 'username', ignorable: auth()->user())
                            ->alphaDash()
                            ->maxLength(255),

                        TextInput::make('email')
                            ->label('Alamat Email')
                            ->email()
                            ->required()
                            ->unique(table: 'users', column: 'email', ignorable: auth()->user())
                            ->maxLength(255),

                        TextInput::make('phone')
                            ->label('Nomor WhatsApp / HP')
                            ->tel()
                            ->placeholder('Contoh: 081234567890')
                            ->helperText('Digunakan untuk notifikasi sistem & persetujuan keuangan.')
                            ->maxLength(255),
                    ])
                    ->columns(2),

                Section::make('Ubah Kata Sandi')
                    ->description('Kosongkan bagian ini jika tidak ingin mengubah kata sandi lama.')
                    ->schema([
                        TextInput::make('current_password')
                            ->label('Kata Sandi Saat Ini')
                            ->password()
                            ->revealable()
                            ->requiredWith('new_password')
                            ->currentPassword(),

                        TextInput::make('new_password')
                            ->label('Kata Sandi Baru')
                            ->password()
                            ->revealable()
                            ->rule(Password::default()),

                        TextInput::make('new_password_confirmation')
                            ->label('Konfirmasi Kata Sandi Baru')
                            ->password()
                            ->revealable()
                            ->same('new_password')
                            ->requiredWith('new_password'),
                    ])
                    ->columns(3),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $user = auth()->user();

        $updateData = [
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $data['email'],
            'phone' => $data['phone'],
        ];

        if (!empty($data['new_password'])) {
            $updateData['password'] = Hash::make($data['new_password']);
        }

        $user->update($updateData);

        // Reset password fields in form
        $this->data['current_password'] = null;
        $this->data['new_password'] = null;
        $this->data['new_password_confirmation'] = null;

        Notification::make()
            ->title('Profil Berhasil Diperbarui')
            ->success()
            ->send();
    }
}
