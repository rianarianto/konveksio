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
