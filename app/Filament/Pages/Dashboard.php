<?php

namespace App\Filament\Pages;

class Dashboard extends \Filament\Pages\Dashboard
{
    public static function canAccess(): bool
    {
        return in_array(auth()->user()->role, ['admin', 'designer', 'owner']);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return in_array(auth()->user()->role, ['admin', 'owner']);
    }

    public function mount(): void
    {
        if (auth()->user()->role === 'designer') {
            redirect(\App\Filament\Resources\DesignTasks\DesignTaskResource::getUrl());
        }
    }
}
