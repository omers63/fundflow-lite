<?php

namespace App\Filament\Member\Pages;

use App\Filament\Member\Widgets\AccountBalancesWidget;
use App\Filament\Member\Widgets\ContributionHistoryWidget;
use App\Filament\Member\Widgets\LoanRepaymentProgressWidget;
use App\Filament\Member\Widgets\MemberStatsOverview;
use App\Filament\Member\Widgets\MemberStatusWidget;
use App\Filament\Member\Widgets\MemberWelcomeBannerWidget;
use App\Filament\Member\Widgets\UpcomingPaymentsWidget;
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
    private const PREF_PANEL = 'member';
    private const PREF_PAGE = 'dashboard';

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
        $available = [
            MemberWelcomeBannerWidget::class,
            ...$this->getMainDashboardWidgets(),
        ];

        /** @var User|null $user */
        $user = auth()->user();

        if (!$user instanceof User) {
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

    public function getHeaderActions(): array
    {
        return [
            Action::make('configureWidgets')
                ->label(__('Customize widgets'))
                ->icon('heroicon-o-adjustments-horizontal')
                ->iconButton()
                ->tooltip(__('Customize widgets'))
                ->modalHeading(__('Customize widgets'))
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

                    if (!$user instanceof User) {
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
            MemberWelcomeBannerWidget::class,
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
