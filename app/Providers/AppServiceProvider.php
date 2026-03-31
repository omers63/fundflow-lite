<?php

namespace App\Providers;

use App\Models\Contribution;
use App\Models\LoanInstallment;
use App\Observers\ContributionObserver;
use App\Observers\LoanInstallmentObserver;
use BezhanSalleh\LanguageSwitch\LanguageSwitch;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Contribution::observe(ContributionObserver::class);
        LoanInstallment::observe(LoanInstallmentObserver::class);

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
