<?php

namespace App\Providers\Filament;

use App\Filament\Admin\Pages\Dashboard;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use App\Filament\Admin\Widgets\AdminStatsOverview;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
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
            ->brandName('FundFlow Admin')
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            ->colors([
                'primary' => Color::Emerald,
                'gray' => Color::Slate,
            ])
            ->plugins([
                FilamentShieldPlugin::make()
                    ->navigationGroup('Settings')
                    ->navigationSort(1)
                    ->gridColumns([
                        'default' => 1,
                        'sm'      => 2,
                        'lg'      => 3,
                    ])
                    ->sectionColumnSpan(1)
                    ->checkboxListColumns([
                        'default' => 1,
                        'sm'      => 2,
                    ])
                    ->resourceCheckboxListColumns([
                        'default' => 1,
                        'sm'      => 2,
                    ]),
            ])
            ->navigationGroups([
                NavigationGroup::make(__('app.nav.group.membership'))
                    ->icon('heroicon-o-users'),
                NavigationGroup::make(__('app.nav.group.finance'))
                    ->icon('heroicon-o-banknotes'),
                NavigationGroup::make('Banking')
                    ->icon('heroicon-o-building-library'),
                NavigationGroup::make(__('app.nav.group.reports'))
                    ->icon('heroicon-o-chart-bar'),
                NavigationGroup::make(__('app.nav.group.settings'))
                    ->collapsed(),
            ])
            ->discoverResources(in: app_path('Filament/Admin/Resources'), for: 'App\Filament\Admin\Resources')
            ->discoverPages(in: app_path('Filament/Admin/Pages'), for: 'App\Filament\Admin\Pages')
            ->pages([Dashboard::class])
            ->discoverWidgets(in: app_path('Filament/Admin/Widgets'), for: 'App\Filament\Admin\Widgets')
            ->widgets([
                AccountWidget::class,
                AdminStatsOverview::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
