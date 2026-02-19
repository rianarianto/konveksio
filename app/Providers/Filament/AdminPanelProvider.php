<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('app')
            ->tenant(\App\Models\Shop::class)
            ->tenantRegistration(\App\Filament\Pages\Tenancy\RegisterShop::class)
            ->tenantProfile(\App\Filament\Pages\Tenancy\EditTenantProfile::class)
            ->login(\App\Filament\Pages\Auth\CustomLogin::class)
            ->colors([
                'primary' => '#7F00FF',
            ])
            ->font('Rethink Sans')
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                \Filament\Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->renderHook(
                \Filament\View\PanelsRenderHook::SIMPLE_LAYOUT_END,
                fn () => view('filament.login-illustration'),
                scopes: \App\Filament\Pages\Auth\CustomLogin::class,
            )
            ->renderHook(
                \Filament\View\PanelsRenderHook::STYLES_AFTER,
                fn () => new \Illuminate\Support\HtmlString('
                    <link rel="stylesheet" href="' . asset('css/custom-login.css') . '">
                '),
            )
            ->userMenuItems([
                'profile' => \Filament\Navigation\MenuItem::make()
                    ->label('Edit Profile')
                    ->url(fn (): string => filament()->getTenant() ? \App\Filament\Pages\EditProfile::getUrl() : '#')
                    ->icon('heroicon-o-user-circle')
                    ->visible(fn (): bool => filament()->getTenant() !== null),
                'manage_shops' => \Filament\Navigation\MenuItem::make()
                    ->label('Manage All Shops')
                    ->url(fn (): string => filament()->getTenant() ? \App\Filament\Resources\Shops\ShopResource::getUrl('index') : '#')
                    ->icon('heroicon-o-building-storefront')
                    ->visible(fn (): bool => auth()->check() && auth()->user()->role === 'owner' && filament()->getTenant() !== null),
            ])
            ->sidebarCollapsibleOnDesktop()
            ->brandName('Konveksio');
    }
}
