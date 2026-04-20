<?php

namespace App\Filament\Member\Pages;

use App\Filament\Member\Widgets\AccountBalancesWidget;
use App\Filament\Member\Widgets\ContributionHistoryWidget;
use App\Filament\Member\Widgets\LoanRepaymentProgressWidget;
use App\Filament\Member\Widgets\MemberStatsOverview;
use App\Filament\Member\Widgets\MemberStatusWidget;
use App\Filament\Member\Widgets\MemberWelcomeBannerWidget;
use App\Filament\Member\Widgets\UpcomingPaymentsWidget;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class Dashboard extends BaseDashboard
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-home';

    public function getTitle(): string
    {
        return __('My Dashboard');
    }

    public static function getNavigationLabel(): string
    {
        return __('My Dashboard');
    }

    /**
     * @return array<class-string>
     */
    public function getWidgets(): array
    {
        return [
            MemberWelcomeBannerWidget::class,
            ...$this->getMainDashboardWidgets(),
        ];
    }

    /**
     * @return array<class-string>
     */
    protected function getMainDashboardWidgets(): array
    {
        return [
            MemberStatusWidget::class,
            MemberStatsOverview::class,
            AccountBalancesWidget::class,
            UpcomingPaymentsWidget::class,
            LoanRepaymentProgressWidget::class,
            ContributionHistoryWidget::class,
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
