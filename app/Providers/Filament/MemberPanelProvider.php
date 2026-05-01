<?php

namespace App\Providers\Filament;

use App\Filament\Member\Pages\Dashboard;
use App\Filament\Member\Widgets\MemberStatsOverview;
use App\Http\Middleware\AuthenticateMemberPanel;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class MemberPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('member')
            ->path('member')
            ->favicon(asset('favicon-32x32.png'))
            ->login()
            ->sidebarCollapsibleOnDesktop()
            ->sidebarFullyCollapsibleOnDesktop()
            ->brandName(fn(): string => app()->getLocale() === 'ar' ? 'فندفلو — بوابة العضو' : __('app.brand.member'))
            ->renderHook(
                PanelsRenderHook::TOPBAR_START,
                fn(): \Illuminate\Contracts\View\View => view('filament.member.impersonation-topbar-banner'),
            )
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            ->colors([
                'primary' => Color::Sky,
                'gray' => Color::Slate,
            ])
            ->navigationGroups([
                'my_finance' => NavigationGroup::make()->label(fn(): string => __('app.nav.group.my_finance')),
                'loans' => NavigationGroup::make()->label(fn(): string => __('app.nav.group.loans')),
                'account' => NavigationGroup::make()->label(fn(): string => __('app.nav.group.account')),
                'settings' => NavigationGroup::make()->label(fn(): string => __('app.nav.group.settings')),
            ])
            ->viteTheme('resources/css/filament/member/theme.css')
            ->discoverResources(in: app_path('Filament/Member/Resources'), for: 'App\Filament\Member\Resources')
            ->discoverPages(in: app_path('Filament/Member/Pages'), for: 'App\Filament\Member\Pages')
            ->pages([Dashboard::class])
            ->discoverWidgets(in: app_path('Filament/Member/Widgets'), for: 'App\Filament\Member\Widgets')
            ->widgets([
                AccountWidget::class,
                MemberStatsOverview::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                AuthenticateMemberPanel::class,
            ]);
    }
}
