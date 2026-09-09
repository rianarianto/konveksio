<?php

namespace App\Filament\Pages;

use Filament\Facades\Filament;
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

    public function getTitle(): string
    {
        $shopName = Filament::getTenant()?->name;
        return $shopName ? "Pengaturan WhatsApp — {$shopName}" : 'Pengaturan WhatsApp';
    }

    /**
     * Check if the current user is an Owner (for privileged actions like logout).
     */
    public function isOwner(): bool
    {
        return auth()->user()->role === 'owner';
    }

    /**
     * Get the active Shop ID.
     */
    public function getShopId(): int
    {
        return (int) (Filament::getTenant()?->id ?? 1);
    }

    /**
     * Get the active Shop Name.
     */
    public function getShopName(): string
    {
        return Filament::getTenant()?->name ?? 'Toko';
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
            $client = $client->withHeaders([
                'x-bot-key' => $secretKey,
                'x-shop-id' => (string) $this->getShopId(),
            ]);
        }
        return $client;
    }

    /**
     * Fetch bot status from the /api/status endpoint for active shop.
     */
    public function fetchBotStatus(): ?array
    {
        try {
            $response = $this->botClient()->get($this->getBotUrl() . '/api/status', [
                'shop_id' => $this->getShopId(),
            ]);
            if ($response->successful()) {
                return $response->json();
            }
            Log::warning("[WA Settings] Bot status fetch failed for shop {$this->getShopId()}: HTTP " . $response->status());
        } catch (\Exception $e) {
            Log::warning("[WA Settings] Bot status fetch error for shop {$this->getShopId()}: " . $e->getMessage());
        }
        return null;
    }

    /**
     * Fetch message logs from the bot for active shop.
     */
    public function fetchLogs(int $limit = 20): ?array
    {
        try {
            $response = $this->botClient()->get($this->getBotUrl() . '/api/logs', [
                'shop_id' => $this->getShopId(),
                'limit' => $limit,
            ]);
            if ($response->successful()) {
                return $response->json();
            }
        } catch (\Exception $e) {
            Log::warning("[WA Settings] Bot logs fetch error for shop {$this->getShopId()}: " . $e->getMessage());
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
                ->body("Status koneksi WhatsApp ({$this->getShopName()}) berhasil disinkronkan.")
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
            $response = $this->botClient()->post($this->getBotUrl() . '/api/reconnect', [
                'shop_id' => $this->getShopId(),
            ]);
            
            \Filament\Notifications\Notification::make()
                ->title('Proses Reconnect Dimulai')
                ->body("Menyambung ulang session WhatsApp {$this->getShopName()}. Tunggu beberapa detik...")
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
                'shop_id' => $this->getShopId(),
            ]);

            $data = $response->json();
            $isSuccess = $response->successful() && ($data['status'] ?? '') === 'berhasil terkirim';

            if ($isSuccess) {
                \Filament\Notifications\Notification::make()
                    ->title('Pesan Terkirim!')
                    ->body("Pesan berhasil diserahkan ke WhatsApp via {$this->getShopName()} (ID: " . ($data['id'] ?? '-') . ").")
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
            $this->botClient()->post($this->getBotUrl() . '/api/logout', [
                'shop_id' => $this->getShopId(),
            ]);
            
            \Filament\Notifications\Notification::make()
                ->title('WhatsApp Logout')
                ->body("Session WhatsApp {$this->getShopName()} telah dihapus. Silakan scan QR code baru.")
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
            'shopId' => $this->getShopId(),
            'shopName' => $this->getShopName(),
        ];
    }
}
