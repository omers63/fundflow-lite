<?php

namespace App\Filament\Member\Resources;

use App\Filament\Member\Resources\MyLoansResource\Pages;
use App\Models\Loan;
use App\Models\LoanTier;
use App\Models\Member;
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
    protected static string|\BackedEnum|null $navigationIcon = null;
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
        $myMember = fn () => Member::where('user_id', auth()->id())->first();

        return $table
            ->query(fn () => Loan::whereHas('member', fn ($q) => $q->where('user_id', auth()->id()))
                ->with(['loanTier', 'fundTier', 'guarantor.user']))
            ->columns([
                Tables\Columns\TextColumn::make('loanTier.label')->label('Tier')->placeholder('—'),
                Tables\Columns\TextColumn::make('queue_position')->label('Q#')->placeholder('—'),
                Tables\Columns\TextColumn::make('amount_requested')->label('Requested')->money('SAR'),
                Tables\Columns\TextColumn::make('amount_approved')->label('Approved')->money('SAR')->placeholder('—'),
                Tables\Columns\TextColumn::make('installments_count')->label('Months'),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->color(fn (string $state) => match ($state) {
                        'pending'       => 'warning',
                        'approved'      => 'info',
                        'active'        => 'success',
                        'completed', 'early_settled' => 'gray',
                        'rejected', 'cancelled' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('late_repayment_count')->label('Late #')
                    ->badge()->color(fn ($state) => $state > 0 ? 'warning' : 'success'),
                Tables\Columns\TextColumn::make('applied_at')->dateTime('d M Y')->sortable(),
            ])
            ->defaultSort('applied_at', 'desc')
            ->headerActions([
                Action::make('apply_loan')
                    ->label('Apply for Loan')
                    ->icon('heroicon-o-plus')
                    ->color('primary')
                    ->schema(function () use ($myMember) {
                        $member = $myMember();
                        $elig   = $member ? app(LoanEligibilityService::class)->context($member) : null;
                        $maxAmt = $elig ? $elig['max_loan_amount'] : 0;
                        $tiers  = LoanTier::where('is_active', true)->get();

                        return [
                            Forms\Components\Placeholder::make('eligibility_info')
                                ->label('Your Eligibility')
                                ->content(function () use ($elig, $maxAmt) {
                                    if (! $elig) return 'Member record not found.';
                                    if (! $elig['eligible']) return '❌ ' . $elig['reason'];
                                    return "✅ Eligible — Max loan amount: SAR " . number_format($maxAmt) .
                                           " | Fund balance: SAR " . number_format($elig['fund_balance'], 2);
                                }),

                            Forms\Components\TextInput::make('amount_requested')
                                ->label('Loan Amount (SAR)')
                                ->numeric()->prefix('SAR')->required()
                                ->minValue(1000)
                                ->maxValue($maxAmt > 0 ? $maxAmt : 300000)
                                ->helperText($maxAmt > 0 ? "Max: SAR " . number_format($maxAmt) : 'Eligibility limit not determined'),

                            Forms\Components\Select::make('installments_count')
                                ->label('Repayment Period')
                                ->options(array_combine(range(1, 36), array_map(fn ($n) => "{$n} months", range(1, 36))))
                                ->default(12)->required(),

                            Forms\Components\Textarea::make('purpose')
                                ->label('Purpose of Loan')->required()->rows(3)->columnSpanFull(),

                            Forms\Components\Select::make('guarantor_member_id')
                                ->label('Guarantor Member')
                                ->options(function () use ($myMember) {
                                    $me = $myMember();
                                    return Member::active()->with('user')
                                        ->when($me, fn ($q) => $q->where('id', '!=', $me->id))
                                        ->get()
                                        ->mapWithKeys(fn ($m) => [$m->id => "{$m->member_number} – {$m->user->name}"]);
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
                    ->action(function (array $data) use ($myMember) {
                        $member = $myMember();
                        if (! $member) {
                            Notification::make()->title('Member record not found')->danger()->send();
                            return;
                        }

                        $eligService = app(LoanEligibilityService::class);
                        if (! $eligService->isEligible($member)) {
                            Notification::make()
                                ->title('Not Eligible')
                                ->body($eligService->getIneligibilityReason($member))
                                ->warning()->send();
                            return;
                        }

                        $amount = (float) $data['amount_requested'];
                        $maxAmt = $eligService->maxLoanAmount($member);
                        if ($amount > $maxAmt) {
                            Notification::make()
                                ->title('Amount Exceeds Limit')
                                ->body("Maximum loan amount is SAR " . number_format($maxAmt) . ".")
                                ->danger()->send();
                            return;
                        }

                        $loan = Loan::create([
                            'member_id'          => $member->id,
                            'amount_requested'   => $amount,
                            'purpose'            => $data['purpose'],
                            'installments_count' => $data['installments_count'],
                            'guarantor_member_id'=> $data['guarantor_member_id'],
                            'witness1_name'      => $data['witness1_name'],
                            'witness1_phone'     => $data['witness1_phone'] ?? null,
                            'witness2_name'      => $data['witness2_name'],
                            'witness2_phone'     => $data['witness2_phone'] ?? null,
                            'settlement_threshold' => \App\Models\Setting::loanSettlementThreshold(),
                            'status'             => 'pending',
                            'applied_at'         => now(),
                        ]);

                        try {
                            $member->user->notify(new LoanSubmittedNotification($loan));
                        } catch (\Throwable) {}

                        Notification::make()
                            ->title('Loan Application Submitted')
                            ->body('Your application is under review. You will be notified once processed.')
                            ->success()->send();
                    }),
            ])
            ->recordActions([
                Action::make('cancel_loan')
                    ->label('Cancel')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Loan $r) => $r->status === 'pending')
                    ->requiresConfirmation()
                    ->modalHeading('Cancel Loan Application')
                    ->modalDescription('Are you sure you want to cancel this loan application?')
                    ->action(function (Loan $record) {
                        $record->update(['status' => 'cancelled', 'cancellation_reason' => 'Cancelled by member']);
                        try {
                            $record->member->user->notify(new LoanCancelledNotification($record, 'Cancelled by member'));
                        } catch (\Throwable) {}
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

    public static function canCreate(): bool { return false; }
}
