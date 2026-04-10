<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\LoanResource\Pages;
use App\Filament\Admin\Resources\LoanResource\RelationManagers\InstallmentsRelationManager;
use App\Filament\Admin\Widgets\LoanStatsWidget;
use App\Models\Account;
use App\Models\FundTier;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\LoanTier;
use App\Models\Member;
use App\Models\Setting;
use App\Notifications\LoanApprovedNotification;
use App\Notifications\LoanCancelledNotification;
use App\Notifications\LoanDisbursedNotification;
use App\Notifications\LoanEarlySettledNotification;
use App\Notifications\MembershipRejectedNotification;
use App\Services\AccountingService;
use App\Services\LoanEligibilityService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Livewire\Component;

class LoanResource extends Resource
{
    protected static ?string $model = Loan::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-currency-dollar';

    protected static ?int $navigationSort = 3;

    public static function getNavigationGroup(): ?string
    {
        return __('app.nav.group.finance');
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) Loan::where('status', 'pending')->count() ?: null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'warning';
    }

    // =========================================================================
    // Form (used by Create page)
    // =========================================================================

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Loan Request')->schema([
                Forms\Components\Select::make('member_id')
                    ->label('Member')
                    ->options(fn() => Member::active()->with('user')->get()
                        ->mapWithKeys(fn($m) => [$m->id => "{$m->member_number} – {$m->user->name}"]))
                    ->searchable()->required()->live(),

                Forms\Components\Placeholder::make('member_eligibility')
                    ->label('Member Eligibility')
                    ->content(function ($get) {
                        $memberId = $get('member_id');
                        if (!$memberId) {
                            return '— Select a member to see their eligibility status.';
                        }
                        $member = Member::with('accounts')->find($memberId);
                        if (!$member) {
                            return '—';
                        }
                        $svc = app(LoanEligibilityService::class);
                        $ctx = $svc->context($member);
                        if ($ctx['eligible']) {
                            return '✅ Eligible '
                                . '| Fund balance: SAR ' . number_format($ctx['fund_balance'], 2)
                                . ' | Max loan: SAR ' . number_format($ctx['max_loan_amount']);
                        }

                        return '⚠ Not eligible: ' . $ctx['reason'];
                    }),

                Forms\Components\TextInput::make('amount_requested')
                    ->label('Requested Amount (SAR)')
                    ->numeric()->prefix('SAR')->required()
                    ->minValue(1000)
                    ->maxValue(function ($get) {
                        $memberId = $get('member_id');
                        if (!$memberId) {
                            return null; // uncapped until a member is selected
                        }
                        $member = Member::with('accounts')->find($memberId);
                        if (!$member) {
                            return null;
                        }
                        return app(LoanEligibilityService::class)->maxLoanAmount($member);
                    })
                    ->helperText(function ($get) {
                        $memberId = $get('member_id');
                        if (!$memberId) {
                            return 'Select a member first to see the maximum loan amount.';
                        }
                        $member = Member::with('accounts')->find($memberId);
                        if (!$member) {
                            return null;
                        }
                        $max = app(LoanEligibilityService::class)->maxLoanAmount($member);
                        $fundBal = (float) ($member->fundAccount()?->balance ?? 0);
                        return 'Max: SAR ' . number_format($max) . ' (2× fund balance of SAR ' . number_format($fundBal) . ')';
                    }),
                Forms\Components\Toggle::make('is_emergency')
                    ->label('Emergency Loan')
                    ->helperText('Assigns this loan to the Emergency fund tier upon approval.')
                    ->default(false),
                Forms\Components\Textarea::make('purpose')->required()->columnSpanFull(),
            ])->columns(2),

            Section::make('Guarantor & Witnesses')->schema([
                Forms\Components\Select::make('guarantor_member_id')
                    ->label('Guarantor Member')
                    ->options(fn() => Member::active()->with('user')->get()
                        ->mapWithKeys(fn($m) => [$m->id => "{$m->member_number} – {$m->user->name}"]))
                    ->searchable()->nullable()
                    ->helperText('Must be an active member with income.'),
                Forms\Components\TextInput::make('witness1_name')->label('Witness 1 — Name')->maxLength(255),
                Forms\Components\TextInput::make('witness1_phone')->label('Witness 1 — Phone')->tel()->maxLength(50),
                Forms\Components\TextInput::make('witness2_name')->label('Witness 2 — Name')->maxLength(255),
                Forms\Components\TextInput::make('witness2_phone')->label('Witness 2 — Phone')->tel()->maxLength(50),
            ])->columns(2),
        ]);
    }

    // =========================================================================
    // Table
    // =========================================================================

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('queue_position')->label('Q#')->sortable()->placeholder('—'),
                Tables\Columns\IconColumn::make('is_emergency')
                    ->label('Emg.')
                    ->boolean()
                    ->trueIcon('heroicon-o-bolt')
                    ->falseIcon(null)
                    ->trueColor('danger')
                    ->tooltip(fn(Loan $r) => $r->is_emergency ? 'Emergency Loan' : null),
                Tables\Columns\TextColumn::make('loanTier.label')->label('Tier')->placeholder('—'),
                Tables\Columns\TextColumn::make('member.member_number')->label('Member #')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('member.user.name')->label('Member')->searchable(),
                Tables\Columns\TextColumn::make('amount_requested')->label('Requested')->money('SAR'),
                Tables\Columns\TextColumn::make('amount_approved')->label('Approved')->money('SAR')->placeholder('—'),
                Tables\Columns\TextColumn::make('installments_count')
                    ->label('Months')
                    ->description(fn(Loan $r) => $r->loanTier
                        ? 'SAR ' . number_format($r->loanTier->min_monthly_installment) . '/mo'
                        : null),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->color(fn(string $state) => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'info',
                        'active' => 'success',
                        'completed' => 'gray',
                        'early_settled' => 'success',
                        'rejected' => 'danger',
                        'cancelled' => 'gray',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('late_repayment_count')->label('Late #')
                    ->badge()->color(fn($state) => $state > 0 ? 'warning' : 'success'),
                Tables\Columns\TextColumn::make('applied_at')->dateTime('d M Y')->sortable(),
            ])
            ->defaultSort('applied_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'pending' => 'Pending',
                    'approved' => 'Approved',
                    'active' => 'Active',
                    'completed' => 'Completed',
                    'early_settled' => 'Early Settled',
                    'rejected' => 'Rejected',
                    'cancelled' => 'Cancelled',
                ]),
                Tables\Filters\SelectFilter::make('loan_tier_id')->label('Tier')
                    ->options(LoanTier::pluck('label', 'id')),
                Tables\Filters\SelectFilter::make('fund_tier_id')->label('Fund tier')
                    ->options(FundTier::query()->orderBy('label')->pluck('label', 'id')),
                Tables\Filters\SelectFilter::make('member_id')
                    ->label('Member')
                    ->searchable()
                    ->options(fn() => Member::with('user')->orderBy('member_number')->get()
                        ->mapWithKeys(fn(Member $m) => [$m->id => "{$m->member_number} – {$m->user->name}"])),
                Tables\Filters\TernaryFilter::make('is_emergency')
                    ->label('Emergency'),
                Tables\Filters\TernaryFilter::make('disbursed')
                    ->label('Disbursed')
                    ->trueLabel('Disbursed')
                    ->falseLabel('Not disbursed')
                    ->queries(
                        true: fn($q) => $q->whereNotNull('disbursed_at'),
                        false: fn($q) => $q->whereNull('disbursed_at'),
                    ),
                Tables\Filters\Filter::make('applied_at')
                    ->schema([
                        Forms\Components\DatePicker::make('from')->label('Applied from'),
                        Forms\Components\DatePicker::make('until')->label('Applied until'),
                    ])
                    ->columns(2)
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'] ?? null, fn($q) => $q->whereDate('applied_at', '>=', $data['from']))
                            ->when($data['until'] ?? null, fn($q) => $q->whereDate('applied_at', '<=', $data['until']));
                    }),
                Tables\Filters\Filter::make('amount_approved')
                    ->schema([
                        Forms\Components\TextInput::make('min')->label('Min approved (SAR)')->numeric(),
                        Forms\Components\TextInput::make('max')->label('Max approved (SAR)')->numeric(),
                    ])
                    ->columns(2)
                    ->query(function ($query, array $data) {
                        return $query
                            ->when(filled($data['min'] ?? null), fn($q) => $q->where('amount_approved', '>=', $data['min']))
                            ->when(filled($data['max'] ?? null), fn($q) => $q->where('amount_approved', '<=', $data['max']));
                    }),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),

                // ── APPROVE ──
                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn(Loan $r) => $r->status === 'pending')
                    ->fillForm(fn(Loan $r) => [
                        'amount_approved' => $r->amount_requested,
                        'is_emergency' => $r->is_emergency,
                    ])
                    ->schema(fn(Loan $record) => [
                        Forms\Components\TextInput::make('amount_approved')
                            ->label('Approved Amount (SAR)')
                            ->numeric()->prefix('SAR')->required()
                            ->helperText(
                                'Loan tier and fund tier are auto-assigned from the requested amount (SAR '
                                . number_format((float) $record->amount_requested)
                                . '). Adjust this figure only for the disbursed amount.'
                            ),

                        Forms\Components\Toggle::make('is_emergency')
                            ->label('Emergency Loan')
                            ->helperText('Emergency loans bypass the standard loan-tier queue and are assigned to the Emergency fund tier.')
                            ->default(false),

                        Forms\Components\Placeholder::make('repayment_preview')
                            ->label('Loan Schedule & Tier Assignment')
                            ->content(function () use ($record) {
                                $amount   = (float) $record->amount_requested;
                                $fundBal  = (float) ($record->member->fundAccount()?->balance ?? 0);
                                $loanTier = LoanTier::forAmount($amount);
                                $threshold = Setting::loanSettlementThreshold();

                                if (!$loanTier) {
                                    return new HtmlString(
                                        '<div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-400">'
                                        . '⚠ No loan tier covers SAR ' . e(number_format($amount))
                                        . '. Adjust Loan Tiers in Settings before approving.'
                                        . '</div>'
                                    );
                                }

                                $minInstall   = (float) $loanTier->min_monthly_installment;
                                $memberPortion = min($fundBal, $amount);
                                $masterPortion = $amount - $memberPortion;
                                $settleAmt    = $amount * $threshold;
                                $count        = Loan::computeInstallmentsCount($amount, $fundBal, $minInstall, $threshold);

                                $fundTier      = FundTier::forLoanTier($loanTier->id);
                                $fundTierLabel = $fundTier
                                    ? $fundTier->label . ' (SAR ' . number_format($fundTier->available_amount) . ' available)'
                                    : '⚠ No matching fund tier';

                                $row = fn(string $label, string $value, bool $highlight = false): string =>
                                    '<tr class="border-b border-gray-100 last:border-0 dark:border-white/10">'
                                    . '<td class="py-2.5 pl-3 pr-3 text-gray-500 dark:text-gray-400">' . e($label) . '</td>'
                                    . '<td class="py-2.5 pr-3 text-right tabular-nums ' . ($highlight ? 'font-semibold text-gray-950 dark:text-white' : 'text-gray-700 dark:text-gray-300') . '">' . e($value) . '</td>'
                                    . '</tr>';

                                $loanTierValue = $loanTier->label
                                    . ' (SAR ' . number_format((float) $loanTier->min_amount)
                                    . ' – SAR ' . number_format((float) $loanTier->max_amount) . ')';
                                $rows = $row('Loan tier (from requested amount)', $loanTierValue)
                                    . $row('Fund tier', $fundTierLabel)
                                    . $row('Member fund balance', 'SAR ' . number_format($fundBal, 2))
                                    . $row('Member portion', 'SAR ' . number_format($memberPortion, 2))
                                    . $row('Fund (master) portion', 'SAR ' . number_format($masterPortion, 2))
                                    . $row('Settlement top-up (' . ($threshold * 100) . '%)', 'SAR ' . number_format($settleAmt, 2))
                                    . $row('Monthly installment', 'SAR ' . number_format($minInstall, 2))
                                    . $row('Repayment period', $count . ' months', true);

                                $table = '<div class="overflow-x-auto rounded-lg border border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900/40">'
                                    . '<table class="w-full min-w-[20rem] text-sm">'
                                    . '<thead><tr class="border-b border-gray-200 bg-gray-50 dark:border-white/10 dark:bg-white/5">'
                                    . '<th scope="col" class="py-2.5 pl-3 pr-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Field</th>'
                                    . '<th scope="col" class="py-2.5 pr-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Value</th>'
                                    . '</tr></thead>'
                                    . '<tbody>' . $rows . '</tbody>'
                                    . '</table></div>';

                                $note = '<p class="text-xs leading-relaxed text-gray-500 dark:text-gray-400">'
                                    . 'Tier assignment is based on the <span class="font-medium text-gray-700 dark:text-gray-300">requested amount</span>. '
                                    . 'Repayment period is re-computed at disbursement using the then-current fund balance.'
                                    . '</p>';

                                return new HtmlString('<div class="space-y-3">' . $table . $note . '</div>');
                            })
                            ->columnSpanFull(),
                    ])
                    ->action(function (Loan $record, array $data, Component $livewire) {
                        $amount          = (float) $data['amount_approved'];
                        $requestedAmount = (float) $record->amount_requested;
                        $isEmergency     = (bool) ($data['is_emergency'] ?? false);
                        $threshold       = Setting::loanSettlementThreshold();

                        // Tier assignment: always based on the requested amount exclusively.
                        $loanTier = LoanTier::forAmount($requestedAmount);

                        // Fund tier: emergency flag overrides, otherwise derived from loan tier.
                        $fundTier = $isEmergency
                            ? FundTier::emergency()
                            : ($loanTier ? FundTier::forLoanTier($loanTier->id) : null);

                        if (!$fundTier) {
                            Notification::make()
                                ->title('Cannot Approve')
                                ->body('No active fund tier found for this loan. Configure Fund Tiers in Settings.')
                                ->danger()->send();

                            return;
                        }

                        // Compute installments_count at approval time (using current fund balance as estimate)
                        $fundBal = (float) ($record->member->fundAccount()?->balance ?? 0);
                        $minInstall = (float) ($loanTier?->min_monthly_installment ?? 1000);
                        $count = Loan::computeInstallmentsCount($amount, $fundBal, $minInstall, $threshold);
                        $position = $fundTier->nextQueuePosition();

                        $record->update([
                            'status' => 'approved',
                            'amount_approved' => $amount,
                            'is_emergency' => $isEmergency,
                            'installments_count' => $count,
                            'loan_tier_id' => $loanTier?->id,
                            'fund_tier_id' => $fundTier->id,
                            'queue_position' => $position,
                            'approved_at' => now(),
                            'approved_by_id' => auth()->id(),
                            'settlement_threshold' => $threshold,
                        ]);

                        $tierInfo = $isEmergency
                            ? 'Emergency fund tier'
                            : "{$fundTier->label} (queue position #{$position})";

                        try {
                            $record->member->user->notify(new LoanApprovedNotification(
                                amount: $amount,
                                installments: $count,
                                dueDate: now()->addMonths($count)->format('d M Y')
                            ));
                        } catch (\Throwable) {
                        }

                        Notification::make()
                            ->title('Loan Approved')
                            ->body("Assigned to {$tierInfo}. Repayment: {$count} months × SAR " . number_format($minInstall) . '/month.')
                            ->success()->send();

                        static::dispatchLoanListHeaderWidgetsRefresh($livewire);
                    }),

                // ── DISBURSE ──
                Action::make('disburse')
                    ->label('Disburse')
                    ->icon('heroicon-o-banknotes')
                    ->color('primary')
                    ->visible(fn(Loan $r) => $r->status === 'approved')
                    ->requiresConfirmation()
                    ->modalHeading(fn(Loan $r) => 'Disburse SAR ' . number_format($r->amount_approved, 2) . " to {$r->member->user->name}?")
                    ->modalDescription(function (Loan $r) {
                        $fundBal = (float) ($r->member->fundAccount()?->balance ?? 0);
                        $masterBal = (float) (Account::masterFund()?->balance ?? 0);
                        $minInstall = (float) ($r->loanTier?->min_monthly_installment ?? 1000);
                        $threshold = (float) $r->settlement_threshold;
                        $count = Loan::computeInstallmentsCount(
                            (float) $r->amount_approved,
                            $fundBal,
                            $minInstall,
                            $threshold
                        );
                        $memberPortion = min($fundBal, (float) $r->amount_approved);
                        $masterPortion = (float) $r->amount_approved - $memberPortion;

                        return 'Member fund balance: SAR ' . number_format($fundBal, 2)
                            . ' | Master fund: SAR ' . number_format($masterBal, 2)
                            . "\nMember portion: SAR " . number_format($memberPortion, 2)
                            . ' | Fund portion: SAR ' . number_format($masterPortion, 2)
                            . "\nInstallment: SAR " . number_format($minInstall) . "/month × {$count} months";
                    })
                    ->action(function (Loan $record, Component $livewire) {
                        $disbursedAt = now();
                        $exemption = Loan::computeExemptionAndFirstRepayment($disbursedAt);
                        $exemption = Loan::adjustFirstRepaymentIfContributionAlreadyMade($record->member, $exemption);

                        // Recompute at actual disbursement time with current fund balance
                        $fundBal = (float) ($record->member->fundAccount()?->balance ?? 0);
                        $amount = (float) $record->amount_approved;
                        $minInstall = (float) ($record->loanTier?->min_monthly_installment ?? 1000);
                        $threshold = (float) $record->settlement_threshold;
                        $count = Loan::computeInstallmentsCount($amount, $fundBal, $minInstall, $threshold);

                        DB::transaction(function () use ($record, $disbursedAt, $exemption, $count, $minInstall) {
                            // Finalize installments_count on the loan record
                            $record->update([
                                'status' => 'active',
                                'installments_count' => $count,
                                'disbursed_at' => $disbursedAt,
                                'due_date' => $disbursedAt->copy()->addMonths($count)->toDateString(),
                            ] + $exemption);

                            // Build installment schedule — every installment = min_monthly_installment
                            $startDate = Carbon::create(
                                $exemption['first_repayment_year'],
                                $exemption['first_repayment_month'],
                                5
                            );

                            for ($i = 1; $i <= $count; $i++) {
                                LoanInstallment::create([
                                    'loan_id' => $record->id,
                                    'installment_number' => $i,
                                    'amount' => $minInstall,
                                    'due_date' => $startDate->copy()->addMonths($i - 1)->toDateString(),
                                    'status' => 'pending',
                                ]);
                            }

                            // Post accounting (member portion + master portion split)
                            app(AccountingService::class)->postLoanDisbursement($record);
                        });

                        try {
                            $record->refresh();
                            $record->member->user->notify(new LoanDisbursedNotification($record));
                        } catch (\Throwable) {
                        }

                        Notification::make()
                            ->title('Loan Disbursed')
                            ->body("{$count} installments of SAR " . number_format($minInstall) . '/month created. First repayment: ' . ($exemption['first_repayment_month'] . '/' . $exemption['first_repayment_year']))
                            ->success()->send();

                        static::dispatchLoanListHeaderWidgetsRefresh($livewire);
                    }),

                // ── REJECT ──
                Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn(Loan $r) => $r->status === 'pending')
                    ->schema([
                        Forms\Components\Textarea::make('rejection_reason')->label('Rejection Reason')->required(),
                    ])
                    ->action(function (Loan $record, array $data, Component $livewire) {
                        $record->update(['status' => 'rejected', 'rejection_reason' => $data['rejection_reason']]);
                        try {
                            $record->member->user->notify(new MembershipRejectedNotification($data['rejection_reason']));
                        } catch (\Throwable) {
                        }
                        Notification::make()->title('Loan Rejected')->warning()->send();
                        static::dispatchLoanListHeaderWidgetsRefresh($livewire);
                    }),

                // ── CANCEL ──
                Action::make('cancel')
                    ->label('Cancel')
                    ->icon('heroicon-o-trash')
                    ->color('gray')
                    ->visible(fn(Loan $r) => in_array($r->status, ['pending', 'approved']))
                    ->schema([
                        Forms\Components\Textarea::make('cancellation_reason')->label('Cancellation Reason')->nullable(),
                    ])
                    ->action(function (Loan $record, array $data, Component $livewire) {
                        $record->update(['status' => 'cancelled', 'cancellation_reason' => $data['cancellation_reason'] ?? null]);
                        try {
                            $record->member->user->notify(new LoanCancelledNotification($record, $data['cancellation_reason'] ?? ''));
                        } catch (\Throwable) {
                        }
                        Notification::make()->title('Loan Cancelled')->send();
                        static::dispatchLoanListHeaderWidgetsRefresh($livewire);
                    }),

                // ── EARLY SETTLE ──
                Action::make('early_settle')
                    ->label('Early Settle')
                    ->icon('heroicon-o-check-badge')
                    ->color('info')
                    ->visible(fn(Loan $r) => $r->status === 'active')
                    ->requiresConfirmation()
                    ->modalHeading('Confirm Early Settlement')
                    ->modalDescription(fn(Loan $r) => 'Remaining balance: SAR ' . number_format($r->remaining_amount, 2) . '. All pending installments will be marked paid.')
                    ->action(function (Loan $record, Component $livewire) {
                        DB::transaction(function () use ($record) {
                            $member = $record->member;
                            $pending = $record->installments()->whereIn('status', ['pending', 'overdue'])->get();

                            foreach ($pending as $inst) {
                                app(AccountingService::class)->debitCashForRepayment($member, $inst);
                                $inst->update(['status' => 'paid', 'paid_at' => now()]);
                            }

                            $record->update(['status' => 'early_settled', 'settled_at' => now()]);
                        });

                        try {
                            $record->member->user->notify(new LoanEarlySettledNotification($record));
                        } catch (\Throwable) {
                        }

                        Notification::make()->title('Loan Early Settled')->success()->send();
                        static::dispatchLoanListHeaderWidgetsRefresh($livewire);
                    }),

                DeleteAction::make()
                    ->modalDescription(
                        'Reverses all ledger postings for this loan (disbursement, repayments, and any cash or guarantor lines tied to its installments), then soft-deletes installments, the loan account, and the loan. Restoring a loan from the trash does not rebuild ledger postings — use only when you understand the impact.'
                    )
                    ->using(function (Loan $record) {
                        app(AccountingService::class)->safeDeleteLoan($record);

                        return true;
                    })
                    ->after(fn(Component $livewire) => static::dispatchLoanListHeaderWidgetsRefresh($livewire)),
                RestoreAction::make()
                    ->after(fn(Component $livewire) => static::dispatchLoanListHeaderWidgetsRefresh($livewire)),
                ForceDeleteAction::make()
                    ->after(fn(Component $livewire) => static::dispatchLoanListHeaderWidgetsRefresh($livewire)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->modalDescription(
                            'Each selected loan is deleted like a single delete: ledger lines are reversed, installments and the loan account removed. Failures are reported; other rows still process.'
                        )
                        ->using(function (DeleteBulkAction $action, $records) {
                            $accounting = app(AccountingService::class);
                            foreach ($records as $record) {
                                try {
                                    $accounting->safeDeleteLoan($record);
                                } catch (\Throwable $e) {
                                    $action->reportBulkProcessingFailure(message: $e->getMessage());
                                    report($e);
                                }
                            }
                        }),
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Loan request')
                    ->icon('heroicon-o-banknotes')
                    ->schema([
                        TextEntry::make('borrower')
                            ->label('Member')
                            ->state(fn(Loan $record): ?string => $record->member
                                ? "{$record->member->member_number} – {$record->member->user->name}"
                                : null)
                            ->url(function (Loan $record): ?string {
                                $member = $record->member;
                                if ($member === null || !MemberResource::canView($member)) {
                                    return null;
                                }

                                return MemberResource::getUrl('view', ['record' => $member]);
                            })
                            ->color('primary')
                            ->weight(FontWeight::Medium),
                        TextEntry::make('member_eligibility')
                            ->label('Member eligibility')
                            ->state(function (Loan $record): string {
                                $member = $record->member;
                                if ($member === null) {
                                    return '—';
                                }
                                $ctx = app(LoanEligibilityService::class)->context($member);
                                if ($ctx['eligible']) {
                                    return '✅ Eligible '
                                        . '| Fund balance: SAR ' . number_format($ctx['fund_balance'], 2)
                                        . ' | Max loan: SAR ' . number_format($ctx['max_loan_amount']);
                                }

                                return '⚠ Not eligible: ' . $ctx['reason'];
                            })
                            ->columnSpanFull(),
                        TextEntry::make('status')
                            ->badge()
                            ->color(fn(string $state): string => match ($state) {
                                'pending' => 'warning',
                                'approved' => 'info',
                                'active' => 'success',
                                'completed' => 'gray',
                                'early_settled' => 'success',
                                'rejected' => 'danger',
                                'cancelled' => 'gray',
                                default => 'gray',
                            }),
                        TextEntry::make('amount_requested')
                            ->label('Requested amount')
                            ->money('SAR'),
                        TextEntry::make('amount_approved')
                            ->label('Approved amount')
                            ->money('SAR')
                            ->placeholder('—'),
                        TextEntry::make('installments_count')
                            ->label('Installments (months)'),
                        TextEntry::make('is_emergency')
                            ->label('Emergency loan')
                            ->formatStateUsing(fn(?bool $state): string => $state ? 'Yes' : 'No'),
                        TextEntry::make('purpose')
                            ->columnSpanFull(),
                    ])->columns(2),
                Section::make('Fund & schedule')
                    ->icon('heroicon-o-calendar-days')
                    ->schema([
                        TextEntry::make('loanTier.label')
                            ->label('Loan tier')
                            ->placeholder('—'),
                        TextEntry::make('fundTier.label')
                            ->label('Fund tier')
                            ->placeholder('—'),
                        TextEntry::make('queue_position')
                            ->label('Queue #')
                            ->placeholder('—'),
                        TextEntry::make('applied_at')
                            ->dateTime('d M Y H:i'),
                        TextEntry::make('approved_at')
                            ->dateTime('d M Y H:i')
                            ->placeholder('—'),
                        TextEntry::make('disbursed_at')
                            ->dateTime('d M Y H:i')
                            ->placeholder('—'),
                        TextEntry::make('due_date')
                            ->date('d M Y')
                            ->placeholder('—'),
                        TextEntry::make('settled_at')
                            ->dateTime('d M Y H:i')
                            ->placeholder('—'),
                        TextEntry::make('member_portion')
                            ->label('Member portion')
                            ->money('SAR')
                            ->placeholder('—'),
                        TextEntry::make('master_portion')
                            ->label('Master / fund portion')
                            ->money('SAR')
                            ->placeholder('—'),
                        TextEntry::make('repaid_to_master')
                            ->label('Repaid (master track)')
                            ->money('SAR'),
                    ])->columns(2)
                    ->collapsible(),
                Section::make('Guarantor & witnesses')
                    ->icon('heroicon-o-user-group')
                    ->schema([
                        TextEntry::make('guarantor_display')
                            ->label('Guarantor')
                            ->state(fn(Loan $record): ?string => $record->guarantor
                                ? "{$record->guarantor->member_number} – {$record->guarantor->user->name}"
                                : null)
                            ->url(function (Loan $record): ?string {
                                $guarantor = $record->guarantor;
                                if ($guarantor === null || !MemberResource::canView($guarantor)) {
                                    return null;
                                }

                                return MemberResource::getUrl('view', ['record' => $guarantor]);
                            })
                            ->color('primary')
                            ->weight(FontWeight::Medium)
                            ->placeholder('—'),
                        TextEntry::make('witness1_name')
                            ->label('Witness 1 — name')
                            ->placeholder('—'),
                        TextEntry::make('witness1_phone')
                            ->label('Witness 1 — phone')
                            ->placeholder('—'),
                        TextEntry::make('witness2_name')
                            ->label('Witness 2 — name')
                            ->placeholder('—'),
                        TextEntry::make('witness2_phone')
                            ->label('Witness 2 — phone')
                            ->placeholder('—'),
                    ])->columns(2),
            ]);
    }

    public static function getRelations(): array
    {
        return [InstallmentsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLoans::route('/'),
            'create' => Pages\CreateLoan::route('/create'),
            'view' => Pages\ViewLoan::route('/{record}'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->withTrashed();
    }

    /**
     * Refresh list-page header widgets ({@see LoanStatsWidget}) after table mutations.
     */
    public static function dispatchLoanListHeaderWidgetsRefresh(?Component $livewire): void
    {
        if ($livewire === null) {
            return;
        }

        $name = json_encode(
            app('livewire.factory')->resolveComponentName(LoanStatsWidget::class),
            JSON_THROW_ON_ERROR
        );

        $livewire->js('setTimeout(() => { window.Livewire.getByName(' . $name . ').forEach(w => w.$refresh()); }, 0)');
    }
}
