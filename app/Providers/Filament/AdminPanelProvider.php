<?php

namespace App\Providers\Filament;

use App\Filament\Admin\Pages\Dashboard;
use App\Filament\Admin\Pages\PostedFundsPage;
use App\Filament\Admin\Pages\ReconciliationPage;
use App\Filament\Admin\Pages\Auth\AdminLogin;
use App\Filament\Admin\Pages\SystemMaintenancePage;
use App\Filament\Admin\Pages\SystemSettingsPage;
use App\Filament\Admin\Widgets\AdminStatsOverview;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
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
            ->favicon(asset('favicon-32x32.png'))
            ->brandLogo(asset('favicon-192x192.png'))
            ->darkModeBrandLogo(asset('favicon-192x192.png'))
            ->brandLogoHeight('5rem')
            ->simplePageMaxContentWidth(Width::FiveExtraLarge)
            ->login(AdminLogin::class)
            ->renderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE,
                fn(): \Illuminate\Contracts\View\View => view('filament.admin.auth.login-security-banner'),
                scopes: AdminLogin::class,
            )
            ->renderHook(
                PanelsRenderHook::STYLES_AFTER,
                fn(): \Illuminate\Contracts\View\View => view('filament.admin.auth.login-security-styles'),
                scopes: AdminLogin::class,
            )
            ->sidebarCollapsibleOnDesktop()
            ->sidebarFullyCollapsibleOnDesktop()
            ->brandName(fn(): string => app()->getLocale() === 'ar' ? 'فندفلو — لوحة الإدارة' : __('app.brand.admin'))
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            ->colors([
                'primary' => Color::Blue,
                'gray' => Color::Slate,
            ])
            ->plugins([
                FilamentShieldPlugin::make()
                    ->navigationGroup('system')
                    ->navigationLabel(fn(): string => __('app.nav.system_roles'))
                    ->navigationSort(1)
                    ->gridColumns([
                        'default' => 1,
                        'sm' => 2,
                        'lg' => 3,
                    ])
                    ->sectionColumnSpan(1)
                    ->checkboxListColumns([
                        'default' => 1,
                        'sm' => 2,
                    ])
                    ->resourceCheckboxListColumns([
                        'default' => 1,
                        'sm' => 2,
                    ]),
            ])
            ->navigationGroups([
                'membership' => NavigationGroup::make()->label(fn(): string => __('app.nav.group.membership')),
                'finance' => NavigationGroup::make()->label(fn(): string => __('app.nav.group.finance')),
                'settings' => NavigationGroup::make()->label(fn(): string => __('app.nav.group.settings'))
                    ->collapsed(),
                'system' => NavigationGroup::make()->label(fn(): string => __('app.nav.group.system'))
                    ->collapsed(),
            ])
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->discoverResources(in: app_path('Filament/Admin/Resources'), for: 'App\Filament\Admin\Resources')
            ->discoverPages(in: app_path('Filament/Admin/Pages'), for: 'App\Filament\Admin\Pages')
            ->pages([
                Dashboard::class,
                PostedFundsPage::class,
                ReconciliationPage::class,
                SystemSettingsPage::class,
                SystemMaintenancePage::class,
            ])
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
