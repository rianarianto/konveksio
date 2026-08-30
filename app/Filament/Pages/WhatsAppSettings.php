<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppSettings extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationLabel = 'WhatsApp';

    protected static ?string $title = 'Pengaturan WhatsApp';

    protected static ?string $slug = 'whatsapp-settings';

    protected static string|\UnitEnum|null $navigationGroup = 'PENGATURAN';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.whatsapp-settings';

    /**
     * Only Admin and Owner can access this page.
     */
    public static function canAccess(): bool
    {
        return auth()->check() && in_array(auth()->user()->role, ['admin', 'owner']);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && in_array(auth()->user()->role, ['admin', 'owner']);
    }

    /**
     * Check if the current user is an Owner (for privileged actions like logout).
     */
    public function isOwner(): bool
    {
        return auth()->user()->role === 'owner';
    }

    /**
     * Get the WA Bot base URL from config.
     */
    protected function getBotUrl(): string
    {
        $url = config('services.wa_bot.url', 'https://duniabordirkomputer.com/bot');
        return rtrim($url, '/');
    }

    /**
     * Get the secret key for API authentication.
     */
    protected function getSecretKey(): ?string
    {
        return config('services.wa_bot.secret_key') ?: env('BOT_SECRET_KEY');
    }

    /**
     * Build an authenticated HTTP client for the bot API.
     */
    protected function botClient(): \Illuminate\Http\Client\PendingRequest
    {
        $client = Http::timeout(10);
        $secretKey = $this->getSecretKey();
        if ($secretKey) {
            $client = $client->withHeaders(['x-bot-key' => $secretKey]);
        }
        return $client;
    }

    /**
     * Fetch bot status from the /api/status endpoint.
     * Returns null on failure.
     */
    public function fetchBotStatus(): ?array
    {
        try {
            $response = $this->botClient()->get($this->getBotUrl() . '/api/status');
            if ($response->successful()) {
                return $response->json();
            }
            Log::warning('[WA Settings] Bot status fetch failed: HTTP ' . $response->status());
        } catch (\Exception $e) {
            Log::warning('[WA Settings] Bot status fetch error: ' . $e->getMessage());
        }
        return null;
    }

    /**
     * Fetch message logs from the bot.
     */
    public function fetchLogs(int $limit = 20): ?array
    {
        try {
            $response = $this->botClient()->get($this->getBotUrl() . '/api/logs', [
                'limit' => $limit,
            ]);
            if ($response->successful()) {
                return $response->json();
            }
        } catch (\Exception $e) {
            Log::warning('[WA Settings] Bot logs fetch error: ' . $e->getMessage());
        }
        return null;
    }

    public function actionRefresh(): void
    {
        $status = $this->fetchBotStatus();
        $logs = $this->fetchLogs();

        if ($status) {
            $this->dispatch('wa-status-updated', status: $status, logs: $logs);
            \Filament\Notifications\Notification::make()
                ->title('Data Diperbarui')
                ->body('Status koneksi & metrik WhatsApp berhasil disinkronkan.')
                ->success()
                ->send();
        } else {
            \Filament\Notifications\Notification::make()
                ->title('Gagal Sinkronisasi')
                ->body('Tidak dapat menghubungi server WhatsApp Bot.')
                ->danger()
                ->send();
        }
    }

    public function actionReconnect(): void
    {
        try {
            $response = $this->botClient()->post($this->getBotUrl() . '/api/reconnect');
            
            \Filament\Notifications\Notification::make()
                ->title('Proses Reconnect Dimulai')
                ->body('Menyambung ulang session WhatsApp. Tunggu beberapa detik...')
                ->info()
                ->send();

            $this->dispatch('wa-reconnecting');
        } catch (\Exception $e) {
            \Filament\Notifications\Notification::make()
                ->title('Gagal Reconnect')
                ->body('Error: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function actionSendTest(string $number, string $message): void
    {
        if (empty($number) || empty($message)) {
            \Filament\Notifications\Notification::make()
                ->title('Form Belum Lengkap')
                ->body('Silakan masukkan nomor WhatsApp tujuan dan pesan uji coba.')
                ->warning()
                ->send();
            return;
        }

        try {
            $response = $this->botClient()->post($this->getBotUrl() . '/api', [
                'nohp' => $number,
                'pesan' => $message,
            ]);

            $data = $response->json();
            $isSuccess = $response->successful() && ($data['status'] ?? '') === 'berhasil terkirim';

            if ($isSuccess) {
                \Filament\Notifications\Notification::make()
                    ->title('Pesan Terkirim!')
                    ->body("Pesan berhasil diserahkan ke WhatsApp (ID: " . ($data['id'] ?? '-') . ").")
                    ->success()
                    ->send();
            } else {
                \Filament\Notifications\Notification::make()
                    ->title('Gagal Mengirim')
                    ->body($data['pesan'] ?? 'WhatsApp client menolak pengiriman pesan.')
                    ->danger()
                    ->send();
            }

            $this->actionRefresh();
        } catch (\Exception $e) {
            \Filament\Notifications\Notification::make()
                ->title('Koneksi Error')
                ->body('Error: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function actionLogout(): void
    {
        if (!$this->isOwner()) {
            return;
        }

        try {
            $this->botClient()->post($this->getBotUrl() . '/api/logout');
            
            \Filament\Notifications\Notification::make()
                ->title('WhatsApp Logout')
                ->body('Session lama telah dihapus. Silakan scan QR code baru.')
                ->warning()
                ->send();

            $this->actionRefresh();
        } catch (\Exception $e) {
            \Filament\Notifications\Notification::make()
                ->title('Gagal Logout')
                ->body('Error: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Pass data to the Blade view.
     */
    protected function getViewData(): array
    {
        $botStatus = $this->fetchBotStatus();
        $logs = $this->fetchLogs();

        return [
            'botStatus' => $botStatus,
            'logs' => $logs,
            'isOwner' => $this->isOwner(),
            'botUrl' => $this->getBotUrl(),
            'secretKey' => $this->getSecretKey(),
        ];
    }
}
