<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Widgets\AdminStatsOverview;
use App\Filament\Admin\Widgets\AdminWelcomeBannerWidget;
use App\Filament\Admin\Widgets\ContributionTrendWidget;
use App\Filament\Admin\Widgets\FeesRevenueWidget;
use App\Filament\Admin\Widgets\FundHealthWidget;
use App\Filament\Admin\Widgets\LoanPortfolioWidget;
use App\Filament\Admin\Widgets\LoanRepaymentTrendWidget;
use App\Filament\Admin\Widgets\MemberPipelineWidget;
use App\Filament\Admin\Widgets\OverdueInstallmentsWidget;
use App\Filament\Admin\Widgets\QuickPostWidget;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class Dashboard extends BaseDashboard
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-home';

    /**
     * @return array<class-string>
     */
    public function getWidgets(): array
    {
        return [
            AdminWelcomeBannerWidget::class,
            QuickPostWidget::class,
            ...$this->getMainDashboardWidgets(),
        ];
    }

    /**
     * @return array<class-string>
     */
    protected function getMainDashboardWidgets(): array
    {
        return [
            AdminStatsOverview::class,
            FundHealthWidget::class,
            FeesRevenueWidget::class,
            OverdueInstallmentsWidget::class,
            MemberPipelineWidget::class,
            LoanPortfolioWidget::class,
            ContributionTrendWidget::class,
            LoanRepaymentTrendWidget::class,
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(1)
                    ->schema(fn(): array => $this->getWidgetsSchemaComponents($this->getWidgets())),
            ]);
    }

    /**
     * Single-column dashboard: every widget is full width on its own row.
     *
     * @return int | array<string, ?int>
     */
    public function getColumns(): int|array
    {
        return 1;
    }
}
