<?php

namespace App\Providers\Filament;

use App\Filament\Widgets\PlatformStats;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
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
            ->path('admin')
            ->login()
            ->brandName('DICT Free Wi-Fi')
            ->colors([
                // One durable government-service accent across the panel.
                'primary' => Color::hex('#144666'),
            ])
            ->favicon(
                'data:image/svg+xml;base64,'.base64_encode(
                    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32">'
                    .'<rect width="32" height="32" rx="7" fill="#144666"/>'
                    .'<path d="M8 14a12 12 0 0 1 16 0" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round"/>'
                    .'<path d="M12 19a6.5 6.5 0 0 1 8 0" fill="none" stroke="#d9a437" stroke-width="3" stroke-linecap="round"/>'
                    .'<circle cx="16" cy="24" r="2" fill="#fff"/>'
                    .'</svg>'
                )
            )
            // A 12-column table is unreadable in a collapsed sidebar on a tablet.
            ->sidebarCollapsibleOnDesktop()
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                PlatformStats::class,
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
            ]);
    }
}
