<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Widgets\AdminStatsOverview;
use App\Filament\Admin\Widgets\ContributionTrendWidget;
use App\Filament\Admin\Widgets\FundHealthWidget;
use App\Filament\Admin\Widgets\LoanPortfolioWidget;
use App\Filament\Admin\Widgets\LoanRepaymentTrendWidget;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-home';

    public function getWidgets(): array
    {
        return [
            AdminStatsOverview::class,
            FundHealthWidget::class,
            LoanPortfolioWidget::class,
            ContributionTrendWidget::class,
            LoanRepaymentTrendWidget::class,
        ];
    }

    public function getColumns(): int|array
    {
        return [
            'default' => 1,
            'sm'      => 2,
            'xl'      => 3,
        ];
    }
}
