<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('test:send-notif', function () {
    $owners = \App\Models\User::withoutGlobalScopes()->where('role', 'owner')->get();
    $this->info("Found " . $owners->count() . " owners");
    foreach ($owners as $owner) {
        $this->info("Sending to owner: " . $owner->name . " (ID: " . $owner->id . ")");
        \Filament\Notifications\Notification::make()
            ->title("🔔 Test Notifikasi Langsung")
            ->body("Ini adalah notifikasi uji coba langsung ke database.")
            ->warning()
            ->sendToDatabase($owner);
    }
    $this->info("Done sending.");
});

Artisan::command('check:notifications', function () {
    $notifs = \Illuminate\Support\Facades\DB::table('notifications')->latest()->take(5)->get();
    $this->info(json_encode($notifs, JSON_PRETTY_PRINT));
});

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
