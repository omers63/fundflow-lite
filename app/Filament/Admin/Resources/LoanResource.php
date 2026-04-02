<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\LoanResource\Pages;
use App\Filament\Admin\Resources\LoanResource\RelationManagers\InstallmentsRelationManager;
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
use App\Services\AccountingService;
use App\Services\LoanEligibilityService;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class LoanResource extends Resource
{
    protected static ?string $model = Loan::class;
    protected static string|\BackedEnum|null $navigationIcon = null;
    protected static ?int $navigationSort = 2;

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
                    ->options(fn () => Member::active()->with('user')->get()
                        ->mapWithKeys(fn ($m) => [$m->id => "{$m->member_number} – {$m->user->name}"]))
                    ->searchable()->required(),
                Forms\Components\TextInput::make('amount_requested')
                    ->label('Requested Amount (SAR)')->numeric()->prefix('SAR')->required(),
                Forms\Components\Select::make('installments_count')
                    ->label('Repayment Period')
                    ->options(array_combine(range(1, 36), array_map(fn ($n) => "{$n} months", range(1, 36))))
                    ->default(12)->required(),
                Forms\Components\Textarea::make('purpose')->required()->columnSpanFull(),
            ])->columns(2),

            Section::make('Guarantor & Witnesses')->schema([
                Forms\Components\Select::make('guarantor_member_id')
                    ->label('Guarantor Member')
                    ->options(fn () => Member::active()->with('user')->get()
                        ->mapWithKeys(fn ($m) => [$m->id => "{$m->member_number} – {$m->user->name}"]))
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
                Tables\Columns\TextColumn::make('loanTier.label')->label('Tier')->placeholder('—'),
                Tables\Columns\TextColumn::make('member.member_number')->label('Member #')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('member.user.name')->label('Member')->searchable(),
                Tables\Columns\TextColumn::make('amount_requested')->label('Requested')->money('SAR'),
                Tables\Columns\TextColumn::make('amount_approved')->label('Approved')->money('SAR')->placeholder('—'),
                Tables\Columns\TextColumn::make('installments_count')->label('Months'),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->color(fn (string $state) => match ($state) {
                        'pending'       => 'warning',
                        'approved'      => 'info',
                        'active'        => 'success',
                        'completed'     => 'gray',
                        'early_settled' => 'success',
                        'rejected'      => 'danger',
                        'cancelled'     => 'gray',
                        default         => 'gray',
                    }),
                Tables\Columns\TextColumn::make('late_repayment_count')->label('Late #')
                    ->badge()->color(fn ($state) => $state > 0 ? 'warning' : 'success'),
                Tables\Columns\TextColumn::make('applied_at')->dateTime('d M Y')->sortable(),
            ])
            ->defaultSort('applied_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'pending' => 'Pending', 'approved' => 'Approved', 'active' => 'Active',
                    'completed' => 'Completed', 'early_settled' => 'Early Settled',
                    'rejected' => 'Rejected', 'cancelled' => 'Cancelled',
                ]),
                Tables\Filters\SelectFilter::make('loan_tier_id')->label('Tier')
                    ->options(LoanTier::pluck('label', 'id')),
            ])
            ->recordActions([
                ViewAction::make(),

                // ── APPROVE ──
                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Loan $r) => $r->status === 'pending')
                    ->fillForm(fn (Loan $r) => [
                        'amount_approved'  => $r->amount_requested,
                        'installments_count' => $r->installments_count,
                    ])
                    ->schema([
                        Forms\Components\TextInput::make('amount_approved')
                            ->label('Approved Amount (SAR)')->numeric()->prefix('SAR')->required(),
                        Forms\Components\Select::make('installments_count')
                            ->label('Installments')
                            ->options(array_combine(range(1, 36), array_map(fn ($n) => "{$n} months", range(1, 36))))
                            ->required(),
                        Forms\Components\Select::make('fund_tier_id')
                            ->label('Assign to Fund Tier')
                            ->options(FundTier::where('is_active', true)->get()
                                ->mapWithKeys(fn ($ft) => [$ft->id => "{$ft->label} (available: SAR " . number_format($ft->available_amount) . ")"]))
                            ->required(),
                    ])
                    ->action(function (Loan $record, array $data) {
                        $amount   = (float) $data['amount_approved'];
                        $tier     = LoanTier::forAmount($amount);
                        $fundTier = FundTier::find($data['fund_tier_id']);
                        $position = $fundTier?->nextQueuePosition() ?? 1;

                        $record->update([
                            'status'           => 'approved',
                            'amount_approved'  => $amount,
                            'installments_count' => $data['installments_count'],
                            'loan_tier_id'     => $tier?->id,
                            'fund_tier_id'     => $data['fund_tier_id'],
                            'queue_position'   => $position,
                            'approved_at'      => now(),
                            'approved_by_id'   => auth()->id(),
                            'settlement_threshold' => Setting::loanSettlementThreshold(),
                        ]);

                        try {
                            $record->member->user->notify(new LoanApprovedNotification(
                                amount: $amount,
                                installments: $data['installments_count'],
                                dueDate: now()->addMonths($data['installments_count'])->format('d M Y')
                            ));
                        } catch (\Throwable) {}

                        Notification::make()->title('Loan Approved')->success()->send();
                    }),

                // ── DISBURSE ──
                Action::make('disburse')
                    ->label('Disburse')
                    ->icon('heroicon-o-banknotes')
                    ->color('primary')
                    ->visible(fn (Loan $r) => $r->status === 'approved')
                    ->requiresConfirmation()
                    ->modalHeading(fn (Loan $r) => "Disburse SAR " . number_format($r->amount_approved, 2) . " to {$r->member->user->name}?")
                    ->modalDescription(fn (Loan $r) => "Member fund balance: SAR " . number_format($r->member->fundAccount()?->balance ?? 0, 2) . " | Master fund balance: SAR " . number_format(\App\Models\Account::masterFund()?->balance ?? 0, 2))
                    ->action(function (Loan $record) {
                        $disbursedAt = now();
                        $exemption   = Loan::computeExemptionAndFirstRepayment($disbursedAt);

                        // Create installments
                        $count      = $record->installments_count;
                        $minInstall = (float) ($record->loanTier?->min_monthly_installment ?? 1000);
                        $base       = max($minInstall, round($record->amount_approved / $count, 2));
                        $remainder  = round($record->amount_approved - ($base * $count), 2);

                        DB::transaction(function () use ($record, $disbursedAt, $exemption, $count, $base, $remainder) {
                            $record->update([
                                'status'       => 'active',
                                'disbursed_at' => $disbursedAt,
                                'due_date'     => $disbursedAt->copy()->addMonths($count)->toDateString(),
                            ] + $exemption);

                            // Build installment schedule starting from first_repayment_month
                            $startDate = \Carbon\Carbon::create(
                                $exemption['first_repayment_year'],
                                $exemption['first_repayment_month'],
                                5
                            );

                            for ($i = 1; $i <= $count; $i++) {
                                $amount = ($i === $count) ? round($base + $remainder, 2) : $base;
                                LoanInstallment::create([
                                    'loan_id'            => $record->id,
                                    'installment_number' => $i,
                                    'amount'             => $amount,
                                    'due_date'           => $startDate->copy()->addMonths($i - 1)->toDateString(),
                                    'status'             => 'pending',
                                ]);
                            }

                            // Post accounting (member + master fund split)
                            app(AccountingService::class)->postLoanDisbursement($record);
                        });

                        try {
                            $record->refresh();
                            $record->member->user->notify(new LoanDisbursedNotification($record));
                        } catch (\Throwable) {}

                        Notification::make()->title('Loan Disbursed')->body("Installments created. First repayment: " . ($exemption['first_repayment_month'] . '/' . $exemption['first_repayment_year']))->success()->send();
                    }),

                // ── REJECT ──
                Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Loan $r) => $r->status === 'pending')
                    ->schema([
                        Forms\Components\Textarea::make('rejection_reason')->label('Rejection Reason')->required(),
                    ])
                    ->action(function (Loan $record, array $data) {
                        $record->update(['status' => 'rejected', 'rejection_reason' => $data['rejection_reason']]);
                        try {
                            $record->member->user->notify(new \App\Notifications\MembershipRejectedNotification($data['rejection_reason']));
                        } catch (\Throwable) {}
                        Notification::make()->title('Loan Rejected')->warning()->send();
                    }),

                // ── CANCEL ──
                Action::make('cancel')
                    ->label('Cancel')
                    ->icon('heroicon-o-trash')
                    ->color('gray')
                    ->visible(fn (Loan $r) => in_array($r->status, ['pending', 'approved']))
                    ->schema([
                        Forms\Components\Textarea::make('cancellation_reason')->label('Cancellation Reason')->nullable(),
                    ])
                    ->action(function (Loan $record, array $data) {
                        $record->update(['status' => 'cancelled', 'cancellation_reason' => $data['cancellation_reason'] ?? null]);
                        try {
                            $record->member->user->notify(new LoanCancelledNotification($record, $data['cancellation_reason'] ?? ''));
                        } catch (\Throwable) {}
                        Notification::make()->title('Loan Cancelled')->send();
                    }),

                // ── EARLY SETTLE ──
                Action::make('early_settle')
                    ->label('Early Settle')
                    ->icon('heroicon-o-check-badge')
                    ->color('info')
                    ->visible(fn (Loan $r) => $r->status === 'active')
                    ->requiresConfirmation()
                    ->modalHeading('Confirm Early Settlement')
                    ->modalDescription(fn (Loan $r) => "Remaining balance: SAR " . number_format($r->remaining_amount, 2) . ". All pending installments will be marked paid.")
                    ->action(function (Loan $record) {
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
                        } catch (\Throwable) {}

                        Notification::make()->title('Loan Early Settled')->success()->send();
                    }),
            ]);
    }

    public static function getRelations(): array
    {
        return [InstallmentsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListLoans::route('/'),
            'create' => Pages\CreateLoan::route('/create'),
            'view'   => Pages\ViewLoan::route('/{record}'),
        ];
    }
}
