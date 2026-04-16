<?php

namespace App\Filament\Admin\Pages;

use App\Models\Setting;
use App\Services\ContributionCycleService;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;

class ContributionCycleSettingsPage extends Page
{
    protected string $view = 'filament.admin.pages.contribution-cycle-settings';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationLabel = 'Contribution cycles';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-path-rounded-square';

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): ?string
    {
        return __('app.nav.group.settings');
    }

    public function getTitle(): string
    {
        return 'Contribution & repayment cycles';
    }

    public function mount(): void
    {
        $this->redirect(SystemSettingsPage::getUrl(['activeTab' => 'contribution-cycles']));
    }

    public function getHeaderActions(): array
    {
        return [
            Action::make('save_settings')
                ->label('Save settings')
                ->icon('heroicon-o-check')
                ->color('primary')
                ->fillForm([
                    'cycle_start_day' => Setting::contributionCycleStartDay(),
                ])
                ->schema([
                    Section::make('Cycle boundaries')
                        ->description(
                            'Each cycle is named by a calendar month (e.g. June). It starts on the chosen day of that month and ends the day before the same numbered day next month. The due date is the last day of that window (end of that day). This applies to contributions, dependent allocations, and loan repayments aligned to the same period.'
                        )
                        ->schema([
                            Forms\Components\TextInput::make('cycle_start_day')
                                ->label('Cycle start day (day of month)')
                                ->numeric()
                                ->required()
                                ->minValue(1)
                                ->maxValue(28)
                                ->default(6)
                                ->helperText(
                                    'Example: 6 → June cycle runs 6 Jun through 5 Jul; payment is due by end of 5 Jul. Limited to 28 to keep February consistent.'
                                ),
                        ]),
                ])
                ->action(function (array $data): void {
                    Setting::set('contribution.cycle_start_day', max(1, min(28, (int) $data['cycle_start_day'])));

                    Notification::make()
                        ->title('Cycle settings saved')
                        ->success()
                        ->send();
                }),
        ];
    }

    public function exampleJuneCycleLine(): string
    {
        $svc = app(ContributionCycleService::class);

        return $svc->cycleWindowDescription(6, (int) now()->year);
    }

    public function currentOpenPeriodLine(): string
    {
        $svc = app(ContributionCycleService::class);
        [$m, $y] = $svc->currentOpenPeriod();

        return $svc->periodLabel($m, $y) . ' — ' . $svc->cycleWindowDescription($m, $y);
    }
}
