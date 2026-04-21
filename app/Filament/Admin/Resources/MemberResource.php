<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\MemberResource\Pages;
use App\Filament\Admin\Resources\MemberResource\RelationManagers\AccountsRelationManager;
use App\Filament\Admin\Resources\MemberResource\RelationManagers\ContributionsRelationManager;
use App\Filament\Admin\Resources\MemberResource\RelationManagers\DependentsRelationManager;
use App\Filament\Admin\Resources\MemberResource\RelationManagers\LoansRelationManager;
use App\Filament\Admin\Resources\MemberResource\RelationManagers\MessagesRelationManager;
use App\Filament\Admin\Widgets\MemberRecordInsightsWidget;
use App\Filament\Admin\Widgets\MemberStatsWidget;
use App\Models\Account;
use App\Models\Contribution;
use App\Models\DirectMessage;
use App\Models\Member;
use App\Models\MembershipApplication;
use App\Notifications\AdminBroadcastNotification;
use App\Services\AccountingService;
use App\Services\ContributionCycleService;
use App\Services\LoanEligibilityService;
use App\Services\LoanRepaymentService;
use App\Services\MemberDeletionService;
use App\Support\PhoneDisplay;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Livewire\Component;

class MemberResource extends Resource
{
    protected static ?string $model = Member::class;

    protected static ?string $navigationLabel = null;

    protected static ?string $modelLabel = null;

    protected static ?string $pluralModelLabel = null;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): ?string
    {
        return 'membership';
    }

    public static function getNavigationLabel(): string
    {
        return __('Members');
    }

    public static function getModelLabel(): string
    {
        return __('Member');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Members');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([

            Section::make('Membership')
                ->icon('heroicon-o-identification')
                ->columnSpanFull()
                ->schema([
                    Forms\Components\TextInput::make('member_number')
                        ->label(__('Member Number'))
                        ->disabled(),
                    Forms\Components\Select::make('status')
                        ->options([
                            'active' => 'Active',
                            'suspended' => 'Suspended',
                            'delinquent' => 'Delinquent',
                            'terminated' => 'Terminated',
                        ])
                        ->required(),
                    Forms\Components\DatePicker::make('joined_at')
                        ->label(__('Membership Date'))
                        ->required()
                        ->native(false)
                        ->helperText(__('Affects loan eligibility (minimum 1-year membership).')),
                    Forms\Components\Select::make('monthly_contribution_amount')
                        ->label(__('Monthly Contribution Amount'))
                        ->options(Member::contributionAmountOptions())
                        ->default(500)
                        ->required()
                        ->helperText(__('Multiples of SAR 500, from SAR 500 to SAR 3,000.')),
                    Forms\Components\Select::make('parent_id')
                        ->label(__('Parent Member (Sponsor)'))
                        ->searchable()
                        ->preload()
                        ->nullable()
                        ->placeholder(__('None (independent member)'))
                        ->columnSpan(2)
                        ->options(function (Forms\Components\Select $component): array {
                            $record = $component->getRecord();
                            if (! $record instanceof Member) {
                                $record = null;
                            }

                            $query = Member::query()
                                ->with('user')
                                ->where(function ($q) use ($record) {
                                    $q->whereNull('parent_id');
                                    if ($record !== null && filled($record->parent_id)) {
                                        $q->orWhere('id', (int) $record->parent_id);
                                    }
                                })
                                ->when($record !== null, fn ($q) => $q->where('id', '!=', $record->id));

                            return $query
                                ->orderBy('member_number')
                                ->get()
                                ->mapWithKeys(
                                    fn (Member $m) => [$m->id => "{$m->member_number} – {$m->user->name}"]
                                )
                                ->all();
                        })
                        ->getOptionLabelUsing(function (mixed $value): ?string {
                            if (blank($value)) {
                                return null;
                            }

                            $member = Member::query()->with('user')->find((int) $value);

                            return $member
                                ? "{$member->member_number} – {$member->user->name}"
                                : null;
                        })
                        ->disabled(function (Forms\Components\Select $component): bool {
                            $record = $component->getRecord();

                            return $record instanceof Member
                                && $record->exists
                                && $record->dependents()->exists();
                        })
                        ->helperText(function (Forms\Components\Select $component): string {
                            $record = $component->getRecord();

                            return $record instanceof Member
                                && $record->exists
                                && $record->dependents()->exists()
                                ? __('This member has dependents and cannot be assigned a parent.')
                                : __('The parent member can fund this member\'s cash account.')
                                .' '.__('Independent members (no sponsor) are the usual choices; the current sponsor stays listed even if they have their own sponsor.');
                        }),
                ])->columns(3),

            // ── 2. User Account ──────────────────────────────────────────────
            // Section::make('User Account')
            //     ->icon('heroicon-o-user')
            //     ->description('Name and mobile phone can be updated. Email is the login credential. Mobile is stored on the membership application and used for SMS/WhatsApp.')
            //     ->schema([
            //         Forms\Components\TextInput::make('_user_name')
            //             ->label(__('Full Name'))
            //             ->required()
            //             ->maxLength(255),
            //         Forms\Components\TextInput::make('_user_email')
            //             ->label(__('Email (Login)'))
            //             ->email()
            //             ->disabled()
            //             ->helperText(__('Email cannot be changed here. Contact system admin.')),
            //         Forms\Components\TextInput::make('_app_mobile_phone')
            //             ->label(__('Mobile phone'))
            //             ->tel()
            //             ->maxLength(30),
            //     ])->columns(3),

            // ── 3. Membership application — personal details (edit on this page) ─
            // Section::make('Membership application — personal details')
            //     ->icon('heroicon-o-document-text')
            //     ->description('There is no separate “application” screen. Change these fields here, then click Save at the top of the page.')
            //     ->schema([
            //         Forms\Components\Select::make('_app_application_type')
            //             ->label(__('Application type'))
            //             ->options(MembershipApplication::applicationTypeOptions())
            //             ->default('new'),
            //         Forms\Components\Select::make('_app_gender')
            //             ->options(MembershipApplication::genderOptions())
            //             ->placeholder(__('—')),
            //         Forms\Components\Select::make('_app_marital_status')
            //             ->label(__('Marital status'))
            //             ->options(MembershipApplication::maritalStatusOptions())
            //             ->placeholder(__('—')),
            //         Forms\Components\DatePicker::make('_app_membership_date')
            //             ->label(__('Membership date'))
            //             ->native(false),
            //         Forms\Components\TextInput::make('_app_national_id')
            //             ->label(__('National ID'))
            //             ->maxLength(20),
            //         Forms\Components\DatePicker::make('_app_date_of_birth')
            //             ->label(__('Date of Birth'))
            //             ->native(false)
            //             ->maxDate(now()),
            //         Forms\Components\TextInput::make('_app_city')
            //             ->label(__('City'))
            //             ->maxLength(100),
            //         Forms\Components\Textarea::make('_app_address')
            //             ->label(__('Address'))
            //             ->rows(2)
            //             ->columnSpanFull(),
            //         Forms\Components\TextInput::make('_app_occupation')
            //             ->label(__('Occupation'))
            //             ->maxLength(150),
            //         Forms\Components\TextInput::make('_app_employer')
            //             ->label(__('Employer'))
            //             ->maxLength(150),
            //         Forms\Components\TextInput::make('_app_monthly_income')
            //             ->label(__('Monthly Income (SAR)'))
            //             ->numeric()
            //             ->prefix('SAR')
            //             ->minValue(0),
            //     ])->columns(3),

            // Section::make('Membership application — contact & banking')
            //     ->icon('heroicon-o-phone')
            //     ->collapsed()
            //     ->schema([
            //         Forms\Components\TextInput::make('_app_home_phone')
            //             ->label(__('Home phone'))
            //             ->tel()
            //             ->maxLength(30),
            //         Forms\Components\TextInput::make('_app_work_phone')
            //             ->label(__('Work phone'))
            //             ->tel()
            //             ->maxLength(30),
            //         Forms\Components\TextInput::make('_app_work_place')
            //             ->label(__('Work place'))
            //             ->maxLength(255)
            //             ->columnSpanFull(),
            //         Forms\Components\TextInput::make('_app_residency_place')
            //             ->label(__('Residency place'))
            //             ->maxLength(255)
            //             ->columnSpanFull(),
            //         Forms\Components\TextInput::make('_app_bank_account_number')
            //             ->label(__('Bank account number'))
            //             ->maxLength(50),
            //         Forms\Components\TextInput::make('_app_iban')
            //             ->label(__('IBAN'))
            //             ->maxLength(34)
            //             ->extraInputAttributes(['dir' => 'ltr', 'class' => 'font-mono']),
            //     ])->columns(3),

            // ── 4. Next of Kin (same application record) ─────────────────────
            // Section::make('Membership application — next of kin')
            //     ->icon('heroicon-o-users')
            //     ->description('Part of the same membership application; saved when you save the member.')
            //     ->schema([
            //         Forms\Components\TextInput::make('_app_next_of_kin_name')
            //             ->label(__('Name'))
            //             ->maxLength(150),
            //         Forms\Components\TextInput::make('_app_next_of_kin_phone')
            //             ->label(__('Phone'))
            //             ->tel()
            //             ->maxLength(30),
            //     ])->columns(2),

        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->striped()
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label(__('Name'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('Status'))
                    ->formatStateUsing(fn (?string $state): string => $state ? __(ucfirst(str_replace('_', ' ', $state))) : __('—'))
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'active' => 'success',
                        'suspended' => 'warning',
                        'delinquent' => 'danger',
                        'terminated' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('user.phone')
                    ->label(__('Phone'))
                    ->formatStateUsing(fn (?string $state): \Illuminate\Support\HtmlString => PhoneDisplay::toHtml($state))
                    ->searchable(),
                Tables\Columns\TextColumn::make('user.email')
                    ->label(__('Email'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('cash_balance')
                    ->label(__('Cash Balance'))
                    ->formatStateUsing(fn ($state) => 'SAR '.number_format(abs((float) $state), 2))
                    ->color(fn ($state) => ((float) $state) < 0 ? 'danger' : 'success')
                    ->sortable(),
                Tables\Columns\TextColumn::make('fund_balance')
                    ->label(__('Fund Balance'))
                    ->formatStateUsing(fn ($state) => 'SAR '.number_format(abs((float) $state), 2))
                    ->color(fn ($state) => ((float) $state) < 0 ? 'danger' : 'success')
                    ->sortable(),
                Tables\Columns\TextColumn::make('loan_balance')
                    ->label(__('Loan Balance'))
                    ->formatStateUsing(fn ($state) => 'SAR '.number_format(abs((float) $state), 2))
                    ->color(fn ($state) => ((float) $state) < 0 ? 'danger' : 'success')
                    ->sortable(),
                Tables\Columns\TextColumn::make('monthly_contribution_amount')
                    ->label(__('Allocation Amount'))
                    ->money('SAR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('parent.user.name')
                    ->label(__('Parent'))
                    ->placeholder(__('—')),
                Tables\Columns\TextColumn::make('late_contributions_marked_count')
                    ->label(__('Late #'))
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy(
                            Contribution::query()
                                ->selectRaw('count(*)')
                                ->whereColumn('contributions.member_id', 'members.id')
                                ->where('contributions.is_late', true)
                                ->whereNull('contributions.deleted_at'),
                            $direction
                        );
                    })
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'warning' : 'success'),
            ])
            ->columnManager()
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(['active' => 'Active', 'suspended' => 'Suspended', 'delinquent' => 'Delinquent', 'terminated' => 'Terminated']),
                Tables\Filters\SelectFilter::make('parent_id')
                    ->label(__('Sponsor / parent'))
                    ->options(fn () => Member::query()->with('user')->whereNull('parent_id')->orderBy('member_number')->get()
                        ->mapWithKeys(fn (Member $m) => [$m->id => "{$m->member_number} – {$m->user->name}"])),
                Tables\Filters\SelectFilter::make('monthly_contribution_amount')
                    ->label(__('Monthly allocation'))
                    ->options(Member::contributionAmountOptions()),
                Tables\Filters\TernaryFilter::make('has_dependents')
                    ->label(__('Dependents'))
                    ->trueLabel(__('Has dependents'))
                    ->falseLabel(__('No dependents'))
                    ->queries(
                        true: fn ($q) => $q->whereHas('dependents'),
                        false: fn ($q) => $q->whereDoesntHave('dependents'),
                    ),
                Tables\Filters\TernaryFilter::make('has_late_contributions')
                    ->label(__('Late contributions'))
                    ->trueLabel(__('Has late contributions'))
                    ->falseLabel(__('No late contributions'))
                    ->queries(
                        true: fn (Builder $q) => $q->whereHas(
                            'contributions',
                            fn (Builder $q) => $q->where('is_late', true)
                        ),
                        false: fn (Builder $q) => $q->whereDoesntHave(
                            'contributions',
                            fn (Builder $q) => $q->where('is_late', true)
                        ),
                    ),
                Tables\Filters\Filter::make('joined_at')
                    ->schema([
                        Forms\Components\DatePicker::make('joined_from')->label(__('Joined from')),
                        Forms\Components\DatePicker::make('joined_until')->label(__('Joined until')),
                    ])
                    ->columns(2)
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['joined_from'] ?? null, fn ($q, $d) => $q->whereDate('joined_at', '>=', $d))
                            ->when($data['joined_until'] ?? null, fn ($q, $d) => $q->whereDate('joined_at', '<=', $d));
                    }),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    Action::make('requestLoan')
                        ->label(__('Request Loan'))
                        ->icon('heroicon-o-document-currency-dollar')
                        ->color('primary')
                        ->url(fn (Member $record): string => LoanResource::getUrl('create').'?member_id='.$record->getKey())
                        ->visible(
                            fn (Member $record): bool => ! $record->trashed()
                            && LoanResource::canCreate()
                            && app(LoanEligibilityService::class)->isEligible($record)
                        ),
                    Action::make('viewApplication')
                        ->label(__('Application'))
                        ->icon('heroicon-o-clipboard-document-check')
                        ->color('info')
                        ->url(fn (Member $record): string => MembershipApplicationResource::getUrl(
                            'view',
                            ['record' => $record->latestMembershipApplication()],
                        ))
                        ->visible(
                            fn (Member $record): bool => (bool) ($record->membership_applications_exists ?? false)
                            && auth()->user()?->can('View:MembershipApplication')
                        ),
                    Action::make('contribute')
                        ->label(__('Contribute'))
                        ->icon('heroicon-o-banknotes')
                        ->color('success')
                        ->visible(
                            fn (Member $record): bool => ! $record->trashed()
                            && app(ContributionCycleService::class)->memberHasPayableContributionCycle($record)
                            && ! app(LoanRepaymentService::class)->shouldOfferOpenPeriodRepayment($record)
                        )
                        ->disabled(
                            fn (Member $record): bool => app(ContributionCycleService::class)->hasInsufficientCashForOpenPeriodContribution($record)
                        )
                        ->authorize(fn (Member $record): bool => auth()->user()?->can('update', $record) ?? false)
                        ->modalHeading(fn (): string => 'Apply contribution')
                        ->modalDescription(
                            'Select the calendar month this contribution is for (arrears). The member\'s cash account is debited and fund accounts are credited the same amount.'
                        )
                        ->modalWidth('md')
                        ->schema(fn (Member $record): array => [
                            Forms\Components\Select::make('cycle')
                                ->label(__('Contribution cycle'))
                                ->options(fn (): array => app(ContributionCycleService::class)->contributionCycleSelectOptionsForMember($record))
                                ->required()
                                ->live()
                                ->native(false)
                                ->helperText(fn (Get $get) => app(ContributionCycleService::class)->contributionModalDescriptionForMemberAndCycleKey(
                                    $record,
                                    $get('cycle'),
                                ))
                                ->columnSpanFull(),
                        ])
                        ->fillForm(fn (Member $record): array => [
                            'cycle' => app(ContributionCycleService::class)->defaultContributionCycleKeyForMember($record) ?? '',
                        ])
                        ->action(function (array $data, Member $record, Component $livewire): void {
                            $svc = app(ContributionCycleService::class);
                            $key = $data['cycle'] ?? null;

                            if (! is_string($key) || $key === '') {
                                Notification::make()
                                    ->title(__('Select a contribution cycle'))
                                    ->danger()
                                    ->send();

                                return;
                            }

                            try {
                                [$month, $year] = $svc->parseContributionCycleKey($key);
                            } catch (\InvalidArgumentException) {
                                Notification::make()
                                    ->title(__('Invalid contribution cycle'))
                                    ->danger()
                                    ->send();

                                return;
                            }

                            $outcome = $svc->applyContributionForMemberForPeriod($record, $month, $year);
                            $period = $svc->periodLabel($month, $year);

                            if ($outcome === 'applied') {
                                Notification::make()
                                    ->title(__('Contribution applied'))
                                    ->body(__('SAR :amount posted for :period.', ['amount' => number_format((float) $record->monthly_contribution_amount, 2), 'period' => $period]))
                                    ->success()
                                    ->send();
                            } elseif ($outcome === 'insufficient') {
                                Notification::make()
                                    ->title(__('Insufficient cash balance'))
                                    ->body(__('Cash balance is below the required monthly amount.'))
                                    ->danger()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title(__('Could not apply contribution'))
                                    ->body(match ($outcome) {
                                        'already_contributed' => Contribution::duplicateCycleMessage($month, $year),
                                        'exempt' => __('This member is exempt from contributions while they have an approved or active loan.'),
                                        'skipped' => __('This contribution could not be applied.'),
                                        default => __('Status: :status', ['status' => $outcome]),
                                    })
                                    ->warning()
                                    ->send();
                            }

                            static::dispatchMemberListHeaderWidgetsRefresh($livewire);
                        }),
                    Action::make('repayment')
                        ->label(__('Repayment'))
                        ->icon('heroicon-o-receipt-percent')
                        ->color('primary')
                        ->visible(
                            fn (Member $record): bool => ! $record->trashed()
                            && app(LoanRepaymentService::class)->shouldOfferOpenPeriodRepayment($record)
                        )
                        ->disabled(
                            fn (Member $record): bool => app(LoanRepaymentService::class)->hasInsufficientCashForOpenPeriodRepayment($record)
                        )
                        ->authorize(fn (Member $record): bool => auth()->user()?->can('update', $record) ?? false)
                        ->requiresConfirmation()
                        ->modalHeading(
                            fn (): string => __('Apply loan repayment - :period', ['period' => app(ContributionCycleService::class)->currentOpenPeriodLabel()])
                        )
                        ->modalDescription(
                            fn (Member $record): string => app(LoanRepaymentService::class)->openPeriodRepaymentModalDescription($record)
                        )
                        ->action(function (Member $record, Component $livewire): void {
                            $svc = app(LoanRepaymentService::class);
                            $outcome = $svc->applyOpenPeriodRepaymentForMember($record);
                            $period = app(ContributionCycleService::class)->currentOpenPeriodLabel();

                            if ($outcome === 'applied') {
                                Notification::make()
                                    ->title(__('Repayment applied'))
                                    ->body(__('Loan installment posted for :period.', ['period' => $period]))
                                    ->success()
                                    ->send();
                            } elseif ($outcome === 'insufficient') {
                                Notification::make()
                                    ->title(__('Insufficient cash balance'))
                                    ->body(__('Cash balance is below the installment amount.'))
                                    ->danger()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title(__('Could not apply repayment'))
                                    ->body(match ($outcome) {
                                        'skipped' => __('No unpaid installment for this period or no active loan.'),
                                        default => __('Status: :status', ['status' => $outcome]),
                                    })
                                    ->warning()
                                    ->send();
                            }

                            static::dispatchMemberListHeaderWidgetsRefresh($livewire);
                        }),
                    Action::make('allocate')
                        ->label(__('Allocate'))
                        ->icon('heroicon-o-arrow-right-circle')
                        ->color('warning')
                        ->visible(
                            fn (Member $record): bool => ! $record->trashed()
                            && $record->status === 'active'
                            && app(ContributionCycleService::class)->shouldShowDependentAllocationAction($record)
                        )
                        ->authorize(fn (Member $record): bool => auth()->user()?->can('update', $record) ?? false)
                        ->modalHeading(fn (): string => __('Allocate to dependents'))
                        ->modalDescription(
                            __('Choose the calendar month you are funding dependent cash for (arrears). Preview updates when you change the cycle.')
                        )
                        ->modalWidth('lg')
                        ->schema(fn (Member $record): array => [
                            Forms\Components\Select::make('cycle')
                                ->label(__('Allocation cycle'))
                                ->options(fn (): array => app(ContributionCycleService::class)->allocationCycleSelectOptionsForParent($record))
                                ->required()
                                ->live()
                                ->native(false)
                                ->columnSpanFull(),
                            Forms\Components\Placeholder::make('breakdown')
                                ->label(__(''))
                                ->content(function (Get $get) use ($record) {
                                    $key = $get('cycle');
                                    if ($key === null || $key === '') {
                                        return new HtmlString('<p class="text-sm text-gray-500 dark:text-gray-400">'.e(__('Select a cycle to preview.')).'</p>');
                                    }

                                    try {
                                        [$m, $y] = app(ContributionCycleService::class)->parseContributionCycleKey($key);
                                    } catch (\InvalidArgumentException) {
                                        return new HtmlString('');
                                    }

                                    return app(ContributionCycleService::class)->dependentAllocationModalDescriptionForPeriod($record, $m, $y);
                                })
                                ->columnSpanFull(),
                        ])
                        ->fillForm(fn (Member $record): array => [
                            'cycle' => app(ContributionCycleService::class)->defaultAllocationCycleKeyForParent($record) ?? '',
                        ])
                        ->action(function (array $data, Member $record, Component $livewire): void {
                            $svc = app(ContributionCycleService::class);
                            $key = $data['cycle'] ?? null;

                            if (! is_string($key) || $key === '') {
                                Notification::make()
                                    ->title(__('Select an allocation cycle'))
                                    ->danger()
                                    ->send();

                                return;
                            }

                            try {
                                [$month, $year] = $svc->parseContributionCycleKey($key);
                            } catch (\InvalidArgumentException) {
                                Notification::make()
                                    ->title(__('Invalid allocation cycle'))
                                    ->danger()
                                    ->send();

                                return;
                            }

                            $result = $svc->applyDependentAllocationForParentForPeriod($record, $month, $year);
                            $body = $svc->formatAllocationResultDetailTableHtml($result['details'])->toHtml();

                            if ($result['transfers'] > 0) {
                                Notification::make()
                                    ->title(__('Allocation completed'))
                                    ->body($body)
                                    ->success()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title(__('Allocation'))
                                    ->body($body)
                                    ->warning()
                                    ->send();
                            }

                            static::dispatchMemberListHeaderWidgetsRefresh($livewire);
                        }),
                    Action::make('adjust_cash')
                        ->label(__('Adjust Cash'))
                        ->icon('heroicon-o-adjustments-horizontal')
                        ->color('info')
                        ->visible(fn (Member $record): bool => ! $record->trashed())
                        ->authorize(fn (Member $record): bool => auth()->user()?->can('update', $record) ?? false)
                        ->modalHeading(fn (Member $record): string => __('Manual Cash Adjustment - :name', ['name' => $record->user->name]))
                        ->modalDescription(__('Credits or debits the member\'s cash account and posts a matching entry to the master cash account. This creates an auditable ledger entry.'))
                        ->modalWidth('md')
                        ->schema([
                            Forms\Components\Select::make('entry_type')
                                ->label(__('Type'))
                                ->options(['credit' => __('Credit (deposit / add funds)'), 'debit' => __('Debit (withdraw / remove funds)')])
                                ->required()
                                ->native(false),
                            Forms\Components\TextInput::make('amount')
                                ->label(__('Amount (SAR)'))
                                ->numeric()
                                ->minValue(0.01)
                                ->required(),
                            Forms\Components\Textarea::make('description')
                                ->label(__('Reason / Description'))
                                ->required()
                                ->rows(2)
                                ->maxLength(255),
                        ])
                        ->action(function (array $data, Member $record, Component $livewire): void {
                            $cashAccount = $record->accounts()
                                ->where('type', Account::TYPE_MEMBER_CASH)
                                ->first();

                            if (! $cashAccount) {
                                Notification::make()
                                    ->title(__('Cash account not found'))
                                    ->body(__('This member does not have a cash account yet.'))
                                    ->danger()
                                    ->send();

                                return;
                            }

                            try {
                                app(AccountingService::class)->postManualLedgerEntry(
                                    $cashAccount,
                                    $data['entry_type'],
                                    (float) $data['amount'],
                                    $data['description'],
                                    $record->id,
                                );
                            } catch (\InvalidArgumentException|\RuntimeException $e) {
                                Notification::make()
                                    ->title(__('Adjustment failed'))
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();

                                return;
                            }

                            $typeLabel = $data['entry_type'] === 'credit' ? __('Credit') : __('Debit');
                            Notification::make()
                                ->title(__('Cash :type Applied', ['type' => $typeLabel]))
                                ->body(__('SAR :amount posted on :name cash account.', ['amount' => number_format((float) $data['amount'], 2), 'name' => $record->user->name]))
                                ->success()
                                ->send();

                            static::dispatchMemberListHeaderWidgetsRefresh($livewire);
                            static::dispatchMemberRecordHeaderWidgetsRefresh($livewire);
                        }),
                    Action::make('adjust_fund')
                        ->label(__('Adjust Fund'))
                        ->icon('heroicon-o-scale')
                        ->color('info')
                        ->visible(fn (Member $record): bool => ! $record->trashed())
                        ->authorize(fn (Member $record): bool => auth()->user()?->can('update', $record) ?? false)
                        ->modalHeading(fn (Member $record): string => __('Manual Fund Adjustment - :name', ['name' => $record->user->name]))
                        ->modalDescription(__('Credits or debits the member\'s fund account and writes an auditable ledger entry.'))
                        ->modalWidth('md')
                        ->schema([
                            Forms\Components\Select::make('entry_type')
                                ->label(__('Type'))
                                ->options(['credit' => __('Credit (increase fund balance)'), 'debit' => __('Debit (decrease fund balance)')])
                                ->required()
                                ->native(false),
                            Forms\Components\TextInput::make('amount')
                                ->label(__('Amount (SAR)'))
                                ->numeric()
                                ->minValue(0.01)
                                ->required(),
                            Forms\Components\Textarea::make('description')
                                ->label(__('Reason / Description'))
                                ->required()
                                ->rows(2)
                                ->maxLength(255),
                        ])
                        ->action(function (array $data, Member $record, Component $livewire): void {
                            $fundAccount = $record->accounts()
                                ->where('type', Account::TYPE_MEMBER_FUND)
                                ->first();

                            if (! $fundAccount) {
                                Notification::make()
                                    ->title(__('Fund account not found'))
                                    ->body(__('This member does not have a fund account yet.'))
                                    ->danger()
                                    ->send();

                                return;
                            }

                            try {
                                app(AccountingService::class)->postManualLedgerEntry(
                                    $fundAccount,
                                    $data['entry_type'],
                                    (float) $data['amount'],
                                    $data['description'],
                                    $record->id,
                                );
                            } catch (\InvalidArgumentException|\RuntimeException $e) {
                                Notification::make()
                                    ->title(__('Adjustment failed'))
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();

                                return;
                            }

                            $typeLabel = $data['entry_type'] === 'credit' ? __('Credit') : __('Debit');
                            Notification::make()
                                ->title(__('Fund :type Applied', ['type' => $typeLabel]))
                                ->body(__('SAR :amount posted on :name fund account.', ['amount' => number_format((float) $data['amount'], 2), 'name' => $record->user->name]))
                                ->success()
                                ->send();

                            static::dispatchMemberListHeaderWidgetsRefresh($livewire);
                            static::dispatchMemberRecordHeaderWidgetsRefresh($livewire);
                        }),

                    Action::make('send_message')
                        ->label(__('Send Message'))
                        ->icon('heroicon-o-chat-bubble-left-right')
                        ->color('info')
                        ->visible(fn (Member $record): bool => ! $record->trashed() && $record->user !== null)
                        ->modalHeading(fn (Member $record): string => "Send Message to {$record->user->name}")
                        ->modalWidth('lg')
                        ->schema([
                            Forms\Components\TextInput::make('subject')
                                ->label(__('Subject'))
                                ->required()
                                ->maxLength(150),
                            Forms\Components\Textarea::make('body')
                                ->label(__('Message'))
                                ->required()
                                ->rows(5)
                                ->maxLength(3000),
                            Forms\Components\FileUpload::make('attachments')
                                ->label(__('Attachments'))
                                ->multiple()
                                ->disk('public')
                                ->directory('direct-messages')
                                ->openable()
                                ->downloadable()
                                ->maxFiles(5),
                        ])
                        ->action(function (array $data, Member $record): void {
                            $attachments = is_array($data['attachments'] ?? null)
                                ? array_values(array_filter($data['attachments'], fn ($file): bool => filled($file)))
                                : [];

                            $root = DirectMessage::root()
                                ->where(function (Builder $q) use ($record): void {
                                    $q->where(function (Builder $sq) use ($record): void {
                                        $sq->where('from_user_id', $record->user_id)
                                            ->whereHas('recipient', fn (Builder $admin): Builder => $admin->where('role', 'admin'));
                                    })->orWhere(function (Builder $sq) use ($record): void {
                                        $sq->where('to_user_id', $record->user_id)
                                            ->whereHas('sender', fn (Builder $admin): Builder => $admin->where('role', 'admin'));
                                    });
                                })
                                ->orderBy('created_at')
                                ->first();

                            if ($root === null) {
                                DirectMessage::create([
                                    'from_user_id' => auth()->id(),
                                    'to_user_id' => $record->user_id,
                                    'subject' => $data['subject'],
                                    'body' => $data['body'],
                                    'attachments' => $attachments,
                                ]);
                            } else {
                                DirectMessage::create([
                                    'from_user_id' => auth()->id(),
                                    'to_user_id' => $record->user_id,
                                    'parent_id' => $root->id,
                                    'subject' => $root->subject ?: $data['subject'],
                                    'body' => $data['body'],
                                    'attachments' => $attachments,
                                ]);
                            }

                            Notification::make()
                                ->title(__('New Message from Administration'))
                                ->body($data['subject'].': '.mb_strimwidth($data['body'], 0, 100, '…'))
                                ->icon('heroicon-o-chat-bubble-left-right')
                                ->iconColor('info')
                                ->actions([
                                    Action::make('view')
                                        ->label(__('View Inbox'))
                                        ->url(route('filament.member.pages.my-inbox-page')),
                                ])
                                ->sendToDatabase($record->user);

                            Notification::make()
                                ->title('Message sent to '.$record->user->name)
                                ->success()
                                ->send();
                        }),

                    Action::make('suspend')
                        ->label(__('Suspend'))
                        ->icon('heroicon-o-pause-circle')
                        ->color('warning')
                        ->visible(
                            fn (Member $record): bool => in_array($record->status, ['active', 'delinquent'], true)
                            && ! $record->trashed()
                        )
                        ->authorize(fn (Member $record): bool => auth()->user()?->can('update', $record) ?? false)
                        ->requiresConfirmation()
                        ->modalHeading(__('Suspend member'))
                        ->modalDescription(__('Sets membership to Suspended. This member will not be able to sign in to the member portal until their status is changed back.'))
                        ->action(function (Member $record, Component $livewire): void {
                            $record->update(['status' => 'suspended']);
                            Notification::make()
                                ->title(__('Member suspended'))
                                ->success()
                                ->send();
                            static::dispatchMemberListHeaderWidgetsRefresh($livewire);
                        }),
                    Action::make('terminate')
                        ->label(__('Terminate'))
                        ->icon('heroicon-o-no-symbol')
                        ->color('danger')
                        ->visible(
                            fn (Member $record): bool => $record->status !== 'terminated'
                            && ! $record->trashed()
                        )
                        ->authorize(fn (Member $record): bool => auth()->user()?->can('update', $record) ?? false)
                        ->requiresConfirmation()
                        ->modalHeading(__('Terminate membership'))
                        ->modalDescription(__('Ends membership permanently (status: Terminated). The person cannot use the member portal. This does not delete records or ledger history. Use Delete only when a full removal is required.'))
                        ->action(function (Member $record, Component $livewire): void {
                            $record->update(['status' => 'terminated']);
                            Notification::make()
                                ->title(__('Membership terminated'))
                                ->warning()
                                ->send();
                            static::dispatchMemberListHeaderWidgetsRefresh($livewire);
                        }),
                    DeleteAction::make()
                        ->modalDescription(__('Removes this member from active records: loans are safe-deleted first (ledger reversed), then bank/SMS lines, virtual accounts, the member row, and their login user (soft-deleted). Blocked while contribution records still exist — remove or soft-delete those first if you need to delete.'))
                        ->using(function (Member $record): bool {
                            try {
                                app(MemberDeletionService::class)->delete($record);

                                return true;
                            } catch (\RuntimeException $e) {
                                Notification::make()
                                    ->title(__('Cannot delete member'))
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();

                                return false;
                            }
                        })
                        ->after(fn (Component $livewire) => static::dispatchMemberListHeaderWidgetsRefresh($livewire)),
                    RestoreAction::make()
                        ->after(fn (Component $livewire) => static::dispatchMemberListHeaderWidgetsRefresh($livewire)),
                    ForceDeleteAction::make()
                        ->after(fn (Component $livewire) => static::dispatchMemberListHeaderWidgetsRefresh($livewire)),
                ])
                    ->tooltip(__('Actions')),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('suspend')
                        ->label(__('Suspend selected'))
                        ->icon('heroicon-o-pause-circle')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading(__('Suspend selected members'))
                        ->modalDescription(__('Sets each eligible row to Suspended (active or delinquent only). Already suspended, terminated, or trashed rows are skipped.'))
                        ->authorizeIndividualRecords('update')
                        ->action(function (EloquentCollection $records, Component $livewire): void {
                            $eligible = $records->filter(
                                fn (Member $r) => ! $r->trashed()
                                && in_array($r->status, ['active', 'delinquent'], true)
                            );
                            $skipped = $records->count() - $eligible->count();

                            $suspended = 0;
                            foreach ($eligible as $record) {
                                $record->update(['status' => 'suspended']);
                                $suspended++;
                            }

                            Notification::make()
                                ->title(__('Bulk suspend finished'))
                                ->body(__('Suspended: :suspended. Skipped: :skipped.', ['suspended' => $suspended, 'skipped' => $skipped]))
                                ->success()
                                ->send();

                            static::dispatchMemberListHeaderWidgetsRefresh($livewire);
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('terminate')
                        ->label(__('Terminate selected'))
                        ->icon('heroicon-o-no-symbol')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading(__('Terminate selected memberships'))
                        ->modalDescription(__('Sets each eligible row to Terminated. Rows already terminated or trashed are skipped.'))
                        ->authorizeIndividualRecords('update')
                        ->action(function (EloquentCollection $records, Component $livewire): void {
                            $eligible = $records->filter(
                                fn (Member $r) => ! $r->trashed() && $r->status !== 'terminated'
                            );
                            $skipped = $records->count() - $eligible->count();

                            $terminated = 0;
                            foreach ($eligible as $record) {
                                $record->update(['status' => 'terminated']);
                                $terminated++;
                            }

                            Notification::make()
                                ->title(__('Bulk terminate finished'))
                                ->body(__('Terminated: :terminated. Skipped: :skipped.', ['terminated' => $terminated, 'skipped' => $skipped]))
                                ->warning()
                                ->send();

                            static::dispatchMemberListHeaderWidgetsRefresh($livewire);
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('contribute')
                        ->label(__('Contribute (selected cycle)'))
                        ->icon('heroicon-o-banknotes')
                        ->color('success')
                        ->modalHeading(fn (): string => __('Apply contributions'))
                        ->modalDescription(
                            'Choose the contribution cycle. Each selected member is processed for that month: applied if they have no row yet, '.
                            'are not loan-exempt, and have enough cash; otherwise counted as insufficient or skipped.'
                        )
                        ->modalWidth('md')
                        ->schema(fn (): array => [
                            Forms\Components\Select::make('cycle')
                                ->label(__('Contribution cycle'))
                                ->options(fn (): array => app(ContributionCycleService::class)->contributionCycleSelectOptionsForBulk())
                                ->required()
                                ->native(false)
                                ->helperText(__('The same calendar month applies to every selected member.'))
                                ->columnSpanFull(),
                        ])
                        ->fillForm(function (): array {
                            $svc = app(ContributionCycleService::class);
                            [$m, $y] = $svc->currentOpenPeriod();

                            return ['cycle' => $svc->contributionCycleKey($m, $y)];
                        })
                        ->authorizeIndividualRecords('update')
                        ->action(function (array $data, EloquentCollection $records, Component $livewire): void {
                            $svc = app(ContributionCycleService::class);
                            $key = $data['cycle'] ?? null;

                            if (! is_string($key) || $key === '') {
                                Notification::make()
                                    ->title(__('Select a contribution cycle'))
                                    ->danger()
                                    ->send();

                                return;
                            }

                            try {
                                [$month, $year] = $svc->parseContributionCycleKey($key);
                            } catch (\InvalidArgumentException) {
                                Notification::make()
                                    ->title(__('Invalid contribution cycle'))
                                    ->danger()
                                    ->send();

                                return;
                            }

                            $applied = 0;
                            $insufficient = 0;
                            $skipped = 0;

                            foreach ($records as $record) {
                                if (! $record instanceof Member || $record->trashed()) {
                                    $skipped++;

                                    continue;
                                }

                                $outcome = $svc->applyContributionForMemberForPeriod($record, $month, $year);

                                match ($outcome) {
                                    'applied' => $applied++,
                                    'insufficient' => $insufficient++,
                                    default => $skipped++,
                                };
                            }

                            $period = $svc->periodLabel($month, $year);

                            Notification::make()
                                ->title(__('Bulk contribute finished'))
                                ->body(__('Period: :period. Applied: :applied. Insufficient cash: :insufficient. Skipped: :skipped.', ['period' => $period, 'applied' => $applied, 'insufficient' => $insufficient, 'skipped' => $skipped]))
                                ->color($insufficient > 0 ? 'warning' : 'success')
                                ->send();

                            static::dispatchMemberListHeaderWidgetsRefresh($livewire);
                        })
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make()
                        ->modalDescription(__('Deletes each selected member like single delete (loans safe-deleted first, then bank/SMS and accounts). Rows that fail (e.g. contributions still present) are skipped and reported.'))
                        ->using(function (DeleteBulkAction $action, $records) {
                            $svc = app(MemberDeletionService::class);
                            foreach ($records as $record) {
                                try {
                                    $svc->delete($record);
                                } catch (\Throwable $e) {
                                    $action->reportBulkProcessingFailure(message: $e->getMessage());
                                    if (! $e instanceof \RuntimeException) {
                                        report($e);
                                    }
                                }
                            }
                        })
                        ->after(fn (Component $livewire) => static::dispatchMemberListHeaderWidgetsRefresh($livewire)),
                    BulkAction::make('broadcast_notification')
                        ->label(__('Send Notification'))
                        ->icon('heroicon-o-megaphone')
                        ->color('info')
                        ->modalHeading(__('Send Custom Notification'))
                        ->modalDescription(__('Sends an email and in-app notification to each selected member. Use this for announcements, reminders, or alerts.'))
                        ->modalWidth('lg')
                        ->schema([
                            Forms\Components\TextInput::make('subject')
                                ->label(__('Subject / Title'))
                                ->required()
                                ->maxLength(150),
                            Forms\Components\Textarea::make('body')
                                ->label(__('Message Body'))
                                ->required()
                                ->rows(4)
                                ->maxLength(1000),
                        ])
                        ->action(function (array $data, EloquentCollection $records): void {
                            $sent = 0;
                            $skipped = 0;

                            foreach ($records as $member) {
                                if (! $member instanceof Member || ! $member->user) {
                                    $skipped++;

                                    continue;
                                }
                                $member->user->notify(new AdminBroadcastNotification(
                                    $data['subject'],
                                    $data['body'],
                                ));
                                $sent++;
                            }

                            Notification::make()
                                ->title(__('Notifications Sent'))
                                ->body(__('Sent to :sent member(s). Skipped: :skipped.', ['sent' => $sent, 'skipped' => $skipped]))
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    RestoreBulkAction::make()
                        ->after(fn (Component $livewire) => static::dispatchMemberListHeaderWidgetsRefresh($livewire)),
                    ForceDeleteBulkAction::make()
                        ->after(fn (Component $livewire) => static::dispatchMemberListHeaderWidgetsRefresh($livewire)),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ContributionsRelationManager::class,
            LoansRelationManager::class,
            AccountsRelationManager::class,
            DependentsRelationManager::class,
            MessagesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMembers::route('/'),
            'create' => Pages\CreateMember::route('/create'),
            'edit' => Pages\EditMember::route('/{record}/edit'),
            'view' => Pages\ViewMember::route('/{record}'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->withTrashed();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withExists('membershipApplications')
            ->with(['user', 'parent.user'])
            ->withSum(
                [
                    'accounts as cash_balance' => fn (Builder $q) => $q->where('type', Account::TYPE_MEMBER_CASH),
                ],
                'balance'
            )
            ->withSum(
                [
                    'accounts as fund_balance' => fn (Builder $q) => $q->where('type', Account::TYPE_MEMBER_FUND),
                ],
                'balance'
            )
            ->selectSub(
                DB::table('loan_installments')
                    ->join('loans', 'loans.id', '=', 'loan_installments.loan_id')
                    ->whereColumn('loans.member_id', 'members.id')
                    ->whereIn('loan_installments.status', ['pending', 'overdue'])
                    ->selectRaw('COALESCE(SUM(loan_installments.amount), 0)'),
                'loan_balance'
            )
            ->withCount([
                'contributions as late_contributions_marked_count' => fn ($q) => $q->where('is_late', true),
            ])
            ->withSum([
                'contributions as late_contributions_marked_amount' => fn ($q) => $q->where('is_late', true),
            ], 'amount');
    }

    /**
     * Refresh list-page header widgets ({@see MemberStatsWidget}) after table mutations.
     */
    public static function dispatchMemberListHeaderWidgetsRefresh(?Component $livewire): void
    {
        if ($livewire === null) {
            return;
        }

        static::dispatchLivewireWidgetsRefresh($livewire, [
            MemberStatsWidget::class,
        ]);
    }

    /**
     * Refresh member View/Edit header widgets after the record changes.
     */
    public static function dispatchMemberRecordHeaderWidgetsRefresh(?Component $livewire): void
    {
        if ($livewire === null) {
            return;
        }

        static::dispatchLivewireWidgetsRefresh($livewire, [
            MemberRecordInsightsWidget::class,
        ]);
    }

    /**
     * @param  array<class-string>  $widgetClasses
     */
    protected static function dispatchLivewireWidgetsRefresh(Component $livewire, array $widgetClasses): void
    {
        $parts = [];
        foreach ($widgetClasses as $class) {
            $name = json_encode(
                app('livewire.factory')->resolveComponentName($class),
                JSON_THROW_ON_ERROR
            );
            $parts[] = 'window.Livewire.getByName('.$name.').forEach(w => w.$refresh());';
        }

        $livewire->js('setTimeout(() => { '.implode(' ', $parts).' }, 0)');
    }
}
