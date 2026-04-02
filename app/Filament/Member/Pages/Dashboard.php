<?php

namespace App\Filament\Member\Pages;

use App\Filament\Member\Widgets\AccountBalancesWidget;
use App\Filament\Member\Widgets\ContributionHistoryWidget;
use App\Filament\Member\Widgets\LoanRepaymentProgressWidget;
use App\Filament\Member\Widgets\MemberStatsOverview;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-home';
    protected static ?string $title = 'My Dashboard';

    public function getWidgets(): array
    {
        return [
            MemberStatsOverview::class,
            AccountBalancesWidget::class,
            LoanRepaymentProgressWidget::class,
            ContributionHistoryWidget::class,
        ];
    }

    public function getColumns(): int|array
    {
        return [
            'default' => 1,
            'sm'      => 2,
            'xl'      => 2,
        ];
    }
}
