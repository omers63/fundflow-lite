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
use App\Models\User;
use App\Support\WidgetVisibility;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class Dashboard extends BaseDashboard
{
    private const PREF_PANEL = 'admin';
    private const PREF_PAGE = 'dashboard';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-home';

    /**
     * @return array<class-string>
     */
    public function getWidgets(): array
    {
        $available = [
            AdminWelcomeBannerWidget::class,
            QuickPostWidget::class,
            ...$this->getMainDashboardWidgets(),
        ];

        /** @var User|null $user */
        $user = auth()->user();

        if (! $user instanceof User) {
            return $available;
        }

        return WidgetVisibility::selected($user, self::PREF_PANEL, self::PREF_PAGE, $available);
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

    public function getHeaderActions(): array
    {
        return [
            Action::make('configureWidgets')
                ->label('')
                ->icon('heroicon-o-adjustments-horizontal')
                ->tooltip(__('Customize widgets'))
                ->form([
                    CheckboxList::make('visible_widgets')
                        ->label(__('Visible widgets'))
                        ->options(WidgetVisibility::options($this->availableWidgets()))
                        ->columns(1),
                ])
                ->fillForm(function (): array {
                    /** @var User|null $user */
                    $user = auth()->user();

                    return [
                        'visible_widgets' => $user instanceof User
                            ? WidgetVisibility::selected($user, self::PREF_PANEL, self::PREF_PAGE, $this->availableWidgets())
                            : $this->availableWidgets(),
                    ];
                })
                ->action(function (array $data): void {
                    /** @var User|null $user */
                    $user = auth()->user();

                    if (! $user instanceof User) {
                        return;
                    }

                    WidgetVisibility::save(
                        $user,
                        self::PREF_PANEL,
                        self::PREF_PAGE,
                        $this->availableWidgets(),
                        array_values($data['visible_widgets'] ?? []),
                    );

                    Notification::make()
                        ->title(__('Widget preferences saved'))
                        ->success()
                        ->send();
                }),
        ];
    }

    /**
     * @return array<class-string>
     */
    private function availableWidgets(): array
    {
        return [
            AdminWelcomeBannerWidget::class,
            QuickPostWidget::class,
            ...$this->getMainDashboardWidgets(),
        ];
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
