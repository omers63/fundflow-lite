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

    /** @var 'loans'|'contribution-cycles'|'public-membership'|'statements'|'communication'|'roles' */
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
        return 'System settings';
    }

    public function getHeaderActions(): array
    {
        return [
            Action::make('save_loan_settings')
                ->label('Save loan settings')
                ->icon('heroicon-o-check')
                ->color('primary')
                ->visible(fn (): bool => $this->activeTab === 'loans' && $this->loanSubTab === 'loan-rules')
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
                    Section::make('Delinquency policy')
                        ->description(
                            'Daily job `fund:check-delinquency` evaluates missed monthly contributions (when due) and unpaid loan installments for active loans. '.
                            'Breaching either threshold suspends the member (member portal blocked) and shifts active loan repayment collection to the guarantor until restored.'
                        )
                        ->schema([
                            Forms\Components\TextInput::make('delinquency_consecutive')
                                ->label('Consecutive missed cycles')
                                ->numeric()
                                ->required()
                                ->minValue(1)
                                ->maxValue(36)
                                ->default(3)
                                ->helperText('Trailing streak of closed months where any required contribution or repayment was still missed.'),
                            Forms\Components\TextInput::make('delinquency_total')
                                ->label('Total misses (rolling window)')
                                ->numeric()
                                ->required()
                                ->minValue(1)
                                ->maxValue(240)
                                ->default(15)
                                ->helperText('Count of missed months within the lookback window below (spread-out misses).'),
                            Forms\Components\TextInput::make('delinquency_lookback_months')
                                ->label('Rolling window (months)')
                                ->numeric()
                                ->required()
                                ->minValue(1)
                                ->maxValue(240)
                                ->default(60)
                                ->helperText('How far back to count toward the total-miss threshold.'),
                        ])->columns(3),
                    Section::make('Late fees (tiered by days after due)')
                        ->description(
                            'Due is the end of the contribution/repayment cycle for that month. Calendar days after that are counted; '.
                            'the highest tier reached (30+ ≥ 20+ ≥ 10+ ≥ 1+) with a non-zero SAR amount applies — if a tier is 0, the next lower tier is used. '.
                            'Cash-account debits bundle principal and late fee; late fees credit master cash only (not master fund).'
                        )
                        ->schema([
                            Forms\Components\TextInput::make('late_fee_contribution_1d')
                                ->label('Contribution — 1+ days late (SAR)')
                                ->numeric()->prefix('SAR')->required()->minValue(0)->default(0),
                            Forms\Components\TextInput::make('late_fee_contribution_10d')
                                ->label('Contribution — 10+ days late (SAR)')
                                ->numeric()->prefix('SAR')->required()->minValue(0)->default(0),
                            Forms\Components\TextInput::make('late_fee_contribution_20d')
                                ->label('Contribution — 20+ days late (SAR)')
                                ->numeric()->prefix('SAR')->required()->minValue(0)->default(0),
                            Forms\Components\TextInput::make('late_fee_contribution_30d')
                                ->label('Contribution — 30+ days late (SAR)')
                                ->numeric()->prefix('SAR')->required()->minValue(0)->default(0),
                            Forms\Components\TextInput::make('late_fee_repayment_1d')
                                ->label('Repayment — 1+ days late (SAR)')
                                ->numeric()->prefix('SAR')->required()->minValue(0)->default(0),
                            Forms\Components\TextInput::make('late_fee_repayment_10d')
                                ->label('Repayment — 10+ days late (SAR)')
                                ->numeric()->prefix('SAR')->required()->minValue(0)->default(0),
                            Forms\Components\TextInput::make('late_fee_repayment_20d')
                                ->label('Repayment — 20+ days late (SAR)')
                                ->numeric()->prefix('SAR')->required()->minValue(0)->default(0),
                            Forms\Components\TextInput::make('late_fee_repayment_30d')
                                ->label('Repayment — 30+ days late (SAR)')
                                ->numeric()->prefix('SAR')->required()->minValue(0)->default(0),
                        ])->columns(3),
                ])
                ->action(function (array $data): void {
                    Setting::set('contribution.cycle_start_day', max(1, min(28, (int) $data['cycle_start_day'])));
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
                        ->title('Cycle settings saved')
                        ->success()
                        ->send();
                }),
            Action::make('save_statement_settings')
                ->label('Save statement settings')
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
                    Section::make('Branding')
                        ->description('These values appear on every generated PDF statement.')
                        ->schema([
                            Forms\Components\TextInput::make('brand_name')
                                ->label('Organization Name')
                                ->required()
                                ->maxLength(80)
                                ->helperText('Printed at the top of every statement.'),
                            Forms\Components\TextInput::make('tagline')
                                ->label('Tagline / Sub-brand')
                                ->maxLength(120)
                                ->helperText('Small text under the organization name.'),
                            Forms\Components\TextInput::make('accent_color')
                                ->label('Header Accent Color (hex)')
                                ->required()
                                ->maxLength(7)
                                ->placeholder('#059669')
                                ->helperText('Must be a valid 6-digit hex code, e.g. #059669 (green), #1d4ed8 (blue), #7c3aed (purple).'),
                        ])->columns(3),
                    Section::make('Footer & Signature')
                        ->schema([
                            Forms\Components\Textarea::make('footer_disclaimer')
                                ->label('Footer Disclaimer')
                                ->rows(2)
                                ->columnSpanFull()
                                ->helperText('Printed at the bottom of every PDF, e.g. "Confidential — for named member only."'),
                            Forms\Components\TextInput::make('signature_line')
                                ->label('Authorized Signature Line')
                                ->maxLength(100)
                                ->helperText('Appears in the signature block of the PDF.'),
                        ]),
                    Section::make('Delivery & Content')
                        ->description('Control what is included in each generated PDF and how members are notified.')
                        ->schema([
                            Forms\Components\Toggle::make('auto_email')
                                ->label('Auto-email members on generation')
                                ->helperText('When enabled, each member receives an email with the statement PDF attached when statements are generated.'),
                            Forms\Components\Toggle::make('include_transactions')
                                ->label('Include account transaction detail table')
                                ->helperText('Shows every credit/debit on the member\'s accounts for the period.'),
                            Forms\Components\Toggle::make('include_loan_section')
                                ->label('Include loan standing section')
                                ->helperText('Shows active loan balance, installment progress, and overdue alerts.'),
                            Forms\Components\Toggle::make('include_compliance')
                                ->label('Include compliance snapshot')
                                ->helperText('Shows compliance score, late contribution/repayment counts.'),
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
                        ->title('Statement settings saved')
                        ->success()
                        ->send();
                }),

            Action::make('save_public_membership_settings')
                ->label('Save public membership settings')
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
                    Section::make('Membership application fees')
                        ->description(
                            'Set a separate fee for each application type. When at least one fee is greater than zero, /apply adds a final payment step (after identity, employment, and document upload): applicants transfer to your bank and submit a reference for the fee that matches their chosen type, then submit the application. '.
                            'On successful submission, that amount is credited to the master cash account only (not the master fund). Reconcile with your bank to avoid double-counting if you also import the same deposit.'
                        )
                        ->schema([
                            Forms\Components\TextInput::make('membership_application_fee_new')
                                ->label('New membership (SAR)')
                                ->numeric()
                                ->minValue(0)
                                ->required()
                                ->default(0),
                            Forms\Components\TextInput::make('membership_application_fee_resume')
                                ->label('Resume membership (SAR)')
                                ->numeric()
                                ->minValue(0)
                                ->required()
                                ->default(0),
                            Forms\Components\TextInput::make('membership_application_fee_renew')
                                ->label('Renew membership (SAR)')
                                ->numeric()
                                ->minValue(0)
                                ->required()
                                ->default(0),
                            Forms\Components\Textarea::make('membership_application_fee_bank_instructions')
                                ->label('Bank transfer instructions')
                                ->rows(6)
                                ->columnSpanFull()
                                ->helperText('Shown on the application form (plain text; line breaks preserved). Include IBAN, account name, and bank name.'),
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
                        ->title('Public membership settings saved')
                        ->success()
                        ->send();
                }),

            Action::make('save_communication_settings')
                ->label('Save communication settings')
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
                    Section::make('Communication Channels')
                        ->description(
                            'Enable or disable each outbound communication channel system-wide. '.
                            'When a channel is disabled, no notifications of any type will be sent through it, '.
                            'regardless of individual member preferences.'
                        )
                        ->schema([
                            Forms\Components\Toggle::make('channel_in_app')
                                ->label('In-App Inbox')
                                ->helperText('Shows notifications inside the member portal. Disabling this silences all in-app alerts — use with caution.')
                                ->onColor('success')
                                ->offColor('danger'),
                            Forms\Components\Toggle::make('channel_email')
                                ->label('Email')
                                ->helperText('Sends emails via the configured SMTP/mail driver (MAIL_* environment variables).')
                                ->onColor('success')
                                ->offColor('danger'),
                            Forms\Components\Toggle::make('channel_sms')
                                ->label('SMS')
                                ->helperText('Sends SMS messages via Twilio (TWILIO_* environment variables must be set).')
                                ->onColor('success')
                                ->offColor('danger'),
                            Forms\Components\Toggle::make('channel_whatsapp')
                                ->label('WhatsApp')
                                ->helperText('Sends WhatsApp messages via Twilio. Requires a verified WhatsApp sender number.')
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
                        ->title('Communication settings saved')
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
}
