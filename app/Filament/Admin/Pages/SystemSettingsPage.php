<?php

namespace App\Filament\Admin\Pages;

use App\Models\MembershipApplication;
use App\Models\Setting;
use App\Services\ContributionCycleService;
use App\Services\EmailTemplateService;
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

    protected static ?string $navigationLabel = null;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?int $navigationSort = 0;

    public static function getNavigationLabel(): string
    {
        return __('System Settings');
    }

    /** @var 'loans'|'contribution-cycles'|'public-membership'|'statements'|'communication'|'roles' */
    #[Url]
    public string $activeTab = 'loans';

    /** @var 'loan-rules'|'loan-tiers'|'fund-tiers' — used when {@see $activeTab} is `loans` */
    #[Url]
    public string $loanSubTab = 'loan-rules';

    public static function getNavigationGroup(): ?string
    {
        return 'system';
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

        $allowedTop = ['loans', 'contribution-cycles', 'public-membership', 'statements', 'communication', 'roles'];
        if (! in_array($this->activeTab, $allowedTop, true)) {
            $this->activeTab = 'loans';
        }

        $allowedLoanSub = ['loan-rules', 'loan-tiers', 'fund-tiers'];
        if (! in_array($this->loanSubTab, $allowedLoanSub, true)) {
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
        return __('System settings');
    }

    public function getHeaderActions(): array
    {
        return [
            Action::make('save_loan_settings')
                ->label(__('Save loan settings'))
                ->icon('heroicon-o-check')
                ->color('primary')
                ->visible(fn (): bool => $this->activeTab === 'loans' && $this->loanSubTab === 'loan-rules')
                ->fillForm([
                    'settlement_threshold_pct' => Setting::loanSettlementThreshold() * 100,
                    'min_fund_balance' => Setting::loanMinFundBalance(),
                    'eligibility_months' => Setting::loanEligibilityMonths(),
                    'max_borrow_multiplier' => Setting::loanMaxBorrowMultiplier(),
                    'default_grace_cycles' => Setting::loanDefaultGraceCycles(),
                    'auto_allocate_loan_repayment' => Setting::autoAllocateLoanRepaymentImportEnabled(),
                    'auto_allocate_loan_repayment_strict_default' => (bool) Setting::get('feature.auto_allocate_loan_repayment.strict_mode_default', false),
                    'auto_allocate_loan_repayment_allow_unapplied_credit' => (bool) Setting::get('feature.auto_allocate_loan_repayment.allow_unapplied_credit', true),
                    'auto_allocate_loan_repayment_idempotency_scope' => (string) Setting::get('feature.auto_allocate_loan_repayment.idempotency_scope', 'file_line_member_paid_at_total_paid'),
                    'mixed_import_allow_partial_installment_repayment' => Setting::mixedImportAllowPartialInstallmentRepayment(),
                    'import_loan_blank_portions_fifty_fifty' => Setting::importLoanBlankPortionsUseFiftyFiftySplit(),
                ])
                ->schema([
                    Section::make(__('Eligibility Rules'))->schema([
                        Forms\Components\TextInput::make('eligibility_months')
                            ->label(__('Membership duration before eligible (months)'))
                            ->numeric()->required()->minValue(1)->default(12),
                        Forms\Components\TextInput::make('min_fund_balance')
                            ->label(__('Minimum fund account balance (SAR)'))
                            ->numeric()->prefix('SAR')->required()->minValue(0)->default(6000),
                        Forms\Components\TextInput::make('max_borrow_multiplier')
                            ->label(__('Max loan = N × fund balance'))
                            ->numeric()->required()->minValue(1)->maxValue(10)->default(2),
                    ])->columns(3),
                    Section::make(__('Repayment Rules'))->schema([
                        Forms\Components\TextInput::make('settlement_threshold_pct')
                            ->label(__('Settlement Threshold (% of loan)'))
                            ->numeric()->suffix('%')->required()->minValue(0)->maxValue(100)->default(16)
                            ->helperText(__('Loan is settled when master fund is repaid AND member fund reaches this % of the loan amount.')),
                        Forms\Components\TextInput::make('default_grace_cycles')
                            ->label(__('Grace cycles before guarantor is debited'))
                            ->numeric()->required()->minValue(1)->default(2)
                            ->helperText(__('Missed cycles before warning period ends and guarantor becomes liable.')),
                    ])->columns(2),
                    Section::make(__('Import allocation rules'))->schema([
                        Forms\Components\Toggle::make('auto_allocate_loan_repayment')
                            ->label(__('Auto-allocate loan repayments during contribution import'))
                            ->helperText(__('When enabled, imported contribution amount is treated as total payment and allocated as repayment first, then contribution, then remainder policy.'))
                            ->default(false),
                        Forms\Components\Toggle::make('auto_allocate_loan_repayment_strict_default')
                            ->label(__('Strict mode default for auto-allocation'))
                            ->helperText(__('If enabled, rows fail when payment cannot fully satisfy required allocation or leaves remainder while unapplied credits are disallowed.'))
                            ->default(false),
                        Forms\Components\Toggle::make('auto_allocate_loan_repayment_allow_unapplied_credit')
                            ->label(__('Allow unapplied remainder as member cash credit'))
                            ->helperText(__('If disabled, any leftover after repayment and contribution allocation fails the row (or strict mode behavior).'))
                            ->default(true),
                        Forms\Components\Select::make('auto_allocate_loan_repayment_idempotency_scope')
                            ->label(__('Idempotency scope'))
                            ->options([
                                'file_line_member_paid_at_total_paid' => __('File + line + member + paid_at + total_paid'),
                                'member_paid_at_total_paid' => __('Member + paid_at + total_paid'),
                                'none' => __('Disabled'),
                            ])
                            ->default('file_line_member_paid_at_total_paid')
                            ->required(),
                        Forms\Components\Toggle::make('mixed_import_allow_partial_installment_repayment')
                            ->label(__('Mixed CSV: allow partial installment repayment'))
                            ->helperText(__('When off, each repayment row pays only whole installments in due order; amounts smaller than the next due installment remain as member cash only.'))
                            ->default(false),
                        Forms\Components\Toggle::make('import_loan_blank_portions_fifty_fifty')
                            ->label(__('Blank loan portions: use 50/50 split'))
                            ->helperText(__('When on, loan CSV or mixed negative rows with empty member/master portions split the principal equally. When off, the member portion follows current fund balance capped at principal (same as admin disbursement).'))
                            ->default(false),
                    ])->columns(1),
                ])
                ->action(function (array $data): void {
                    Setting::set('loan.settlement_threshold_pct', $data['settlement_threshold_pct'] / 100);
                    Setting::set('loan.min_fund_balance', $data['min_fund_balance']);
                    Setting::set('loan.eligibility_months', $data['eligibility_months']);
                    Setting::set('loan.max_borrow_multiplier', $data['max_borrow_multiplier']);
                    Setting::set('loan.default_grace_cycles', $data['default_grace_cycles']);
                    Setting::set('feature.auto_allocate_loan_repayment', ! empty($data['auto_allocate_loan_repayment']) ? '1' : '0');
                    Setting::set('feature.auto_allocate_loan_repayment.strict_mode_default', ! empty($data['auto_allocate_loan_repayment_strict_default']) ? '1' : '0');
                    Setting::set('feature.auto_allocate_loan_repayment.allow_unapplied_credit', ! empty($data['auto_allocate_loan_repayment_allow_unapplied_credit']) ? '1' : '0');
                    Setting::set('feature.auto_allocate_loan_repayment.idempotency_scope', (string) ($data['auto_allocate_loan_repayment_idempotency_scope'] ?? 'file_line_member_paid_at_total_paid'));
                    Setting::set(
                        'feature.contribution_import_mixed.allow_partial_installment_repayment',
                        ! empty($data['mixed_import_allow_partial_installment_repayment']) ? '1' : '0'
                    );
                    Setting::set(
                        'feature.import_loan.blank_portions_use_fifty_fifty_split',
                        ! empty($data['import_loan_blank_portions_fifty_fifty']) ? '1' : '0'
                    );

                    Notification::make()->title(__('Loan settings saved.'))->success()->send();
                }),
            Action::make('save_import_allocation_settings')
                ->label(__('Import allocation settings'))
                ->icon('heroicon-o-adjustments-horizontal')
                ->color('gray')
                ->visible(fn (): bool => $this->activeTab === 'loans' && $this->loanSubTab === 'loan-rules')
                ->fillForm([
                    'auto_allocate_loan_repayment' => Setting::autoAllocateLoanRepaymentImportEnabled(),
                    'auto_allocate_loan_repayment_strict_default' => (bool) Setting::get('feature.auto_allocate_loan_repayment.strict_mode_default', false),
                    'auto_allocate_loan_repayment_allow_unapplied_credit' => (bool) Setting::get('feature.auto_allocate_loan_repayment.allow_unapplied_credit', true),
                    'auto_allocate_loan_repayment_idempotency_scope' => (string) Setting::get('feature.auto_allocate_loan_repayment.idempotency_scope', 'file_line_member_paid_at_total_paid'),
                    'mixed_import_allow_partial_installment_repayment' => Setting::mixedImportAllowPartialInstallmentRepayment(),
                    'import_loan_blank_portions_fifty_fifty' => Setting::importLoanBlankPortionsUseFiftyFiftySplit(),
                ])
                ->schema([
                    Section::make(__('Contribution import auto-allocation'))
                        ->description(__('Controls how contribution import automatically allocates one payment amount between loan repayments and contribution records.'))
                        ->schema([
                            Forms\Components\Toggle::make('auto_allocate_loan_repayment')
                                ->label(__('Enable auto-allocation'))
                                ->helperText(__('Treat imported amount as total payment and allocate: repayment due first, then contribution, then remainder policy.'))
                                ->default(false),
                            Forms\Components\Toggle::make('auto_allocate_loan_repayment_strict_default')
                                ->label(__('Strict mode default'))
                                ->helperText(__('Fail rows that cannot fully satisfy required allocation steps under current policy.'))
                                ->default(false),
                            Forms\Components\Toggle::make('auto_allocate_loan_repayment_allow_unapplied_credit')
                                ->label(__('Allow unapplied remainder as member cash credit'))
                                ->helperText(__('If disabled, leftover amount after allocations causes row failure (or strict-mode behavior).'))
                                ->default(true),
                            Forms\Components\Select::make('auto_allocate_loan_repayment_idempotency_scope')
                                ->label(__('Idempotency scope'))
                                ->options([
                                    'file_line_member_paid_at_total_paid' => __('File + line + member + paid_at + total_paid'),
                                    'member_paid_at_total_paid' => __('Member + paid_at + total_paid'),
                                    'none' => __('Disabled'),
                                ])
                                ->default('file_line_member_paid_at_total_paid')
                                ->required(),
                            Forms\Components\Toggle::make('mixed_import_allow_partial_installment_repayment')
                                ->label(__('Mixed CSV: allow partial installment repayment'))
                                ->helperText(__('When off, each repayment row pays only whole installments in due order; amounts smaller than the next due installment remain as member cash only.'))
                                ->default(false),
                            Forms\Components\Toggle::make('import_loan_blank_portions_fifty_fifty')
                                ->label(__('Blank loan portions: use 50/50 split'))
                                ->helperText(__('Applies to loan CSV rows and mixed disbursement rows with empty portions. Off = derive member slice from fund balance (capped at principal).'))
                                ->default(false),
                        ]),
                ])
                ->action(function (array $data): void {
                    Setting::set('feature.auto_allocate_loan_repayment', ! empty($data['auto_allocate_loan_repayment']) ? '1' : '0');
                    Setting::set('feature.auto_allocate_loan_repayment.strict_mode_default', ! empty($data['auto_allocate_loan_repayment_strict_default']) ? '1' : '0');
                    Setting::set('feature.auto_allocate_loan_repayment.allow_unapplied_credit', ! empty($data['auto_allocate_loan_repayment_allow_unapplied_credit']) ? '1' : '0');
                    Setting::set('feature.auto_allocate_loan_repayment.idempotency_scope', (string) ($data['auto_allocate_loan_repayment_idempotency_scope'] ?? 'file_line_member_paid_at_total_paid'));
                    Setting::set(
                        'feature.contribution_import_mixed.allow_partial_installment_repayment',
                        ! empty($data['mixed_import_allow_partial_installment_repayment']) ? '1' : '0'
                    );
                    Setting::set(
                        'feature.import_loan.blank_portions_use_fifty_fifty_split',
                        ! empty($data['import_loan_blank_portions_fifty_fifty']) ? '1' : '0'
                    );

                    Notification::make()->title(__('Import allocation settings saved.'))->success()->send();
                }),
            Action::make('save_cycle_settings')
                ->label(__('Save cycle settings'))
                ->icon('heroicon-o-check')
                ->color('primary')
                ->visible(fn (): bool => $this->activeTab === 'contribution-cycles')
                ->fillForm([
                    'cycle_start_day' => Setting::contributionCycleStartDay(),
                    'delinquency_consecutive' => Setting::delinquencyConsecutiveMissThreshold(),
                    'delinquency_total' => Setting::delinquencyTotalMissThreshold(),
                    'delinquency_lookback_months' => Setting::delinquencyTotalMissLookbackMonths(),
                    'late_fee_contribution_1d' => Setting::lateFeeContributionTier(1),
                    'late_fee_contribution_10d' => Setting::lateFeeContributionTier(10),
                    'late_fee_contribution_20d' => Setting::lateFeeContributionTier(20),
                    'late_fee_contribution_30d' => Setting::lateFeeContributionTier(30),
                    'late_fee_repayment_1d' => Setting::lateFeeRepaymentTier(1),
                    'late_fee_repayment_10d' => Setting::lateFeeRepaymentTier(10),
                    'late_fee_repayment_20d' => Setting::lateFeeRepaymentTier(20),
                    'late_fee_repayment_30d' => Setting::lateFeeRepaymentTier(30),
                    'annual_subscription_fee' => Setting::annualSubscriptionFee(),
                ])
                ->schema([
                    Section::make(__('Cycle boundaries'))
                        ->description(
                            __('Each cycle is named by a calendar month and starts on the chosen day, ending the day before the same day next month.')
                        )
                        ->schema([
                            Forms\Components\TextInput::make('cycle_start_day')
                                ->label(__('Cycle start day (day of month)'))
                                ->numeric()
                                ->required()
                                ->minValue(1)
                                ->maxValue(28)
                                ->default(6)
                                ->helperText(__('Example: 6 means June cycle runs 6 Jun through 5 Jul. Limited to 28 for February consistency.')),
                        ]),
                    Section::make(__('Delinquency policy'))
                        ->description(
                            __('Daily job `fund:check-delinquency` evaluates missed monthly contributions (when due) and unpaid loan installments for active loans. ').
                            __('Breaching either threshold suspends the member (member portal blocked) and shifts active loan repayment collection to the guarantor until restored.')
                        )
                        ->schema([
                            Forms\Components\TextInput::make('delinquency_consecutive')
                                ->label(__('Consecutive missed cycles'))
                                ->numeric()
                                ->required()
                                ->minValue(1)
                                ->maxValue(36)
                                ->default(3)
                                ->helperText(__('Trailing streak of closed months where any required contribution or repayment was still missed.')),
                            Forms\Components\TextInput::make('delinquency_total')
                                ->label(__('Total misses (rolling window)'))
                                ->numeric()
                                ->required()
                                ->minValue(1)
                                ->maxValue(240)
                                ->default(15)
                                ->helperText(__('Count of missed months within the lookback window below (spread-out misses).')),
                            Forms\Components\TextInput::make('delinquency_lookback_months')
                                ->label(__('Rolling window (months)'))
                                ->numeric()
                                ->required()
                                ->minValue(1)
                                ->maxValue(240)
                                ->default(60)
                                ->helperText(__('How far back to count toward the total-miss threshold.')),
                        ])->columns(3),
                    Section::make(__('Late fees (tiered by days after due)'))
                        ->description(
                            __('Due is the end of the contribution/repayment cycle for that month. Calendar days after that are counted; ').
                            __('the highest tier reached (30+ ≥ 20+ ≥ 10+ ≥ 1+) with a non-zero SAR amount applies — if a tier is 0, the next lower tier is used. ').
                            __('Cash-account debits bundle principal and late fee; late fees credit master cash only (not master fund).')
                        )
                        ->schema([
                            Forms\Components\TextInput::make('late_fee_contribution_1d')
                                ->label(__('Contribution — 1+ days late (SAR)'))
                                ->numeric()->prefix('SAR')->required()->minValue(0)->default(0),
                            Forms\Components\TextInput::make('late_fee_contribution_10d')
                                ->label(__('Contribution — 10+ days late (SAR)'))
                                ->numeric()->prefix('SAR')->required()->minValue(0)->default(0),
                            Forms\Components\TextInput::make('late_fee_contribution_20d')
                                ->label(__('Contribution — 20+ days late (SAR)'))
                                ->numeric()->prefix('SAR')->required()->minValue(0)->default(0),
                            Forms\Components\TextInput::make('late_fee_contribution_30d')
                                ->label(__('Contribution — 30+ days late (SAR)'))
                                ->numeric()->prefix('SAR')->required()->minValue(0)->default(0),
                            Forms\Components\TextInput::make('late_fee_repayment_1d')
                                ->label(__('Repayment — 1+ days late (SAR)'))
                                ->numeric()->prefix('SAR')->required()->minValue(0)->default(0),
                            Forms\Components\TextInput::make('late_fee_repayment_10d')
                                ->label(__('Repayment — 10+ days late (SAR)'))
                                ->numeric()->prefix('SAR')->required()->minValue(0)->default(0),
                            Forms\Components\TextInput::make('late_fee_repayment_20d')
                                ->label(__('Repayment — 20+ days late (SAR)'))
                                ->numeric()->prefix('SAR')->required()->minValue(0)->default(0),
                            Forms\Components\TextInput::make('late_fee_repayment_30d')
                                ->label(__('Repayment — 30+ days late (SAR)'))
                                ->numeric()->prefix('SAR')->required()->minValue(0)->default(0),
                        ])->columns(3),
                    Section::make(__('Annual Subscription Fee'))
                        ->description(
                            __('Charged once per year on each active member\'s join-date anniversary. Set to 0 to disable automatic anniversary charging.')
                        )
                        ->schema([
                            Forms\Components\TextInput::make('annual_subscription_fee')
                                ->label(__('Annual subscription fee (SAR)'))
                                ->numeric()
                                ->prefix('SAR')
                                ->required()
                                ->minValue(0)
                                ->default(0)
                                ->helperText(__('Credit goes to master cash (like late fees and membership fees). The daily scheduler charges eligible members automatically on their anniversary.')),
                        ]),
                ])
                ->action(function (array $data): void {
                    Setting::set('contribution.cycle_start_day', max(1, min(28, (int) $data['cycle_start_day'])));
                    Setting::set('subscription.annual_fee', max(0, (float) $data['annual_subscription_fee']));
                    Setting::set('delinquency.consecutive_miss_threshold', max(1, min(36, (int) $data['delinquency_consecutive'])));
                    Setting::set('delinquency.total_miss_threshold', max(1, min(240, (int) $data['delinquency_total'])));
                    Setting::set('delinquency.total_miss_lookback_months', max(1, min(240, (int) $data['delinquency_lookback_months'])));
                    Setting::set('late_fee.contribution_day_1', max(0, (float) $data['late_fee_contribution_1d']));
                    Setting::set('late_fee.contribution_day_10', max(0, (float) $data['late_fee_contribution_10d']));
                    Setting::set('late_fee.contribution_day_20', max(0, (float) $data['late_fee_contribution_20d']));
                    Setting::set('late_fee.contribution_day_30', max(0, (float) $data['late_fee_contribution_30d']));
                    Setting::set('late_fee.repayment_day_1', max(0, (float) $data['late_fee_repayment_1d']));
                    Setting::set('late_fee.repayment_day_10', max(0, (float) $data['late_fee_repayment_10d']));
                    Setting::set('late_fee.repayment_day_20', max(0, (float) $data['late_fee_repayment_20d']));
                    Setting::set('late_fee.repayment_day_30', max(0, (float) $data['late_fee_repayment_30d']));

                    Notification::make()
                        ->title(__('Cycle settings saved'))
                        ->success()
                        ->send();
                }),
            Action::make('save_statement_settings')
                ->label(__('Save statement settings'))
                ->icon('heroicon-o-check')
                ->color('primary')
                ->visible(fn (): bool => $this->activeTab === 'statements')
                ->fillForm(fn () => [
                    'brand_name' => Setting::statementBrandName(),
                    'tagline' => Setting::statementTagline(),
                    'accent_color' => Setting::statementAccentColor(),
                    'footer_disclaimer' => Setting::statementFooterDisclaimer(),
                    'signature_line' => Setting::statementSignatureLine(),
                    'auto_email' => Setting::statementAutoEmail(),
                    'include_transactions' => Setting::statementIncludeTransactions(),
                    'include_loan_section' => Setting::statementIncludeLoanSection(),
                    'include_compliance' => Setting::statementIncludeCompliance(),
                ])
                ->schema([
                    Section::make(__('Branding'))
                        ->description(__('These values appear on every generated PDF statement.'))
                        ->schema([
                            Forms\Components\TextInput::make('brand_name')
                                ->label(__('Organization Name'))
                                ->required()
                                ->maxLength(80)
                                ->helperText(__('Printed at the top of every statement.')),
                            Forms\Components\TextInput::make('tagline')
                                ->label(__('Tagline / Sub-brand'))
                                ->maxLength(120)
                                ->helperText(__('Small text under the organization name.')),
                            Forms\Components\TextInput::make('accent_color')
                                ->label(__('Header Accent Color (hex)'))
                                ->required()
                                ->maxLength(7)
                                ->placeholder('#059669')
                                ->helperText(__('Must be a valid 6-digit hex code, e.g. #059669 (green), #1d4ed8 (blue), #7c3aed (purple).')),
                        ])->columns(3),
                    Section::make(__('Footer & Signature'))
                        ->schema([
                            Forms\Components\Textarea::make('footer_disclaimer')
                                ->label(__('Footer Disclaimer'))
                                ->rows(2)
                                ->columnSpanFull()
                                ->helperText(__('Printed at the bottom of every PDF, e.g. "Confidential — for named member only."')),
                            Forms\Components\TextInput::make('signature_line')
                                ->label(__('Authorized Signature Line'))
                                ->maxLength(100)
                                ->helperText(__('Appears in the signature block of the PDF.')),
                        ]),
                    Section::make(__('Delivery & Content'))
                        ->description(__('Control what is included in each generated PDF and how members are notified.'))
                        ->schema([
                            Forms\Components\Toggle::make('auto_email')
                                ->label(__('Auto-email members on generation'))
                                ->helperText(__('When enabled, each member receives an email with the statement PDF attached when statements are generated.')),
                            Forms\Components\Toggle::make('include_transactions')
                                ->label(__('Include account transaction detail table'))
                                ->helperText(__('Shows every credit/debit on the member\'s accounts for the period.')),
                            Forms\Components\Toggle::make('include_loan_section')
                                ->label(__('Include loan standing section'))
                                ->helperText(__('Shows active loan balance, installment progress, and overdue alerts.')),
                            Forms\Components\Toggle::make('include_compliance')
                                ->label(__('Include compliance snapshot'))
                                ->helperText(__('Shows compliance score, late contribution/repayment counts.')),
                        ])->columns(2),
                ])
                ->action(function (array $data): void {
                    Setting::set('statement.brand_name', trim($data['brand_name']));
                    Setting::set('statement.tagline', trim($data['tagline'] ?? ''));
                    // Validate hex before storing
                    $color = trim($data['accent_color']);
                    if (preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
                        Setting::set('statement.accent_color', $color);
                    }
                    Setting::set('statement.footer_disclaimer', trim($data['footer_disclaimer'] ?? ''));
                    Setting::set('statement.signature_line', trim($data['signature_line'] ?? ''));
                    Setting::set('statement.auto_email', $data['auto_email'] ? '1' : '0');
                    Setting::set('statement.include_transactions', $data['include_transactions'] ? '1' : '0');
                    Setting::set('statement.include_loan_section', $data['include_loan_section'] ? '1' : '0');
                    Setting::set('statement.include_compliance', $data['include_compliance'] ? '1' : '0');

                    Notification::make()
                        ->title(__('Statement settings saved'))
                        ->success()
                        ->send();
                }),

            Action::make('save_public_membership_settings')
                ->label(__('Save public membership settings'))
                ->icon('heroicon-o-check')
                ->color('primary')
                ->visible(fn (): bool => $this->activeTab === 'public-membership')
                ->fillForm([
                    'max_pending_public' => Setting::maxPublicApplications(),
                    'membership_application_fee_new' => Setting::membershipApplicationFeeForType('new'),
                    'membership_application_fee_resume' => Setting::membershipApplicationFeeForType('resume'),
                    'membership_application_fee_renew' => Setting::membershipApplicationFeeForType('renew'),
                    'membership_application_fee_bank_instructions' => Setting::membershipApplicationFeeBankInstructions(),
                ])
                ->schema([
                    Section::make(__('Application capacity'))
                        ->description(__('Controls the public application page at /apply. Existing member login is unchanged.'))
                        ->schema([
                            Forms\Components\TextInput::make('max_pending_public')
                                ->label(__('Maximum applications (public apply)'))
                                ->numeric()
                                ->minValue(0)
                                ->required()
                                ->default(0)
                                ->helperText(__('Counts all application rows. Use 0 for no limit.')),
                        ]),
                    Section::make(__('Membership application fees'))
                        ->description(
                            __('Set a separate fee for each application type. When at least one fee is greater than zero, /apply adds a final payment step (after identity, employment, and document upload): applicants transfer to your bank and submit a reference for the fee that matches their chosen type, then submit the application. ').
                            __('On successful submission, that amount is credited to the master cash account only (not the master fund). Reconcile with your bank to avoid double-counting if you also import the same deposit.')
                        )
                        ->schema([
                            Forms\Components\TextInput::make('membership_application_fee_new')
                                ->label(__('New membership (SAR)'))
                                ->numeric()
                                ->minValue(0)
                                ->required()
                                ->default(0),
                            Forms\Components\TextInput::make('membership_application_fee_resume')
                                ->label(__('Resume membership (SAR)'))
                                ->numeric()
                                ->minValue(0)
                                ->required()
                                ->default(0),
                            Forms\Components\TextInput::make('membership_application_fee_renew')
                                ->label(__('Renew membership (SAR)'))
                                ->numeric()
                                ->minValue(0)
                                ->required()
                                ->default(0),
                            Forms\Components\Textarea::make('membership_application_fee_bank_instructions')
                                ->label(__('Bank transfer instructions'))
                                ->rows(6)
                                ->columnSpanFull()
                                ->helperText(__('Shown on the application form (plain text; line breaks preserved). Include IBAN, account name, and bank name.')),
                        ])
                        ->columns(3),
                ])
                ->action(function (array $data): void {
                    $feeNew = max(0, (float) ($data['membership_application_fee_new'] ?? 0));
                    $feeResume = max(0, (float) ($data['membership_application_fee_resume'] ?? 0));
                    $feeRenew = max(0, (float) ($data['membership_application_fee_renew'] ?? 0));

                    Setting::set('membership.max_pending_public', max(0, (int) $data['max_pending_public']));
                    Setting::set('membership.application_fee_new', $feeNew);
                    Setting::set('membership.application_fee_resume', $feeResume);
                    Setting::set('membership.application_fee_renew', $feeRenew);
                    Setting::set('membership.application_fee_bank_instructions', (string) ($data['membership_application_fee_bank_instructions'] ?? ''));
                    Setting::set('membership.application_fee_amount', max($feeNew, $feeResume, $feeRenew));

                    Notification::make()
                        ->title(__('Public membership settings saved'))
                        ->success()
                        ->send();
                }),

            Action::make('save_communication_settings')
                ->label(__('Save communication settings'))
                ->icon('heroicon-o-check')
                ->color('primary')
                ->visible(fn (): bool => $this->activeTab === 'communication')
                ->fillForm(fn () => [
                    'channel_in_app' => Setting::commChannelEnabled('in_app'),
                    'channel_email' => Setting::commChannelEnabled('email'),
                    'channel_sms' => Setting::commChannelEnabled('sms'),
                    'channel_whatsapp' => Setting::commChannelEnabled('whatsapp'),
                ])
                ->schema([
                    Section::make(__('Communication Channels'))
                        ->description(
                            __('Enable or disable each outbound communication channel system-wide. ').
                            __('When a channel is disabled, no notifications of any type will be sent through it, ').
                            __('regardless of individual member preferences.')
                        )
                        ->schema([
                            Forms\Components\Toggle::make('channel_in_app')
                                ->label(__('In-App Inbox'))
                                ->helperText(__('Shows notifications inside the member portal. Disabling this silences all in-app alerts — use with caution.'))
                                ->onColor('success')
                                ->offColor('danger'),
                            Forms\Components\Toggle::make('channel_email')
                                ->label(__('Email'))
                                ->helperText(__('Sends emails via the configured SMTP/mail driver (MAIL_* environment variables).'))
                                ->onColor('success')
                                ->offColor('danger'),
                            Forms\Components\Toggle::make('channel_sms')
                                ->label(__('SMS'))
                                ->helperText(__('Sends SMS messages via Twilio (TWILIO_* environment variables must be set).'))
                                ->onColor('success')
                                ->offColor('danger'),
                            Forms\Components\Toggle::make('channel_whatsapp')
                                ->label(__('WhatsApp'))
                                ->helperText(__('Sends WhatsApp messages via Twilio. Requires a verified WhatsApp sender number.'))
                                ->onColor('success')
                                ->offColor('danger'),
                        ])
                        ->columns(2),
                ])
                ->action(function (array $data): void {
                    Setting::setCommChannel('in_app', (bool) $data['channel_in_app']);
                    Setting::setCommChannel('email', (bool) $data['channel_email']);
                    Setting::setCommChannel('sms', (bool) $data['channel_sms']);
                    Setting::setCommChannel('whatsapp', (bool) $data['channel_whatsapp']);

                    Notification::make()
                        ->title(__('Communication settings saved'))
                        ->success()
                        ->send();
                }),
            Action::make('save_email_templates')
                ->label(__('Configure member email templates'))
                ->icon('heroicon-o-envelope')
                ->color('gray')
                ->visible(fn (): bool => $this->activeTab === 'communication')
                ->fillForm([
                    'membership_approved_subject_en' => EmailTemplateService::get('membership_approved', 'subject', 'en', 'Welcome to FundFlow — Membership Approved!'),
                    'membership_approved_subject_ar' => EmailTemplateService::get('membership_approved', 'subject', 'ar', 'مرحبًا بك في FundFlow — تمت الموافقة على العضوية!'),
                    'membership_approved_greeting_en' => EmailTemplateService::get('membership_approved', 'greeting', 'en', 'Dear :name,'),
                    'membership_approved_greeting_ar' => EmailTemplateService::get('membership_approved', 'greeting', 'ar', 'عزيزي/عزيزتي :name،'),
                    'membership_approved_body_en' => EmailTemplateService::get('membership_approved', 'body', 'en', "Congratulations! Your membership application has been **approved**.\nYour member number is: **:number**\nYou can now log in to your member portal to:\n• View your contribution history\n• Apply for interest-free loans\n• Download monthly statements"),
                    'membership_approved_body_ar' => EmailTemplateService::get('membership_approved', 'body', 'ar', "تهانينا! تمت **الموافقة** على طلب عضويتك.\nرقم عضويتك هو: **:number**\nيمكنك الآن تسجيل الدخول إلى بوابة الأعضاء من أجل:\n• عرض سجل المساهمات\n• التقديم على القروض الحسنة\n• تنزيل الكشوفات الشهرية"),
                    'membership_approved_action_en' => EmailTemplateService::get('membership_approved', 'action_label', 'en', 'Sign In to Member Portal'),
                    'membership_approved_action_ar' => EmailTemplateService::get('membership_approved', 'action_label', 'ar', 'تسجيل الدخول إلى بوابة الأعضاء'),
                    'membership_approved_closing_en' => EmailTemplateService::get('membership_approved', 'closing', 'en', 'Welcome to the family!'),
                    'membership_approved_closing_ar' => EmailTemplateService::get('membership_approved', 'closing', 'ar', 'مرحبًا بك ضمن أسرة الصندوق!'),

                    'membership_rejected_subject_en' => EmailTemplateService::get('membership_rejected', 'subject', 'en', 'FundFlow — Membership Application Update'),
                    'membership_rejected_subject_ar' => EmailTemplateService::get('membership_rejected', 'subject', 'ar', 'FundFlow — تحديث طلب العضوية'),
                    'membership_rejected_greeting_en' => EmailTemplateService::get('membership_rejected', 'greeting', 'en', 'Dear :name,'),
                    'membership_rejected_greeting_ar' => EmailTemplateService::get('membership_rejected', 'greeting', 'ar', 'عزيزي/عزيزتي :name،'),
                    'membership_rejected_body_en' => EmailTemplateService::get('membership_rejected', 'body', 'en', "Thank you for your interest in joining FundFlow.\nAfter careful review, we regret to inform you that your membership application could not be approved at this time."),
                    'membership_rejected_body_ar' => EmailTemplateService::get('membership_rejected', 'body', 'ar', "شكرًا لاهتمامك بالانضمام إلى FundFlow.\nبعد مراجعة دقيقة، نأسف لإبلاغك بأنه تعذر الموافقة على طلب العضوية في الوقت الحالي."),
                    'membership_rejected_reason_line_en' => EmailTemplateService::get('membership_rejected', 'reason_line', 'en', '**Reason:** :reason'),
                    'membership_rejected_reason_line_ar' => EmailTemplateService::get('membership_rejected', 'reason_line', 'ar', '**السبب:** :reason'),
                    'membership_rejected_closing_en' => EmailTemplateService::get('membership_rejected', 'closing', 'en', 'If you believe this decision was made in error or have any questions, please contact us at admin@fundflow.sa.'),
                    'membership_rejected_closing_ar' => EmailTemplateService::get('membership_rejected', 'closing', 'ar', 'إذا كنت تعتقد أن هذا القرار تم بالخطأ أو كانت لديك أي أسئلة، يرجى التواصل معنا عبر admin@fundflow.sa.'),
                    'membership_rejected_action_en' => EmailTemplateService::get('membership_rejected', 'action_label', 'en', 'Contact Us'),
                    'membership_rejected_action_ar' => EmailTemplateService::get('membership_rejected', 'action_label', 'ar', 'تواصل معنا'),

                    'loan_approved_subject_en' => EmailTemplateService::get('loan_approved', 'subject', 'en', 'FundFlow — Loan Approved!'),
                    'loan_approved_subject_ar' => EmailTemplateService::get('loan_approved', 'subject', 'ar', 'FundFlow — تمت الموافقة على القرض!'),
                    'loan_approved_greeting_en' => EmailTemplateService::get('loan_approved', 'greeting', 'en', 'Dear :name,'),
                    'loan_approved_greeting_ar' => EmailTemplateService::get('loan_approved', 'greeting', 'ar', 'عزيزي/عزيزتي :name،'),
                    'loan_approved_body_en' => EmailTemplateService::get('loan_approved', 'body', 'en', "Your loan application for **:amount** has been approved.\nRepayment Details:\n• Amount: :amount\n• Installments: :count monthly payments\n• Final due date: :date"),
                    'loan_approved_body_ar' => EmailTemplateService::get('loan_approved', 'body', 'ar', "تمت الموافقة على طلب قرضك بمبلغ **:amount**.\nتفاصيل السداد:\n• المبلغ: :amount\n• الأقساط: :count دفعات شهرية\n• تاريخ الاستحقاق النهائي: :date"),
                    'loan_approved_action_en' => EmailTemplateService::get('loan_approved', 'action_label', 'en', 'View Loan Details'),
                    'loan_approved_action_ar' => EmailTemplateService::get('loan_approved', 'action_label', 'ar', 'عرض تفاصيل القرض'),
                    'signature_line_en' => EmailTemplateService::get('global', 'signature_line', 'en', config('app.name', 'FundFlow')),
                    'signature_line_ar' => EmailTemplateService::get('global', 'signature_line', 'ar', config('app.name', 'FundFlow')),
                    'email_template_overrides_en' => $this->emailTemplateOverrides('en'),
                    'email_template_overrides_ar' => $this->emailTemplateOverrides('ar'),
                ])
                ->schema([
                    Section::make(__('Membership approved email'))
                        ->description(__('Available placeholders: :name, :number'))
                        ->schema([
                            Forms\Components\TextInput::make('membership_approved_subject_en')->label(__('Subject (EN)'))->required(),
                            Forms\Components\TextInput::make('membership_approved_subject_ar')->label(__('Subject (AR)'))->required(),
                            Forms\Components\TextInput::make('membership_approved_greeting_en')->label(__('Greeting (EN)'))->required(),
                            Forms\Components\TextInput::make('membership_approved_greeting_ar')->label(__('Greeting (AR)'))->required(),
                            Forms\Components\Textarea::make('membership_approved_body_en')->label(__('Body (EN, one line per paragraph)'))->rows(6)->required()->columnSpanFull(),
                            Forms\Components\Textarea::make('membership_approved_body_ar')->label(__('Body (AR, one line per paragraph)'))->rows(6)->required()->columnSpanFull(),
                            Forms\Components\TextInput::make('membership_approved_action_en')->label(__('Action button label (EN)'))->required(),
                            Forms\Components\TextInput::make('membership_approved_action_ar')->label(__('Action button label (AR)'))->required(),
                            Forms\Components\TextInput::make('membership_approved_closing_en')->label(__('Closing line (EN)'))->required(),
                            Forms\Components\TextInput::make('membership_approved_closing_ar')->label(__('Closing line (AR)'))->required(),
                        ])->columns(2),
                    Section::make(__('Membership rejected email'))
                        ->description(__('Available placeholders: :name, :reason'))
                        ->schema([
                            Forms\Components\TextInput::make('membership_rejected_subject_en')->label(__('Subject (EN)'))->required(),
                            Forms\Components\TextInput::make('membership_rejected_subject_ar')->label(__('Subject (AR)'))->required(),
                            Forms\Components\TextInput::make('membership_rejected_greeting_en')->label(__('Greeting (EN)'))->required(),
                            Forms\Components\TextInput::make('membership_rejected_greeting_ar')->label(__('Greeting (AR)'))->required(),
                            Forms\Components\Textarea::make('membership_rejected_body_en')->label(__('Body (EN, one line per paragraph)'))->rows(4)->required()->columnSpanFull(),
                            Forms\Components\Textarea::make('membership_rejected_body_ar')->label(__('Body (AR, one line per paragraph)'))->rows(4)->required()->columnSpanFull(),
                            Forms\Components\TextInput::make('membership_rejected_reason_line_en')->label(__('Reason line (EN)'))->required(),
                            Forms\Components\TextInput::make('membership_rejected_reason_line_ar')->label(__('Reason line (AR)'))->required(),
                            Forms\Components\TextInput::make('membership_rejected_closing_en')->label(__('Closing line (EN)'))->required(),
                            Forms\Components\TextInput::make('membership_rejected_closing_ar')->label(__('Closing line (AR)'))->required(),
                            Forms\Components\TextInput::make('membership_rejected_action_en')->label(__('Action button label (EN)'))->required(),
                            Forms\Components\TextInput::make('membership_rejected_action_ar')->label(__('Action button label (AR)'))->required(),
                        ])->columns(2),
                    Section::make(__('Loan approved email'))
                        ->description(__('Available placeholders: :name, :amount, :count, :date'))
                        ->schema([
                            Forms\Components\TextInput::make('loan_approved_subject_en')->label(__('Subject (EN)'))->required(),
                            Forms\Components\TextInput::make('loan_approved_subject_ar')->label(__('Subject (AR)'))->required(),
                            Forms\Components\TextInput::make('loan_approved_greeting_en')->label(__('Greeting (EN)'))->required(),
                            Forms\Components\TextInput::make('loan_approved_greeting_ar')->label(__('Greeting (AR)'))->required(),
                            Forms\Components\Textarea::make('loan_approved_body_en')->label(__('Body (EN, one line per paragraph)'))->rows(5)->required()->columnSpanFull(),
                            Forms\Components\Textarea::make('loan_approved_body_ar')->label(__('Body (AR, one line per paragraph)'))->rows(5)->required()->columnSpanFull(),
                            Forms\Components\TextInput::make('loan_approved_action_en')->label(__('Action button label (EN)'))->required(),
                            Forms\Components\TextInput::make('loan_approved_action_ar')->label(__('Action button label (AR)'))->required(),
                        ])->columns(2),
                    Section::make(__('Advanced template overrides (all member emails)'))
                        ->description(__('Use keys in the format template_key.field (for example: contribution_due.subject). Values here are saved as email templates and can override any email that supports the template service.'))
                        ->schema([
                            Forms\Components\TextInput::make('signature_line_en')
                                ->label(__('Line after "Regards," (EN)'))
                                ->helperText(__('Global email signature line used after the "Regards," keyword when no custom salutation is set. Current fallback: :fallback', ['fallback' => config('app.name', 'FundFlow')]))
                                ->required(),
                            Forms\Components\TextInput::make('signature_line_ar')
                                ->label(__('Line after "Regards," (AR)'))
                                ->helperText(__('Global email signature line used after "مع التحية،" when no custom salutation is set. Current fallback: :fallback', ['fallback' => config('app.name', 'FundFlow')]))
                                ->required(),
                            Forms\Components\Placeholder::make('email_template_helper')
                                ->label(__('Quick helper'))
                                ->content(__(
                                    "Common keys:\n".
                                    "• contribution_due.subject\n".
                                    "• contribution_due.body\n".
                                    "• loan_repayment_due.subject\n".
                                    "• loan_repayment_due.insufficient_line\n".
                                    "• loan_disbursed.subject\n".
                                    "• monthly_statement.closing\n".
                                    "• global.signature_line\n\n".
                                    "Common placeholders:\n".
                                    "• :name, :amount, :period, :date\n".
                                    '• :count, :balance, :number, :reason'
                                ))
                                ->columnSpanFull(),
                            Forms\Components\KeyValue::make('email_template_overrides_en')
                                ->label(__('Overrides (EN)'))
                                ->keyLabel(__('Template key.field'))
                                ->valueLabel(__('Template text'))
                                ->columnSpanFull(),
                            Forms\Components\KeyValue::make('email_template_overrides_ar')
                                ->label(__('Overrides (AR)'))
                                ->keyLabel(__('Template key.field'))
                                ->valueLabel(__('Template text'))
                                ->columnSpanFull(),
                        ]),
                ])
                ->modalWidth('7xl')
                ->action(function (array $data): void {
                    $pairs = [
                        'email_template.membership_approved.subject.en' => $data['membership_approved_subject_en'],
                        'email_template.membership_approved.subject.ar' => $data['membership_approved_subject_ar'],
                        'email_template.membership_approved.greeting.en' => $data['membership_approved_greeting_en'],
                        'email_template.membership_approved.greeting.ar' => $data['membership_approved_greeting_ar'],
                        'email_template.membership_approved.body.en' => $data['membership_approved_body_en'],
                        'email_template.membership_approved.body.ar' => $data['membership_approved_body_ar'],
                        'email_template.membership_approved.action_label.en' => $data['membership_approved_action_en'],
                        'email_template.membership_approved.action_label.ar' => $data['membership_approved_action_ar'],
                        'email_template.membership_approved.closing.en' => $data['membership_approved_closing_en'],
                        'email_template.membership_approved.closing.ar' => $data['membership_approved_closing_ar'],

                        'email_template.membership_rejected.subject.en' => $data['membership_rejected_subject_en'],
                        'email_template.membership_rejected.subject.ar' => $data['membership_rejected_subject_ar'],
                        'email_template.membership_rejected.greeting.en' => $data['membership_rejected_greeting_en'],
                        'email_template.membership_rejected.greeting.ar' => $data['membership_rejected_greeting_ar'],
                        'email_template.membership_rejected.body.en' => $data['membership_rejected_body_en'],
                        'email_template.membership_rejected.body.ar' => $data['membership_rejected_body_ar'],
                        'email_template.membership_rejected.reason_line.en' => $data['membership_rejected_reason_line_en'],
                        'email_template.membership_rejected.reason_line.ar' => $data['membership_rejected_reason_line_ar'],
                        'email_template.membership_rejected.closing.en' => $data['membership_rejected_closing_en'],
                        'email_template.membership_rejected.closing.ar' => $data['membership_rejected_closing_ar'],
                        'email_template.membership_rejected.action_label.en' => $data['membership_rejected_action_en'],
                        'email_template.membership_rejected.action_label.ar' => $data['membership_rejected_action_ar'],

                        'email_template.loan_approved.subject.en' => $data['loan_approved_subject_en'],
                        'email_template.loan_approved.subject.ar' => $data['loan_approved_subject_ar'],
                        'email_template.loan_approved.greeting.en' => $data['loan_approved_greeting_en'],
                        'email_template.loan_approved.greeting.ar' => $data['loan_approved_greeting_ar'],
                        'email_template.loan_approved.body.en' => $data['loan_approved_body_en'],
                        'email_template.loan_approved.body.ar' => $data['loan_approved_body_ar'],
                        'email_template.loan_approved.action_label.en' => $data['loan_approved_action_en'],
                        'email_template.loan_approved.action_label.ar' => $data['loan_approved_action_ar'],
                        'email_template.global.signature_line.en' => $data['signature_line_en'],
                        'email_template.global.signature_line.ar' => $data['signature_line_ar'],
                    ];

                    foreach ($pairs as $key => $value) {
                        Setting::set($key, trim((string) $value));
                    }

                    foreach (($data['email_template_overrides_en'] ?? []) as $k => $v) {
                        $k = trim((string) $k);
                        if ($k === '') {
                            continue;
                        }
                        Setting::set("email_template.{$k}.en", trim((string) $v));
                    }

                    foreach (($data['email_template_overrides_ar'] ?? []) as $k => $v) {
                        $k = trim((string) $k);
                        if ($k === '') {
                            continue;
                        }
                        Setting::set("email_template.{$k}.ar", trim((string) $v));
                    }

                    Notification::make()
                        ->title(__('Email templates saved'))
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

        return $svc->periodLabel($month, $year).' — '.$svc->cycleWindowDescription($month, $year);
    }

    public function getTotalApplicationsCount(): int
    {
        return MembershipApplication::query()->count();
    }

    public function canViewRolesPage(): bool
    {
        if (! class_exists(RoleResource::class)) {
            return false;
        }

        return RoleResource::canViewAny();
    }

    public function getRolesPageUrl(): ?string
    {
        if (! $this->canViewRolesPage()) {
            return null;
        }

        return RoleResource::getUrl('index');
    }

    /**
     * @return array<string, string>
     */
    protected function emailTemplateOverrides(string $locale): array
    {
        $rows = Setting::query()
            ->where('key', 'like', "email_template.%.{$locale}")
            ->get(['key', 'value']);

        $out = [];
        foreach ($rows as $row) {
            $prefix = 'email_template.';
            $suffix = ".{$locale}";
            $key = (string) $row->key;
            if (! str_starts_with($key, $prefix) || ! str_ends_with($key, $suffix)) {
                continue;
            }

            $trimmed = substr($key, strlen($prefix), -strlen($suffix));
            if ($trimmed === false || $trimmed === '') {
                continue;
            }

            $out[$trimmed] = (string) $row->value;
        }

        ksort($out);

        return $out;
    }
}
