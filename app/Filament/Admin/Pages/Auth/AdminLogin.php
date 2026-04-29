<?php

namespace App\Filament\Admin\Pages\Auth;

use Filament\Auth\Pages\Login;
use Illuminate\Contracts\Support\Htmlable;

class AdminLogin extends Login
{
    public function getHeading(): string|Htmlable|null
    {
        $appName = (string) config('app.short_name', 'FundFlow');

        return __($appName) . ' - ' . __('Family Fund Management System');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return app()->getLocale() === 'ar'
            ? 'تسجيل دخول الإدارة الآمنة'
            : 'Secure Administrator Sign In';
    }
}
