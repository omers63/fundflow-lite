<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\MemberResource\Pages;
use App\Filament\Admin\Resources\MembershipApplicationResource;
use App\Filament\Admin\Widgets\MemberRecordInsightsWidget;
use App\Filament\Admin\Widgets\MemberStatsWidget;
use App\Filament\Admin\Resources\MemberResource\RelationManagers\AccountsRelationManager;
use App\Filament\Admin\Resources\MemberResource\RelationManagers\ContributionsRelationManager;
use App\Filament\Admin\Resources\MemberResource\RelationManagers\DependentsRelationManager;
use App\Filament\Admin\Resources\MemberResource\RelationManagers\LoansRelationManager;
use App\Models\Contribution;
use App\Models\Member;
use App\Models\MembershipApplication;
use App\Services\ContributionCycleService;
use App\Services\LoanRepaymentService;
use App\Services\MemberDeletionService;
use App\Services\MemberImportService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
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
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Livewire\Component;

class MemberResource extends Resource
{
    protected static ?string $model = Member::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): ?string
    {
        return __('app.nav.group.membership');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([

            Section::make('Membership')
                ->icon('heroicon-o-identification')
                ->columnSpanFull()
                ->schema([
                    Forms\Components\TextInput::make('member_number')
                        ->label('Member Number')
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
                        ->label('Membership Date')
                        ->required()
                        ->native(false)
                        ->helperText('Affects loan eligibility (minimum 1-year membership).'),
                    Forms\Components\Select::make('monthly_contribution_amount')
                        ->label('Monthly Contribution Amount')
                        ->options(Member::contributionAmountOptions())
                        ->default(500)
                        ->required()
                        ->helperText('Multiples of SAR 500, from SAR 500 to SAR 3,000.'),
                    Forms\Components\Select::make('parent_id')
                        ->label('Parent Member (Sponsor)')
                        ->searchable()
                        ->preload()
                        ->nullable()
                        ->placeholder('None (independent member)')
                        ->columnSpan(2)
                        ->options(function (Forms\Components\Select $component): array {
                            $record = $component->getRecord();
                            if (!$record instanceof Member) {
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
                                ->when($record !== null, fn($q) => $q->where('id', '!=', $record->id));

                            return $query
                                ->orderBy('member_number')
                                ->get()
                                ->mapWithKeys(
                                    fn(Member $m) => [$m->id => "{$m->member_number} – {$m->user->name}"]
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
                                ? 'This member has dependents and cannot be assigned a parent.'
                                : 'The parent member can fund this member\'s cash account. '
                                . 'Independent members (no sponsor) are the usual choices; the current sponsor stays listed even if they have their own sponsor.';
                        }),
                ])->columns(3),

            // ── 2. User Account ──────────────────────────────────────────────
            // Section::make('User Account')
            //     ->icon('heroicon-o-user')
            //     ->description('Name and mobile phone can be updated. Email is the login credential. Mobile is stored on the membership application and used for SMS/WhatsApp.')
            //     ->schema([
            //         Forms\Components\TextInput::make('_user_name')
            //             ->label('Full Name')
            //             ->required()
            //             ->maxLength(255),
            //         Forms\Components\TextInput::make('_user_email')
            //             ->label('Email (Login)')
            //             ->email()
            //             ->disabled()
            //             ->helperText('Email cannot be changed here. Contact system admin.'),
            //         Forms\Components\TextInput::make('_app_mobile_phone')
            //             ->label('Mobile phone')
            //             ->tel()
            //             ->maxLength(30),
            //     ])->columns(3),

            // ── 3. Membership application — personal details (edit on this page) ─
            // Section::make('Membership application — personal details')
            //     ->icon('heroicon-o-document-text')
            //     ->description('There is no separate “application” screen. Change these fields here, then click Save at the top of the page.')
            //     ->schema([
            //         Forms\Components\Select::make('_app_application_type')
            //             ->label('Application type')
            //             ->options(MembershipApplication::applicationTypeOptions())
            //             ->default('new'),
            //         Forms\Components\Select::make('_app_gender')
            //             ->options(MembershipApplication::genderOptions())
            //             ->placeholder('—'),
            //         Forms\Components\Select::make('_app_marital_status')
            //             ->label('Marital status')
            //             ->options(MembershipApplication::maritalStatusOptions())
            //             ->placeholder('—'),
            //         Forms\Components\DatePicker::make('_app_membership_date')
            //             ->label('Membership date')
            //             ->native(false),
            //         Forms\Components\TextInput::make('_app_national_id')
            //             ->label('National ID')
            //             ->maxLength(20),
            //         Forms\Components\DatePicker::make('_app_date_of_birth')
            //             ->label('Date of Birth')
            //             ->native(false)
            //             ->maxDate(now()),
            //         Forms\Components\TextInput::make('_app_city')
            //             ->label('City')
            //             ->maxLength(100),
            //         Forms\Components\Textarea::make('_app_address')
            //             ->label('Address')
            //             ->rows(2)
            //             ->columnSpanFull(),
            //         Forms\Components\TextInput::make('_app_occupation')
            //             ->label('Occupation')
            //             ->maxLength(150),
            //         Forms\Components\TextInput::make('_app_employer')
            //             ->label('Employer')
            //             ->maxLength(150),
            //         Forms\Components\TextInput::make('_app_monthly_income')
            //             ->label('Monthly Income (SAR)')
            //             ->numeric()
            //             ->prefix('SAR')
            //             ->minValue(0),
            //     ])->columns(3),

            // Section::make('Membership application — contact & banking')
            //     ->icon('heroicon-o-phone')
            //     ->collapsed()
            //     ->schema([
            //         Forms\Components\TextInput::make('_app_home_phone')
            //             ->label('Home phone')
            //             ->tel()
            //             ->maxLength(30),
            //         Forms\Components\TextInput::make('_app_work_phone')
            //             ->label('Work phone')
            //             ->tel()
            //             ->maxLength(30),
            //         Forms\Components\TextInput::make('_app_work_place')
            //             ->label('Work place')
            //             ->maxLength(255)
            //             ->columnSpanFull(),
            //         Forms\Components\TextInput::make('_app_residency_place')
            //             ->label('Residency place')
            //             ->maxLength(255)
            //             ->columnSpanFull(),
            //         Forms\Components\TextInput::make('_app_bank_account_number')
            //             ->label('Bank account number')
            //             ->maxLength(50),
            //         Forms\Components\TextInput::make('_app_iban')
            //             ->label('IBAN')
            //             ->maxLength(34)
            //             ->extraInputAttributes(['dir' => 'ltr', 'class' => 'font-mono']),
            //     ])->columns(3),

            // ── 4. Next of Kin (same application record) ─────────────────────
            // Section::make('Membership application — next of kin')
            //     ->icon('heroicon-o-users')
            //     ->description('Part of the same membership application; saved when you save the member.')
            //     ->schema([
            //         Forms\Components\TextInput::make('_app_next_of_kin_name')
            //             ->label('Name')
            //             ->maxLength(150),
            //         Forms\Components\TextInput::make('_app_next_of_kin_phone')
            //             ->label('Phone')
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
                Tables\Columns\TextColumn::make('member_number')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Name')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('user.email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('user.phone')
                    ->label('Phone')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state) => match ($state) {
                        'active' => 'success',
                        'suspended' => 'warning',
                        'delinquent' => 'danger',
                        'terminated' => 'danger',
                        default => 'gray',
                    })
                    ->toggleable(),
                Tables\Columns\TextColumn::make('joined_at')
                    ->date('d M Y')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('monthly_contribution_amount')
                    ->label('Monthly Alloc.')
                    ->money('SAR')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('parent.user.name')
                    ->label('Parent')
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('contributions_sum_amount')
                    ->label('Total Contributions')
                    ->money('SAR')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('late_contributions_marked_count')
                    ->label('Late #')
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
                    ->color(fn($state) => $state > 0 ? 'warning' : 'success')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('late_contributions_marked_amount')
                    ->label('Late Amount')
                    ->money('SAR')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy(
                            Contribution::query()
                                ->selectRaw('coalesce(sum(contributions.amount),0)')
                                ->whereColumn('contributions.member_id', 'members.id')
                                ->where('contributions.is_late', true)
                                ->whereNull('contributions.deleted_at'),
                            $direction
                        );
                    })
                    ->color(fn($state) => $state > 0 ? 'warning' : 'gray')
                    ->toggleable(),
            ])
            ->columnManager()
            ->headerActions([
                Action::make('importMembers')
                    ->label('Import Members')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('success')
                    ->visible(fn(): bool => static::canCreate() || (bool) auth()->user()?->can('Update:Member'))
                    ->modalHeading('Import members from CSV')
                    ->modalDescription(
                        'First row must be headers. Required: email; name required for new members only (balance-only rows for existing emails may leave name blank). Optional: password, phone, joined_at, status, monthly_contribution_amount, parent_member_number, ' .
                        'cash_balance (≥ 0), fund_balance (may be negative — paired debit on master + member fund, e.g. master-funded loan). ' .
                        'Existing email: if the user already has a member, applies cash/fund adjustments only (other columns ignored); requires Update:Member. No member record → error. ' .
                        'New members require Create:Member. Parent rows before dependents. Status: active, suspended, delinquent, terminated. Contribution: 500–3000 in steps of 500.'
                    )
                    ->modalWidth('2xl')
                    ->schema([
                        Forms\Components\FileUpload::make('csv_file')
                            ->label('CSV file')
                            ->disk('local')
                            ->directory('member-imports')
                            ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'])
                            ->required(),
                        Forms\Components\TextInput::make('default_password')
                            ->label('Default password')
                            ->password()
                            ->revealable()
                            ->required()
                            ->minLength(8)
                            ->helperText('Used when the password column is empty or shorter than 8 characters. Members should change it after first login.'),
                    ])
                    ->action(function (array $data, Component $livewire): void {
                        $relative = $data['csv_file'];
                        $fullPath = Storage::disk('local')->path($relative);

                        try {
                            $result = app(MemberImportService::class)->import($fullPath, $data['default_password']);
                        } finally {
                            Storage::disk('local')->delete($relative);
                        }

                        $body = "Created: {$result['created']} · Updated (balances): {$result['updated']} · Skipped: {$result['skipped']} · Failed: {$result['failed']}";

                        if ($result['errors'] !== []) {
                            $preview = implode("\n", array_slice($result['errors'], 0, 8));
                            if (count($result['errors']) > 8) {
                                $preview .= "\n… and " . (count($result['errors']) - 8) . ' more';
                            }
                            $body .= "\n\n" . $preview;
                        }

                        Notification::make()
                            ->title('Member import finished')
                            ->body($body)
                            ->color($result['failed'] > 0 || $result['errors'] !== [] ? 'warning' : 'success')
                            ->persistent()
                            ->send();

                        static::dispatchMemberListHeaderWidgetsRefresh($livewire);
                    }),
                CreateAction::make()
                    ->label('New Member')
                    ->icon('heroicon-o-plus-circle')
                    ->url(static::getUrl('create'))
                    ->visible(fn(): bool => static::canCreate()),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(['active' => 'Active', 'suspended' => 'Suspended', 'delinquent' => 'Delinquent', 'terminated' => 'Terminated']),
                Tables\Filters\SelectFilter::make('parent_id')
                    ->label('Sponsor / parent')
                    ->options(fn() => Member::query()->with('user')->whereNull('parent_id')->orderBy('member_number')->get()
                        ->mapWithKeys(fn(Member $m) => [$m->id => "{$m->member_number} – {$m->user->name}"])),
                Tables\Filters\SelectFilter::make('monthly_contribution_amount')
                    ->label('Monthly allocation')
                    ->options(Member::contributionAmountOptions()),
                Tables\Filters\TernaryFilter::make('has_dependents')
                    ->label('Dependents')
                    ->trueLabel('Has dependents')
                    ->falseLabel('No dependents')
                    ->queries(
                        true: fn($q) => $q->whereHas('dependents'),
                        false: fn($q) => $q->whereDoesntHave('dependents'),
                    ),
                Tables\Filters\TernaryFilter::make('has_late_contributions')
                    ->label('Late contributions')
                    ->trueLabel('Has late contributions')
                    ->falseLabel('No late contributions')
                    ->queries(
                        true: fn(Builder $q) => $q->whereHas(
                            'contributions',
                            fn(Builder $q) => $q->where('is_late', true)
                        ),
                        false: fn(Builder $q) => $q->whereDoesntHave(
                            'contributions',
                            fn(Builder $q) => $q->where('is_late', true)
                        ),
                    ),
                Tables\Filters\Filter::make('joined_at')
                    ->schema([
                        Forms\Components\DatePicker::make('joined_from')->label('Joined from'),
                        Forms\Components\DatePicker::make('joined_until')->label('Joined until'),
                    ])
                    ->columns(2)
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['joined_from'] ?? null, fn($q, $d) => $q->whereDate('joined_at', '>=', $d))
                            ->when($data['joined_until'] ?? null, fn($q, $d) => $q->whereDate('joined_at', '<=', $d));
                    }),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    Action::make('viewApplication')
                        ->label('Application')
                        ->icon('heroicon-o-clipboard-document-check')
                        ->color('info')
                        ->url(fn(Member $record): string => MembershipApplicationResource::getUrl(
                            'view',
                            ['record' => $record->latestMembershipApplication()],
                        ))
                        ->visible(
                            fn(Member $record): bool => (bool) ($record->membership_applications_exists ?? false)
                            && auth()->user()?->can('View:MembershipApplication')
                        ),
                    Action::make('contribute')
                        ->label('Contribute')
                        ->icon('heroicon-o-banknotes')
                        ->color('success')
                        ->visible(
                            fn(Member $record): bool => !$record->trashed()
                            && app(ContributionCycleService::class)->memberHasPayableContributionCycle($record)
                            && !app(LoanRepaymentService::class)->shouldOfferOpenPeriodRepayment($record)
                        )
                        ->disabled(
                            fn(Member $record): bool => app(ContributionCycleService::class)->hasInsufficientCashForOpenPeriodContribution($record)
                        )
                        ->authorize(fn(Member $record): bool => auth()->user()?->can('update', $record) ?? false)
                        ->modalHeading(fn(): string => 'Apply contribution')
                        ->modalDescription(
                            'Select the calendar month this contribution is for (arrears). The member\'s cash account is debited and fund accounts are credited the same amount.'
                        )
                        ->modalWidth('md')
                        ->schema(fn(Member $record): array => [
                            Forms\Components\Select::make('cycle')
                                ->label('Contribution cycle')
                                ->options(fn(): array => app(ContributionCycleService::class)->contributionCycleSelectOptionsForMember($record))
                                ->required()
                                ->live()
                                ->native(false)
                                ->helperText(fn(Get $get) => app(ContributionCycleService::class)->contributionModalDescriptionForMemberAndCycleKey(
                                    $record,
                                    $get('cycle'),
                                ))
                                ->columnSpanFull(),
                        ])
                        ->fillForm(fn(Member $record): array => [
                            'cycle' => app(ContributionCycleService::class)->defaultContributionCycleKeyForMember($record) ?? '',
                        ])
                        ->action(function (array $data, Member $record, Component $livewire): void {
                            $svc = app(ContributionCycleService::class);
                            $key = $data['cycle'] ?? null;

                            if (!is_string($key) || $key === '') {
                                Notification::make()
                                    ->title('Select a contribution cycle')
                                    ->danger()
                                    ->send();

                                return;
                            }

                            try {
                                [$month, $year] = $svc->parseContributionCycleKey($key);
                            } catch (\InvalidArgumentException) {
                                Notification::make()
                                    ->title('Invalid contribution cycle')
                                    ->danger()
                                    ->send();

                                return;
                            }

                            $outcome = $svc->applyContributionForMemberForPeriod($record, $month, $year);
                            $period = $svc->periodLabel($month, $year);

                            if ($outcome === 'applied') {
                                Notification::make()
                                    ->title('Contribution applied')
                                    ->body('SAR ' . number_format((float) $record->monthly_contribution_amount, 2) . " posted for {$period}.")
                                    ->success()
                                    ->send();
                            } elseif ($outcome === 'insufficient') {
                                Notification::make()
                                    ->title('Insufficient cash balance')
                                    ->body('Cash balance is below the required monthly amount.')
                                    ->danger()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title('Could not apply contribution')
                                    ->body(match ($outcome) {
                                        'already_contributed' => Contribution::duplicateCycleMessage($month, $year),
                                        'exempt' => 'This member is exempt from contributions while they have an approved or active loan.',
                                        'skipped' => 'This contribution could not be applied.',
                                        default => "Status: {$outcome}",
                                    })
                                    ->warning()
                                    ->send();
                            }

                            static::dispatchMemberListHeaderWidgetsRefresh($livewire);
                        }),
                    Action::make('repayment')
                        ->label('Repayment')
                        ->icon('heroicon-o-receipt-percent')
                        ->color('primary')
                        ->visible(
                            fn(Member $record): bool => !$record->trashed()
                            && app(LoanRepaymentService::class)->shouldOfferOpenPeriodRepayment($record)
                        )
                        ->disabled(
                            fn(Member $record): bool => app(LoanRepaymentService::class)->hasInsufficientCashForOpenPeriodRepayment($record)
                        )
                        ->authorize(fn(Member $record): bool => auth()->user()?->can('update', $record) ?? false)
                        ->requiresConfirmation()
                        ->modalHeading(
                            fn(): string => 'Apply loan repayment – ' . app(ContributionCycleService::class)->currentOpenPeriodLabel()
                        )
                        ->modalDescription(
                            fn(Member $record): string => app(LoanRepaymentService::class)->openPeriodRepaymentModalDescription($record)
                        )
                        ->action(function (Member $record, Component $livewire): void {
                            $svc = app(LoanRepaymentService::class);
                            $outcome = $svc->applyOpenPeriodRepaymentForMember($record);
                            $period = app(ContributionCycleService::class)->currentOpenPeriodLabel();

                            if ($outcome === 'applied') {
                                Notification::make()
                                    ->title('Repayment applied')
                                    ->body("Loan installment posted for {$period}.")
                                    ->success()
                                    ->send();
                            } elseif ($outcome === 'insufficient') {
                                Notification::make()
                                    ->title('Insufficient cash balance')
                                    ->body('Cash balance is below the installment amount.')
                                    ->danger()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title('Could not apply repayment')
                                    ->body(match ($outcome) {
                                        'skipped' => 'No unpaid installment for this period or no active loan.',
                                        default => "Status: {$outcome}",
                                    })
                                    ->warning()
                                    ->send();
                            }

                            static::dispatchMemberListHeaderWidgetsRefresh($livewire);
                        }),
                    Action::make('allocate')
                        ->label('Allocate')
                        ->icon('heroicon-o-arrow-right-circle')
                        ->color('warning')
                        ->visible(
                            fn(Member $record): bool => !$record->trashed()
                            && $record->status === 'active'
                            && app(ContributionCycleService::class)->shouldShowDependentAllocationAction($record)
                        )
                        ->authorize(fn(Member $record): bool => auth()->user()?->can('update', $record) ?? false)
                        ->modalHeading(fn(): string => 'Allocate to dependents')
                        ->modalDescription(
                            'Choose the calendar month you are funding dependent cash for (arrears). Preview updates when you change the cycle.'
                        )
                        ->modalWidth('lg')
                        ->schema(fn(Member $record): array => [
                            Forms\Components\Select::make('cycle')
                                ->label('Allocation cycle')
                                ->options(fn(): array => app(ContributionCycleService::class)->allocationCycleSelectOptionsForParent($record))
                                ->required()
                                ->live()
                                ->native(false)
                                ->columnSpanFull(),
                            Forms\Components\Placeholder::make('breakdown')
                                ->label('')
                                ->content(function (Get $get) use ($record) {
                                    $key = $get('cycle');
                                    if ($key === null || $key === '') {
                                        return new HtmlString('<p class="text-sm text-gray-500 dark:text-gray-400">Select a cycle to preview.</p>');
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
                        ->fillForm(fn(Member $record): array => [
                            'cycle' => app(ContributionCycleService::class)->defaultAllocationCycleKeyForParent($record) ?? '',
                        ])
                        ->action(function (array $data, Member $record, Component $livewire): void {
                            $svc = app(ContributionCycleService::class);
                            $key = $data['cycle'] ?? null;

                            if (!is_string($key) || $key === '') {
                                Notification::make()
                                    ->title('Select an allocation cycle')
                                    ->danger()
                                    ->send();

                                return;
                            }

                            try {
                                [$month, $year] = $svc->parseContributionCycleKey($key);
                            } catch (\InvalidArgumentException) {
                                Notification::make()
                                    ->title('Invalid allocation cycle')
                                    ->danger()
                                    ->send();

                                return;
                            }

                            $result = $svc->applyDependentAllocationForParentForPeriod($record, $month, $year);
                            $body = $svc->formatAllocationResultDetailTableHtml($result['details'])->toHtml();

                            if ($result['transfers'] > 0) {
                                Notification::make()
                                    ->title('Allocation completed')
                                    ->body($body)
                                    ->success()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title('Allocation')
                                    ->body($body)
                                    ->warning()
                                    ->send();
                            }

                            static::dispatchMemberListHeaderWidgetsRefresh($livewire);
                        }),
                    Action::make('suspend')
                        ->label('Suspend')
                        ->icon('heroicon-o-pause-circle')
                        ->color('warning')
                        ->visible(
                            fn(Member $record): bool => in_array($record->status, ['active', 'delinquent'], true)
                            && !$record->trashed()
                        )
                        ->authorize(fn(Member $record): bool => auth()->user()?->can('update', $record) ?? false)
                        ->requiresConfirmation()
                        ->modalHeading('Suspend member')
                        ->modalDescription('Sets membership to Suspended. This member will not be able to sign in to the member portal until their status is changed back.')
                        ->action(function (Member $record, Component $livewire): void {
                            $record->update(['status' => 'suspended']);
                            Notification::make()
                                ->title('Member suspended')
                                ->success()
                                ->send();
                            static::dispatchMemberListHeaderWidgetsRefresh($livewire);
                        }),
                    Action::make('terminate')
                        ->label('Terminate')
                        ->icon('heroicon-o-no-symbol')
                        ->color('danger')
                        ->visible(
                            fn(Member $record): bool => $record->status !== 'terminated'
                            && !$record->trashed()
                        )
                        ->authorize(fn(Member $record): bool => auth()->user()?->can('update', $record) ?? false)
                        ->requiresConfirmation()
                        ->modalHeading('Terminate membership')
                        ->modalDescription('Ends membership permanently (status: Terminated). The person cannot use the member portal. This does not delete records or ledger history. Use Delete only when a full removal is required.')
                        ->action(function (Member $record, Component $livewire): void {
                            $record->update(['status' => 'terminated']);
                            Notification::make()
                                ->title('Membership terminated')
                                ->warning()
                                ->send();
                            static::dispatchMemberListHeaderWidgetsRefresh($livewire);
                        }),
                    DeleteAction::make()
                        ->modalDescription('Removes this member from active records: loans are safe-deleted first (ledger reversed), then bank/SMS lines, virtual accounts, the member row, and their login user (soft-deleted). Blocked while contribution records still exist — remove or soft-delete those first if you need to delete.')
                        ->using(function (Member $record): bool {
                            try {
                                app(MemberDeletionService::class)->delete($record);

                                return true;
                            } catch (\RuntimeException $e) {
                                Notification::make()
                                    ->title('Cannot delete member')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();

                                return false;
                            }
                        })
                        ->after(fn(Component $livewire) => static::dispatchMemberListHeaderWidgetsRefresh($livewire)),
                    RestoreAction::make()
                        ->after(fn(Component $livewire) => static::dispatchMemberListHeaderWidgetsRefresh($livewire)),
                    ForceDeleteAction::make()
                        ->after(fn(Component $livewire) => static::dispatchMemberListHeaderWidgetsRefresh($livewire)),
                ])
                    ->tooltip('Actions'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('suspend')
                        ->label('Suspend selected')
                        ->icon('heroicon-o-pause-circle')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Suspend selected members')
                        ->modalDescription('Sets each eligible row to Suspended (active or delinquent only). Already suspended, terminated, or trashed rows are skipped.')
                        ->authorizeIndividualRecords('update')
                        ->action(function (EloquentCollection $records, Component $livewire): void {
                            $eligible = $records->filter(
                                fn(Member $r) => !$r->trashed()
                                && in_array($r->status, ['active', 'delinquent'], true)
                            );
                            $skipped = $records->count() - $eligible->count();

                            $suspended = 0;
                            foreach ($eligible as $record) {
                                $record->update(['status' => 'suspended']);
                                $suspended++;
                            }

                            Notification::make()
                                ->title('Bulk suspend finished')
                                ->body("Suspended: {$suspended}. Skipped: {$skipped}.")
                                ->success()
                                ->send();

                            static::dispatchMemberListHeaderWidgetsRefresh($livewire);
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('terminate')
                        ->label('Terminate selected')
                        ->icon('heroicon-o-no-symbol')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Terminate selected memberships')
                        ->modalDescription('Sets each eligible row to Terminated. Rows already terminated or trashed are skipped.')
                        ->authorizeIndividualRecords('update')
                        ->action(function (EloquentCollection $records, Component $livewire): void {
                            $eligible = $records->filter(
                                fn(Member $r) => !$r->trashed() && $r->status !== 'terminated'
                            );
                            $skipped = $records->count() - $eligible->count();

                            $terminated = 0;
                            foreach ($eligible as $record) {
                                $record->update(['status' => 'terminated']);
                                $terminated++;
                            }

                            Notification::make()
                                ->title('Bulk terminate finished')
                                ->body("Terminated: {$terminated}. Skipped: {$skipped}.")
                                ->warning()
                                ->send();

                            static::dispatchMemberListHeaderWidgetsRefresh($livewire);
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('contribute')
                        ->label('Contribute (selected cycle)')
                        ->icon('heroicon-o-banknotes')
                        ->color('success')
                        ->modalHeading(fn(): string => 'Apply contributions')
                        ->modalDescription(
                            'Choose the contribution cycle. Each selected member is processed for that month: applied if they have no row yet, ' .
                            'are not loan-exempt, and have enough cash; otherwise counted as insufficient or skipped.'
                        )
                        ->modalWidth('md')
                        ->schema(fn(): array => [
                            Forms\Components\Select::make('cycle')
                                ->label('Contribution cycle')
                                ->options(fn(): array => app(ContributionCycleService::class)->contributionCycleSelectOptionsForBulk())
                                ->required()
                                ->native(false)
                                ->helperText('The same calendar month applies to every selected member.')
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

                            if (!is_string($key) || $key === '') {
                                Notification::make()
                                    ->title('Select a contribution cycle')
                                    ->danger()
                                    ->send();

                                return;
                            }

                            try {
                                [$month, $year] = $svc->parseContributionCycleKey($key);
                            } catch (\InvalidArgumentException) {
                                Notification::make()
                                    ->title('Invalid contribution cycle')
                                    ->danger()
                                    ->send();

                                return;
                            }

                            $applied = 0;
                            $insufficient = 0;
                            $skipped = 0;

                            foreach ($records as $record) {
                                if (!$record instanceof Member || $record->trashed()) {
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
                                ->title('Bulk contribute finished')
                                ->body("Period: {$period}. Applied: {$applied}. Insufficient cash: {$insufficient}. Skipped: {$skipped}.")
                                ->color($insufficient > 0 ? 'warning' : 'success')
                                ->send();

                            static::dispatchMemberListHeaderWidgetsRefresh($livewire);
                        })
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make()
                        ->modalDescription('Deletes each selected member like single delete (loans safe-deleted first, then bank/SMS and accounts). Rows that fail (e.g. contributions still present) are skipped and reported.')
                        ->using(function (DeleteBulkAction $action, $records) {
                            $svc = app(MemberDeletionService::class);
                            foreach ($records as $record) {
                                try {
                                    $svc->delete($record);
                                } catch (\Throwable $e) {
                                    $action->reportBulkProcessingFailure(message: $e->getMessage());
                                    if (!$e instanceof \RuntimeException) {
                                        report($e);
                                    }
                                }
                            }
                        })
                        ->after(fn(Component $livewire) => static::dispatchMemberListHeaderWidgetsRefresh($livewire)),
                    RestoreBulkAction::make()
                        ->after(fn(Component $livewire) => static::dispatchMemberListHeaderWidgetsRefresh($livewire)),
                    ForceDeleteBulkAction::make()
                        ->after(fn(Component $livewire) => static::dispatchMemberListHeaderWidgetsRefresh($livewire)),
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
            ->withSum('contributions', 'amount')
            ->withCount([
                'contributions as late_contributions_marked_count' => fn($q) => $q->where('is_late', true),
            ])
            ->withSum([
                'contributions as late_contributions_marked_amount' => fn($q) => $q->where('is_late', true),
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
            $parts[] = 'window.Livewire.getByName(' . $name . ').forEach(w => w.$refresh());';
        }

        $livewire->js('setTimeout(() => { ' . implode(' ', $parts) . ' }, 0)');
    }
}
