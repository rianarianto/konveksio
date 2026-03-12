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
            ->login(\App\Filament\Pages\Auth\CustomLogin::class)
            ->colors([
                'primary' => [
                    50 => '#F2E6FF',
                    100 => '#E6CCFF',
                    200 => '#CC99FF',
                    300 => '#B366FF',
                    400 => '#9933FF',
                    500 => '#8000FF',
                    600 => '#8000FF', // Exact vibrant CTA color
                    700 => '#6600CC',
                    800 => '#4D0099',
                    900 => '#330066',
                    950 => '#1A0033',
                ],
            ])
            ->font('Rethink Sans')
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->navigationGroups([
                \Filament\Navigation\NavigationGroup::make()
                    ->label('PENJUALAN'),
                \Filament\Navigation\NavigationGroup::make()
                    ->label('KEUANGAN'),
                \Filament\Navigation\NavigationGroup::make()
                    ->label('PRODUKSI'),
                \Filament\Navigation\NavigationGroup::make()
                    ->label('INVENTORI & MASTER'),
                \Filament\Navigation\NavigationGroup::make()
                    ->label('KARYAWAN'),
                \Filament\Navigation\NavigationGroup::make()
                    ->label('PENGATURAN'),
            ])
            ->pages([
                \App\Filament\Pages\Dashboard::class,
            ])
            ->widgets([
                \App\Filament\Widgets\OwnerFinanceStatsWidget::class,
                \App\Filament\Widgets\OwnerVisualInsightsWidget::class,
                \App\Filament\Widgets\AktivitasUtamaWidget::class,
                \App\Filament\Widgets\DashboardRow2Widget::class,
                \App\Filament\Widgets\DashboardRow3Widget::class,
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
                fn() => view('filament.login-illustration'),
                scopes: \App\Filament\Pages\Auth\CustomLogin::class,
            )
            ->sidebarCollapsibleOnDesktop()
            ->icons([
                'panels::sidebar.collapse-button' => 'heroicon-o-chevron-double-left',
                'panels::sidebar.expand-button' => 'heroicon-o-chevron-double-right',
            ])
            ->renderHook(
                \Filament\View\PanelsRenderHook::STYLES_AFTER,
                fn() => new \Illuminate\Support\HtmlString('
                    <link rel="stylesheet" href="' . asset('css/custom-login.css') . '">
                    ' . \Illuminate\Support\Facades\Blade::render('@vite("resources/css/app.css")') . '
                    <style>
                        /* Disable click for admin & designer */
                        ' . (auth()->check() && in_array(auth()->user()->role, ['admin', 'designer']) ? '
                        .fi-tenant-menu {
                            pointer-events: none !important;
                            cursor: default !important;
                        }
                        ' : '') . '

                        /* Global x-cloak */
                        [x-cloak] { display: none !important; }

                        /* Global table vertical alignment */
                        .fi-ta-table td { vertical-align: top !important; }

                        /* Tombol actions tabel rata atas */
                        .fi-ta-record-actions { vertical-align: top !important; }

                        /* Custom Piutang Table UI Overrides */
                        .custom-piutang-pill {
                            display: inline-flex !important; align-items: center !important; gap: 6px !important;
                            padding: 2px 10px !important; border-radius: 9999px !important; 
                            border: 1px solid #e9d5ff !important; background-color: #faf5ff !important; 
                            color: #7e22ce !important; font-size: 12px !important; font-weight: 500 !important; width: max-content !important;
                        }
                        .custom-piutang-badge {
                            display: inline-flex !important; align-items: center !important; gap: 6px !important; 
                            padding: 4px 10px 4px 6px !important; border-radius: 6px !important; 
                            font-size: 12px !important; font-weight: 600 !important; width: max-content !important;
                        }
                        .custom-piutang-item-badge {
                            display: inline-flex !important; align-items: stretch !important; background-color: #f9fafb !important; 
                            border: 1px solid #e5e7eb !important; border-radius: 6px !important; overflow: hidden !important; 
                            font-size: 12px !important; height: 26px !important;
                        }
                        /* Custom Payment Pill */
                        .custom-payment-pill {
                            display: inline-flex !important; align-items: center !important; gap: 4px !important; 
                            padding: 2px 6px !important; background-color: #f3f4f6 !important; border: 1px solid #e5e7eb !important; 
                            border-radius: 4px !important; font-size: 10px !important; color: #4b5563 !important;
                        }

                        /* Fix Modal Z-Index Overlap on Table Actions */
                        .fi-modal {
                            z-index: 9999 !important;
                        }
                        .fi-modal-window {
                            z-index: 10000 !important;
                        }
                        
                        /* Ensure table action dropdowns don\'t cover modals */
                        .fi-dropdown-panel {
                            z-index: 40 !important; 
                        }

                    </style>
                '),
            )
            ->userMenuItems([
                'profile' => \Filament\Navigation\MenuItem::make()
                    ->label('Shop Settings')
                    ->url(fn(): string => filament()->getTenant() ? \App\Filament\Resources\Shops\ShopResource::getUrl('edit', ['record' => filament()->getTenant()]) : '#')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->visible(fn(): bool => filament()->getTenant() !== null),
                'manage_shops' => \Filament\Navigation\MenuItem::make()
                    ->label('Manage All Shops')
                    ->url(fn(): string => filament()->getTenant() ? \App\Filament\Resources\Shops\ShopResource::getUrl('index') : '#')
                    ->icon('heroicon-o-building-storefront')
                    ->visible(fn(): bool => auth()->check() && auth()->user()->role === 'owner' && filament()->getTenant() !== null),
            ])
            ->brandLogo(fn() => view('filament.brand-logo'))
            ->brandLogoHeight('2.5rem');
    }
}
