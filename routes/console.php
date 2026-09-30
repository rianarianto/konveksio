<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('check:notifications', function () {
    $notifs = \Illuminate\Support\Facades\DB::table('notifications')->latest()->take(5)->get();
    $this->info(json_encode($notifs, JSON_PRETTY_PRINT));
});

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
