<?php

namespace App\Filament\Admin\Pages;

use App\Models\Setting;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Livewire\Attributes\Url;

class LoanSettingsPage extends Page
{
    protected string $view = 'filament.admin.pages.loan-settings';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationLabel = 'Loans';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

    protected static ?int $navigationSort = 1;

    /** @var 'settings'|'loan-tiers'|'fund-tiers' */
    #[Url]
    public string $activeTab = 'settings';

    public static function getNavigationGroup(): ?string
    {
        return __('app.nav.group.settings');
    }

    /**
     * Keep this nav item highlighted when creating/editing tiers (only reachable from this page).
     *
     * @return array<int, string>|string
     */
    public static function getNavigationItemActiveRoutePattern(): string|array
    {
        return [
            static::getRouteName(),
            'filament.admin.resources.loan-tiers.*',
            'filament.admin.resources.fund-tiers.*',
        ];
    }

    public function mount(): void
    {
        $this->redirect(SystemSettingsPage::getUrl(['activeTab' => 'loans', 'loanSubTab' => 'loan-rules']));
    }

    public function getHeaderActions(): array
    {
        return [
            Action::make('save_settings')
                ->label('Save settings')
                ->icon('heroicon-o-check')
                ->color('primary')
                ->visible(fn (): bool => $this->activeTab === 'settings')
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
                            ->helperText('Loan is settled when master fund is repaid AND member\'s fund account reaches this % of the loan amount.'),
                        Forms\Components\TextInput::make('default_grace_cycles')
                            ->label('Grace cycles before guarantor is debited')
                            ->numeric()->required()->minValue(1)->default(2)
                            ->helperText('Number of missed cycles that trigger a warning to borrower before holding guarantor liable.'),
                    ])->columns(2),
                ])
                ->action(function (array $data) {
                    Setting::set('loan.settlement_threshold_pct', $data['settlement_threshold_pct'] / 100);
                    Setting::set('loan.min_fund_balance', $data['min_fund_balance']);
                    Setting::set('loan.eligibility_months', $data['eligibility_months']);
                    Setting::set('loan.max_borrow_multiplier', $data['max_borrow_multiplier']);
                    Setting::set('loan.default_grace_cycles', $data['default_grace_cycles']);

                    Notification::make()->title('Loan settings saved.')->success()->send();
                }),
        ];
    }

    public function getTitle(): string
    {
        return 'Loan configuration';
    }
}
