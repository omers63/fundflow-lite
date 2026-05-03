<?php

namespace App\Providers;

use App\Http\Responses\FilamentLogoutResponse;
use App\Models\Contribution;
use App\Models\LoanInstallment;
use App\Observers\ContributionObserver;
use App\Observers\LoanInstallmentObserver;
use App\Support\Notifications\DatabaseBellNotification;
use BezhanSalleh\LanguageSwitch\LanguageSwitch;
use Filament\Auth\Http\Responses\Contracts\LogoutResponse as LogoutResponseContract;
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Tables\Columns\Column;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Contracts\Routing\ResponseFactory as ResponseFactoryContract;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(LogoutResponseContract::class, FilamentLogoutResponse::class);
        $this->app->bind(FilamentNotification::class, DatabaseBellNotification::class);

        $this->app->singleton(ResponseFactoryContract::class, function ($app) {
            return new \App\Http\ResponseFactory($app['view'], $app['redirect']);
        });
    }

    public function boot(): void
    {
        Contribution::observe(ContributionObserver::class);
        LoanInstallment::observe(LoanInstallmentObserver::class);

        // Apply consistent filter UX (show/hide toggle) across all Filament tables.
        Table::configureUsing(function (Table $table): void {
            $table->filtersLayout(FiltersLayout::AboveContentCollapsible);
        });

        // Enable table column manager (show/hide) globally.
        Column::configureUsing(function (Column $column): void {
            $column->toggleable();
        });

        LanguageSwitch::configureUsing(function (LanguageSwitch $switch) {
            $switch
                ->locales(['en', 'ar'])
                ->labels([
                    'en' => 'English',
                    'ar' => 'العربية',
                ])
                ->flags([
                    'en' => 'https://flagcdn.com/w40/gb.png',
                    'ar' => 'https://flagcdn.com/w40/sa.png',
                ])
                ->circular()
                ->visible(insidePanels: true, outsidePanels: true)
                ->outsidePanelRoutes(['auth.login', 'auth.register'])
                ->renderHook('panels::user-menu.before');
        });
    }
}
