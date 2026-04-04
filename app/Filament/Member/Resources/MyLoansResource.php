<?php

namespace App\Filament\Member\Resources;

use App\Filament\Member\Resources\MyLoansResource\Pages;
use App\Models\Loan;
use App\Models\LoanTier;
use App\Models\Member;
use App\Models\Setting;
use App\Notifications\LoanCancelledNotification;
use App\Notifications\LoanSubmittedNotification;
use App\Services\LoanEligibilityService;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class MyLoansResource extends Resource
{
    protected static ?string $model = Loan::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-currency-dollar';

    protected static ?string $navigationLabel = 'My Loans';

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): ?string
    {
        return __('app.nav.group.my_finance');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public static function table(Table $table): Table
    {
        // Resolve member and eligibility once per request
        $myMember = fn() => Member::where('user_id', auth()->id())->first();
        $eligService = app(LoanEligibilityService::class);

        $member = $myMember();
        $eligible = $member ? $eligService->isEligible($member) : false;
        $eligCtx = $member ? $eligService->context($member) : null;
        $blockReason = ($member && !$eligible)
            ? $eligService->getIneligibilityReason($member)
            : '';

        return $table
            ->query(fn() => Loan::whereHas('member', fn($q) => $q->where('user_id', auth()->id()))
                ->with(['loanTier', 'fundTier', 'guarantor.user']))
            ->columns([
                Tables\Columns\IconColumn::make('is_emergency')
                    ->label('Emg.')
                    ->boolean()
                    ->trueIcon('heroicon-o-bolt')
                    ->falseIcon(null)
                    ->trueColor('danger'),
                Tables\Columns\TextColumn::make('loanTier.label')->label('Tier')->placeholder('—'),
                Tables\Columns\TextColumn::make('queue_position')->label('Q#')->placeholder('—'),
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
                        'completed', 'early_settled' => 'gray',
                        'rejected', 'cancelled' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('late_repayment_count')->label('Late #')
                    ->badge()->color(fn($state) => $state > 0 ? 'warning' : 'success'),
                Tables\Columns\TextColumn::make('applied_at')->dateTime('d M Y')->sortable(),
            ])
            ->defaultSort('applied_at', 'desc')
            ->headerActions([

                // ── NOT ELIGIBLE: show why, no form ──────────────────────────
                Action::make('apply_loan_blocked')
                    ->label('Apply for Loan')
                    ->icon('heroicon-o-lock-closed')
                    ->color('gray')
                    ->visible(!$eligible)
                    ->requiresConfirmation()
                    ->modalIcon('heroicon-o-exclamation-circle')
                    ->modalIconColor('warning')
                    ->modalHeading('Loan Application — Not Yet Eligible')
                    ->modalDescription($blockReason ?: 'You are currently not eligible to apply for a loan.')
                    ->modalSubmitActionLabel('I Understand')
                    ->action(fn() => null), // informational only

                // ── ELIGIBLE: full application form ──────────────────────────
                Action::make('apply_loan')
                    ->label('Apply for Loan')
                    ->icon('heroicon-o-plus')
                    ->color('primary')
                    ->visible($eligible)

                    // Layer 1: gate before the modal opens
                    ->before(function (Action $action) use ($myMember, $eligService) {
                        $member = $myMember();
                        if (!$member || !$eligService->isEligible($member)) {
                            Notification::make()
                                ->title('No Longer Eligible')
                                ->body($member
                                    ? $eligService->getIneligibilityReason($member)
                                    : 'Member record not found.')
                                ->danger()
                                ->send();
                            $action->halt();
                        }
                    })

                    ->schema(function () use ($myMember, $eligCtx) {
                        $member = $myMember();
                        $maxAmt = $eligCtx ? $eligCtx['max_loan_amount'] : 0;

                        return [
                            // Eligibility summary banner inside the form
                            Forms\Components\Placeholder::make('eligibility_banner')
                                ->label('')
                                ->content(function () use ($eligCtx, $maxAmt) {
                                    if (!$eligCtx) {
                                        return '—';
                                    }

                                    return '✅ Eligible to apply '
                                        . '| Fund balance: SAR ' . number_format($eligCtx['fund_balance'], 2)
                                        . ' | Max loan: SAR ' . number_format($maxAmt);
                                })
                                ->columnSpanFull(),

                            Forms\Components\TextInput::make('amount_requested')
                                ->label('Loan Amount (SAR)')
                                ->numeric()->prefix('SAR')->required()
                                ->minValue(1000)
                                ->maxValue($maxAmt > 0 ? $maxAmt : 300000)
                                ->helperText(
                                    $maxAmt > 0
                                    ? 'Maximum: SAR ' . number_format($maxAmt) . ' (2× your fund balance)'
                                    : 'Maximum could not be determined'
                                ),

                            Forms\Components\Placeholder::make('repayment_estimate')
                                ->label('Estimated Repayment Period')
                                ->content(function () use ($member) {
                                    if (!$member) {
                                        return '—';
                                    }
                                    $fundBal = (float) ($member->fundAccount()?->balance ?? 0);
                                    $threshold = Setting::loanSettlementThreshold();
                                    $maxBorrow = $fundBal * Setting::loanMaxBorrowMultiplier();
                                    $lines = [];

                                    foreach (LoanTier::where('is_active', true)->orderBy('min_amount')->get() as $tier) {
                                        if ($maxBorrow < (float) $tier->min_amount) {
                                            continue;
                                        }
                                        $sampleAmt = min((float) $tier->max_amount, $maxBorrow);
                                        $count = Loan::computeInstallmentsCount(
                                            $sampleAmt,
                                            $fundBal,
                                            (float) $tier->min_monthly_installment,
                                            $threshold
                                        );
                                        $lines[] = "{$tier->label}: SAR " . number_format($sampleAmt)
                                            . " → {$count} months × SAR "
                                            . number_format($tier->min_monthly_installment) . '/mo';
                                    }

                                    return empty($lines)
                                        ? 'Repayment period is computed automatically at approval.'
                                        : 'For your fund balance of SAR ' . number_format($fundBal) . ":\n"
                                        . implode("\n", $lines)
                                        . "\n\nFinal period confirmed at approval.";
                                })
                                ->columnSpanFull(),

                            Forms\Components\Toggle::make('is_emergency')
                                ->label('Request as Emergency Loan')
                                ->helperText('Emergency requests are reviewed with priority and funded from the Emergency tier. Use only for genuine urgent needs.')
                                ->default(false),

                            Forms\Components\Textarea::make('purpose')
                                ->label('Purpose of Loan')->required()->rows(3)->columnSpanFull(),

                            Forms\Components\Select::make('guarantor_member_id')
                                ->label('Guarantor Member')
                                ->options(function () use ($myMember) {
                                    $me = $myMember();

                                    return Member::active()->with('user')
                                        ->when($me, fn($q) => $q->where('id', '!=', $me->id))
                                        ->get()
                                        ->mapWithKeys(fn($m) => [$m->id => "{$m->member_number} – {$m->user->name}"]);
                                })
                                ->searchable()->required()
                                ->helperText('Must be an active member willing to guarantee your loan.'),

                            Forms\Components\TextInput::make('witness1_name')
                                ->label('Witness 1 — Name')->required()->maxLength(255),
                            Forms\Components\TextInput::make('witness1_phone')
                                ->label('Witness 1 — Phone')->tel()->maxLength(50),
                            Forms\Components\TextInput::make('witness2_name')
                                ->label('Witness 2 — Name')->required()->maxLength(255),
                            Forms\Components\TextInput::make('witness2_phone')
                                ->label('Witness 2 — Phone')->tel()->maxLength(50),
                        ];
                    })

                    // Layer 2 (final server-side guard): re-check on submission
                    ->action(function (array $data) use ($myMember, $eligService) {
                        $member = $myMember();

                        if (!$member) {
                            Notification::make()->title('Member record not found')->danger()->send();

                            return;
                        }

                        if (!$eligService->isEligible($member)) {
                            Notification::make()
                                ->title('Loan Submission Rejected — Not Eligible')
                                ->body($eligService->getIneligibilityReason($member))
                                ->danger()
                                ->send();

                            return;
                        }

                        $amount = (float) $data['amount_requested'];
                        $maxAmt = $eligService->maxLoanAmount($member);

                        if ($amount > $maxAmt) {
                            Notification::make()
                                ->title('Amount Exceeds Maximum')
                                ->body('Maximum loan amount is SAR ' . number_format($maxAmt)
                                    . ' (2× your fund balance of SAR ' . number_format($maxAmt / 2) . ').')
                                ->danger()
                                ->send();

                            return;
                        }

                        if ($amount < 1000) {
                            Notification::make()
                                ->title('Amount Below Minimum')
                                ->body('Minimum loan amount is SAR 1,000.')
                                ->danger()
                                ->send();

                            return;
                        }

                        $loan = Loan::create([
                            'member_id' => $member->id,
                            'amount_requested' => $amount,
                            'purpose' => $data['purpose'],
                            'installments_count' => 0, // computed at approval
                            'is_emergency' => (bool) ($data['is_emergency'] ?? false),
                            'guarantor_member_id' => $data['guarantor_member_id'],
                            'witness1_name' => $data['witness1_name'],
                            'witness1_phone' => $data['witness1_phone'] ?? null,
                            'witness2_name' => $data['witness2_name'],
                            'witness2_phone' => $data['witness2_phone'] ?? null,
                            'settlement_threshold' => Setting::loanSettlementThreshold(),
                            'status' => 'pending',
                            'applied_at' => now(),
                        ]);

                        try {
                            $member->user->notify(new LoanSubmittedNotification($loan));
                        } catch (\Throwable) {
                        }

                        Notification::make()
                            ->title('Loan Application Submitted')
                            ->body('Your application is under review. You will be notified once processed.')
                            ->success()
                            ->send();
                    }),

            ])
            ->recordActions([
                Action::make('cancel_loan')
                    ->label('Cancel')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn(Loan $r) => $r->status === 'pending')
                    ->requiresConfirmation()
                    ->modalHeading('Cancel Loan Application')
                    ->modalDescription('Are you sure you want to cancel this loan application?')
                    ->action(function (Loan $record) {
                        $record->update(['status' => 'cancelled', 'cancellation_reason' => 'Cancelled by member']);
                        try {
                            $record->member->user->notify(new LoanCancelledNotification($record, 'Cancelled by member'));
                        } catch (\Throwable) {
                        }
                        Notification::make()->title('Loan Application Cancelled')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMyLoans::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
