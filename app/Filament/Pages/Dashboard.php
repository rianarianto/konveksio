<?php

namespace App\Filament\Pages;

class Dashboard extends \Filament\Pages\Dashboard
{
    public static function canAccess(): bool
    {
        return in_array(auth()->user()->role, ['admin', 'owner']);
    }
}
