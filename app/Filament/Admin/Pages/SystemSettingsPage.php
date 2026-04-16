<?php

namespace App\Filament\Admin\Pages;

use App\Models\MembershipApplication;
use App\Models\Setting;
use App\Services\ContributionCycleService;
use BezhanSalleh\FilamentShield\Resources\Roles\RoleResource;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Livewire\Attributes\Url;

class SystemSettingsPage extends Page
{
    protected string $view = 'filament.admin.pages.system-settings';

    protected static ?string $navigationLabel = 'System Settings';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?int $navigationSort = 0;

    /** @var 'loans'|'contribution-cycles'|'public-membership'|'roles' */
    #[Url]
    public string $activeTab = 'loans';

    /** @var 'loan-rules'|'loan-tiers'|'fund-tiers' — used when {@see $activeTab} is `loans` */
    #[Url]
    public string $loanSubTab = 'loan-rules';

    public static function getNavigationGroup(): ?string
    {
        return __('app.nav.group.system');
    }

    /**
     * Keep this nav item highlighted while managing tier resources too.
     *
     * @return array<int, string>|string
     */
    public static function getNavigationItemActiveRoutePattern(): string|array
    {
        return [
            static::getRouteName(),
            'filament.admin.resources.loan-tiers.*',
            'filament.admin.resources.fund-tiers.*',
            'filament.admin.resources.roles.*',
            'filament.admin.pages.loan-settings-page',
            'filament.admin.pages.contribution-cycle-settings-page',
            'filament.admin.pages.public-membership-settings-page',
        ];
    }

    public function mount(): void
    {
        $legacyTop = [
            'loan-rules' => 'loans',
            'loan-tiers' => 'loans',
            'fund-tiers' => 'loans',
        ];
        if (isset($legacyTop[$this->activeTab])) {
            $legacySub = [
                'loan-rules' => 'loan-rules',
                'loan-tiers' => 'loan-tiers',
                'fund-tiers' => 'fund-tiers',
            ];
            $this->loanSubTab = $legacySub[$this->activeTab] ?? 'loan-rules';
            $this->activeTab = $legacyTop[$this->activeTab];
        }

        $allowedTop = ['loans', 'contribution-cycles', 'public-membership', 'roles'];
        if (!in_array($this->activeTab, $allowedTop, true)) {
            $this->activeTab = 'loans';
        }

        $allowedLoanSub = ['loan-rules', 'loan-tiers', 'fund-tiers'];
        if (!in_array($this->loanSubTab, $allowedLoanSub, true)) {
            $this->loanSubTab = 'loan-rules';
        }

        if ($this->activeTab !== 'loans') {
            $this->loanSubTab = 'loan-rules';
        }
    }

    public function updatedActiveTab(string $value): void
    {
        if ($value !== 'loans') {
            $this->loanSubTab = 'loan-rules';
        }
    }

    public static function canAccess(): bool
    {
        return true;
    }

    public function getTitle(): string
    {
        return 'System settings';
    }

    public function getHeaderActions(): array
    {
        return [
            Action::make('save_loan_settings')
                ->label('Save loan settings')
                ->icon('heroicon-o-check')
                ->color('primary')
                ->visible(fn(): bool => $this->activeTab === 'loans' && $this->loanSubTab === 'loan-rules')
                ->fillForm([
                    'settlement_threshold_pct' => Setting::loanSettlementThreshold() * 100,
                    'min_fund_balance' => Setting::loanMinFundBalance(),
                    'eligibility_months' => Setting::loanEligibilityMonths(),
                    'max_borrow_multiplier' => Setting::loanMaxBorrowMultiplier(),
                    'default_grace_cycles' => Setting::loanDefaultGraceCycles(),
                ])
                ->schema([
                    Section::make('Eligibility Rules')->schema([
                        Forms\Components\TextInput::make('eligibility_months')
                            ->label('Membership duration before eligible (months)')
                            ->numeric()->required()->minValue(1)->default(12),
                        Forms\Components\TextInput::make('min_fund_balance')
                            ->label('Minimum fund account balance (SAR)')
                            ->numeric()->prefix('SAR')->required()->minValue(0)->default(6000),
                        Forms\Components\TextInput::make('max_borrow_multiplier')
                            ->label('Max loan = N × fund balance')
                            ->numeric()->required()->minValue(1)->maxValue(10)->default(2),
                    ])->columns(3),
                    Section::make('Repayment Rules')->schema([
                        Forms\Components\TextInput::make('settlement_threshold_pct')
                            ->label('Settlement Threshold (% of loan)')
                            ->numeric()->suffix('%')->required()->minValue(0)->maxValue(100)->default(16)
                            ->helperText('Loan is settled when master fund is repaid AND member fund reaches this % of the loan amount.'),
                        Forms\Components\TextInput::make('default_grace_cycles')
                            ->label('Grace cycles before guarantor is debited')
                            ->numeric()->required()->minValue(1)->default(2)
                            ->helperText('Missed cycles before warning period ends and guarantor becomes liable.'),
                    ])->columns(2),
                ])
                ->action(function (array $data): void {
                    Setting::set('loan.settlement_threshold_pct', $data['settlement_threshold_pct'] / 100);
                    Setting::set('loan.min_fund_balance', $data['min_fund_balance']);
                    Setting::set('loan.eligibility_months', $data['eligibility_months']);
                    Setting::set('loan.max_borrow_multiplier', $data['max_borrow_multiplier']);
                    Setting::set('loan.default_grace_cycles', $data['default_grace_cycles']);

                    Notification::make()->title('Loan settings saved.')->success()->send();
                }),
            Action::make('save_cycle_settings')
                ->label('Save cycle settings')
                ->icon('heroicon-o-check')
                ->color('primary')
                ->visible(fn(): bool => $this->activeTab === 'contribution-cycles')
                ->fillForm([
                    'cycle_start_day' => Setting::contributionCycleStartDay(),
                ])
                ->schema([
                    Section::make('Cycle boundaries')
                        ->description(
                            'Each cycle is named by a calendar month and starts on the chosen day, ending the day before the same day next month.'
                        )
                        ->schema([
                            Forms\Components\TextInput::make('cycle_start_day')
                                ->label('Cycle start day (day of month)')
                                ->numeric()
                                ->required()
                                ->minValue(1)
                                ->maxValue(28)
                                ->default(6)
                                ->helperText('Example: 6 means June cycle runs 6 Jun through 5 Jul. Limited to 28 for February consistency.'),
                        ]),
                ])
                ->action(function (array $data): void {
                    Setting::set('contribution.cycle_start_day', max(1, min(28, (int) $data['cycle_start_day'])));

                    Notification::make()
                        ->title('Cycle settings saved')
                        ->success()
                        ->send();
                }),
            Action::make('save_public_membership_settings')
                ->label('Save public membership settings')
                ->icon('heroicon-o-check')
                ->color('primary')
                ->visible(fn(): bool => $this->activeTab === 'public-membership')
                ->fillForm([
                    'max_pending_public' => Setting::maxPublicApplications(),
                ])
                ->schema([
                    Section::make('Application capacity')
                        ->description('Controls the public application page at /apply. Existing member login is unchanged.')
                        ->schema([
                            Forms\Components\TextInput::make('max_pending_public')
                                ->label('Maximum applications (public apply)')
                                ->numeric()
                                ->minValue(0)
                                ->required()
                                ->default(0)
                                ->helperText('Counts all application rows. Use 0 for no limit.'),
                        ]),
                ])
                ->action(function (array $data): void {
                    Setting::set('membership.max_pending_public', max(0, (int) $data['max_pending_public']));

                    Notification::make()
                        ->title('Public membership settings saved')
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
        [$month, $year] = $svc->currentOpenPeriod();

        return $svc->periodLabel($month, $year) . ' — ' . $svc->cycleWindowDescription($month, $year);
    }

    public function getTotalApplicationsCount(): int
    {
        return MembershipApplication::query()->count();
    }

    public function canViewRolesPage(): bool
    {
        if (!class_exists(RoleResource::class)) {
            return false;
        }

        return RoleResource::canViewAny();
    }

    public function getRolesPageUrl(): ?string
    {
        if (!$this->canViewRolesPage()) {
            return null;
        }

        return RoleResource::getUrl('index');
    }
}
