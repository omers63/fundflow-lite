<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\BankTransactionResource\Pages;
use App\Models\Bank;
use App\Models\BankImportSession;
use App\Models\BankTransaction;
use App\Models\Loan;
use App\Models\LoanDisbursement;
use App\Models\LoanInstallment;
use App\Models\Member;
use App\Models\Setting;
use App\Services\AccountingService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
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
use Illuminate\Database\Eloquent\Collection;

class BankTransactionResource extends Resource
{
    protected static ?string $model = BankTransaction::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationLabel = 'Bank Transactions';

    protected static ?int $navigationSort = 12;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function getNavigationLabel(): string
    {
        return __('Bank Transactions');
    }

    public static function getNavigationGroup(): ?string
    {
        return 'finance';
    }

    public static function getNavigationBadge(): ?string
    {
        $count = BankTransaction::where('is_duplicate', true)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Flagged duplicates';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make()
                ->columnSpanFull()
                ->schema([
                    Forms\Components\TextInput::make('bank.name')->label('Bank')->disabled(),
                    Forms\Components\TextInput::make('transaction_date')->disabled(),
                    Forms\Components\TextInput::make('amount')->disabled(),
                    Forms\Components\TextInput::make('transaction_type')->disabled(),
                    Forms\Components\TextInput::make('reference')->placeholder('—')->disabled(),
                    Forms\Components\TextInput::make('member.user.name')->label('Posted to Member')->placeholder('Not yet posted')->disabled(),
                    Forms\Components\TextInput::make('loan_id')
                        ->label('Linked Loan')
                        ->formatStateUsing(fn ($state) => $state ? "#{$state}" : '—')
                        ->disabled(),
                    Forms\Components\TextInput::make('loan_disbursement_id')
                        ->label('Linked disbursement')
                        ->formatStateUsing(fn ($state) => $state ? "#{$state}" : '—')
                        ->disabled(),
                    Forms\Components\Textarea::make('description')->disabled()->columnSpanFull(),
                    Forms\Components\KeyValue::make('raw_data')
                        ->label('Raw CSV Data')
                        ->disabled()
                        ->columnSpanFull(),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        $memberOptions = fn () => Member::with('user')
            ->active()
            ->get()
            ->mapWithKeys(fn ($m) => [$m->id => "{$m->member_number} – {$m->user->name}"]);

        return $table
            ->striped()
            ->columns([
                Tables\Columns\TextColumn::make('bank.name')->label('Bank')->searchable()->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('transaction_date')
                    ->label('Date')->date('d M Y')->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('amount')
                    ->money('SAR')
                    ->sortable()
                    ->color(fn (BankTransaction $record) => $record->transaction_type === 'credit' ? 'success' : 'danger')
                    ->toggleable(),
                Tables\Columns\BadgeColumn::make('transaction_type')
                    ->label('Type')
                    ->colors(['success' => 'credit', 'danger' => 'debit'])
                    ->toggleable(),
                Tables\Columns\TextColumn::make('reference')->placeholder('—')->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('description')->limit(40)->placeholder('—')->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('member.user.name')
                    ->label('Member')
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('loan_id')
                    ->label('Loan')
                    ->formatStateUsing(fn ($state) => $state ? "#{$state}" : '—')
                    ->sortable()
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('loan_disbursement_id')
                    ->label('Disb.')
                    ->formatStateUsing(fn ($state) => $state ? "#{$state}" : '—')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('posted_at')
                    ->label('Posted')
                    ->boolean()
                    ->getStateUsing(fn (BankTransaction $r) => $r->posted_at !== null)
                    ->trueIcon('heroicon-o-check-badge')
                    ->falseIcon('heroicon-o-clock')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_duplicate')
                    ->label('Dup.')
                    ->boolean()
                    ->trueColor('warning')
                    ->falseColor('success')
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->falseIcon('heroicon-o-check-circle')
                    ->toggleable(),
            ])
            ->columnManager()
            ->deferColumnManager(false)
            ->defaultSort('transaction_date', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('bank_id')
                    ->label('Bank')
                    ->options(Bank::active()->pluck('name', 'id')),
                Tables\Filters\SelectFilter::make('import_session_id')
                    ->label('Import Session')
                    ->options(
                        BankImportSession::with('bank')->latest()->get()
                            ->mapWithKeys(fn ($s) => [$s->id => "{$s->bank->name} — {$s->filename} ({$s->created_at->format('d M Y')})"])
                    ),
                Tables\Filters\SelectFilter::make('transaction_type')
                    ->options(['credit' => 'Credit', 'debit' => 'Debit']),
                Tables\Filters\TernaryFilter::make('is_duplicate')
                    ->label('Duplicates')
                    ->trueLabel('Duplicates only')
                    ->falseLabel('Non-duplicates only')
                    ->placeholder('All'),
                Tables\Filters\TernaryFilter::make('posted')
                    ->label('Posting Status')
                    ->trueLabel('Posted only')
                    ->falseLabel('Unposted only')
                    ->placeholder('All')
                    ->queries(
                        true: fn ($q) => $q->whereNotNull('posted_at'),
                        false: fn ($q) => $q->whereNull('posted_at'),
                    ),
                Tables\Filters\Filter::make('date_range')
                    ->schema([
                        Forms\Components\DatePicker::make('date_from')->label('From'),
                        Forms\Components\DatePicker::make('date_to')->label('To'),
                    ])
                    ->query(fn ($query, $data) => $query
                        ->when($data['date_from'], fn ($q, $v) => $q->whereDate('transaction_date', '>=', $v))
                        ->when($data['date_to'], fn ($q, $v) => $q->whereDate('transaction_date', '<=', $v)))
                    ->columns(2),
                Tables\Filters\SelectFilter::make('member_id')
                    ->label('Member')
                    ->searchable()
                    ->options($memberOptions),
                Tables\Filters\SelectFilter::make('loan_id')
                    ->label('Loan')
                    ->searchable()
                    ->options(
                        Loan::query()
                            ->orderByDesc('id')
                            ->limit(1000)
                            ->pluck('id', 'id')
                            ->mapWithKeys(fn ($id) => [$id => "#{$id}"])
                    ),
                Tables\Filters\Filter::make('amount')
                    ->schema([
                        Forms\Components\TextInput::make('amount_min')->label('Min amount (SAR)')->numeric(),
                        Forms\Components\TextInput::make('amount_max')->label('Max amount (SAR)')->numeric(),
                    ])
                    ->columns(2)
                    ->query(function ($query, array $data) {
                        return $query
                            ->when(filled($data['amount_min'] ?? null), fn ($q) => $q->where('amount', '>=', $data['amount_min']))
                            ->when(filled($data['amount_max'] ?? null), fn ($q) => $q->where('amount', '<=', $data['amount_max']));
                    }),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    Action::make('post_to_cash')
                        ->label('Post to Cash')
                        ->icon('heroicon-o-arrow-right-circle')
                        ->color('primary')
                        ->visible(fn (BankTransaction $r) => ! $r->isPosted())
                        ->schema(fn (BankTransaction $record) => [
                            Forms\Components\Select::make('member_id')
                                ->label($record->transaction_type === 'debit' ? 'Member' : 'Member (optional)')
                                ->options($memberOptions)
                                ->searchable()
                                ->required($record->transaction_type === 'debit')
                                ->live()
                                ->afterStateUpdated(function ($set) {
                                    $set('loan_id', null);
                                    $set('loan_disbursement_id', null);
                                })
                                ->helperText($record->transaction_type === 'debit'
                                    ? 'Required for debit reconciliation.'
                                    : 'Optional. Leave empty to post only to master cash account.'),
                            Forms\Components\Select::make('loan_id')
                                ->label('Loan')
                                ->options(fn (Get $get) => Loan::query()
                                    ->where('member_id', $get('member_id'))
                                    ->whereHas('disbursements')
                                    ->orderByDesc('id')
                                    ->get()
                                    ->mapWithKeys(fn (Loan $loan) => [
                                        $loan->id => sprintf(
                                            '#%d — SAR %s approved, SAR %s disbursed, %s',
                                            $loan->id,
                                            number_format((float) $loan->amount_approved, 2),
                                            number_format((float) $loan->amount_disbursed, 2),
                                            $loan->status
                                        ),
                                    ]))
                                ->searchable()
                                ->preload()
                                ->live()
                                ->afterStateUpdated(fn ($set) => $set('loan_disbursement_id', null))
                                ->visible($record->transaction_type === 'debit')
                                ->required($record->transaction_type === 'debit')
                                ->helperText('Member loan summary. Choose the loan this bank debit reconciles to.'),
                            Forms\Components\Select::make('loan_disbursement_id')
                                ->label('Loan disbursement payout')
                                ->options(fn (Get $get) => LoanDisbursement::query()
                                    ->where('loan_id', $get('loan_id'))
                                    ->orderByDesc('disbursed_at')
                                    ->orderByDesc('id')
                                    ->get()
                                    ->mapWithKeys(fn (LoanDisbursement $d) => [
                                        $d->id => sprintf(
                                            'SAR %s on %s — disbursement #%d',
                                            number_format((float) $d->amount, 2),
                                            $d->disbursed_at?->format('d M Y') ?? '?',
                                            $d->id
                                        ),
                                    ]))
                                ->searchable()
                                ->preload()
                                ->visible($record->transaction_type === 'debit')
                                ->required($record->transaction_type === 'debit')
                                ->helperText('The specific partial or full disbursement record this transaction matches.'),
                        ])
                        ->action(function (BankTransaction $record, array $data) {
                            $member = ! empty($data['member_id']) ? Member::findOrFail($data['member_id']) : null;
                            $disbursement = ! empty($data['loan_disbursement_id'])
                                ? LoanDisbursement::query()->findOrFail($data['loan_disbursement_id'])
                                : null;
                            if ($disbursement && ! empty($data['loan_id']) && (int) $disbursement->loan_id !== (int) $data['loan_id']) {
                                throw new \InvalidArgumentException('Selected disbursement does not belong to the selected loan.');
                            }
                            app(AccountingService::class)->postBankTransactionToCashWithOptionalMember($record, $member, $disbursement);

                            Notification::make()
                                ->title('Posted to Cash Account')
                                ->body($member
                                    ? "Transaction posted for {$member->user->name}."
                                    : 'Transaction posted to master cash account.')
                                ->success()
                                ->send();
                        }),
                    Action::make('post_to_member')
                        ->label('Post To Member')
                        ->icon('heroicon-o-user-plus')
                        ->color('success')
                        ->visible(fn (BankTransaction $r) => blank($r->member_id))
                        ->schema(fn (BankTransaction $record) => [
                            Forms\Components\Select::make('member_id')
                                ->label('Member')
                                ->options($memberOptions)
                                ->searchable()
                                ->required()
                                ->helperText('If not yet posted, this action posts to cash first, then posts/mirrors to the selected member cash account.'),
                            Forms\Components\Select::make('loan_id')
                                ->label('Loan')
                                ->options(fn (Get $get) => Loan::query()
                                    ->where('member_id', $get('member_id'))
                                    ->whereHas('disbursements')
                                    ->orderByDesc('id')
                                    ->get()
                                    ->mapWithKeys(fn (Loan $loan) => [
                                        $loan->id => sprintf(
                                            '#%d — SAR %s approved, SAR %s disbursed, %s',
                                            $loan->id,
                                            number_format((float) $loan->amount_approved, 2),
                                            number_format((float) $loan->amount_disbursed, 2),
                                            $loan->status
                                        ),
                                    ]))
                                ->searchable()
                                ->preload()
                                ->live()
                                ->afterStateUpdated(fn ($set) => $set('loan_disbursement_id', null))
                                ->visible(fn () => ! $record->isPosted() && $record->transaction_type === 'debit')
                                ->required(fn () => ! $record->isPosted() && $record->transaction_type === 'debit')
                                ->helperText('Required only for debit transactions that are not yet posted.'),
                            Forms\Components\Select::make('loan_disbursement_id')
                                ->label('Loan disbursement payout')
                                ->options(fn (Get $get) => LoanDisbursement::query()
                                    ->where('loan_id', $get('loan_id'))
                                    ->orderByDesc('disbursed_at')
                                    ->orderByDesc('id')
                                    ->get()
                                    ->mapWithKeys(fn (LoanDisbursement $d) => [
                                        $d->id => sprintf(
                                            'SAR %s on %s — disbursement #%d',
                                            number_format((float) $d->amount, 2),
                                            $d->disbursed_at?->format('d M Y') ?? '?',
                                            $d->id
                                        ),
                                    ]))
                                ->searchable()
                                ->preload()
                                ->visible(fn () => ! $record->isPosted() && $record->transaction_type === 'debit')
                                ->required(fn () => ! $record->isPosted() && $record->transaction_type === 'debit')
                                ->helperText('Required only for debit transactions that are not yet posted.'),
                        ])
                        ->action(function (BankTransaction $record, array $data) {
                            $member = Member::findOrFail($data['member_id']);
                            $service = app(AccountingService::class);

                            if (! $record->isPosted()) {
                                $disbursement = null;

                                if ($record->transaction_type === 'debit') {
                                    if (empty($data['loan_id']) || empty($data['loan_disbursement_id'])) {
                                        throw new \InvalidArgumentException('Loan and disbursement are required for debit postings.');
                                    }

                                    $disbursement = LoanDisbursement::query()->findOrFail($data['loan_disbursement_id']);
                                    if ((int) $disbursement->loan_id !== (int) $data['loan_id']) {
                                        throw new \InvalidArgumentException('Selected disbursement does not belong to the selected loan.');
                                    }
                                }

                                $service->postBankTransactionToCashWithOptionalMember($record, $member, $disbursement);

                                Notification::make()
                                    ->title('Posted To Member')
                                    ->body("Transaction posted to cash and assigned to {$member->user->name}.")
                                    ->success()
                                    ->send();

                                return;
                            }

                            if ($record->transaction_type !== 'credit') {
                                throw new \InvalidArgumentException('Only posted credit transactions can be mirrored to a member.');
                            }

                            $service->mirrorBankCreditToMemberCash($record, $member);

                            Notification::make()
                                ->title('Posted to Member')
                                ->body("Existing cash posting mirrored to {$member->user->name}.")
                                ->success()
                                ->send();
                        }),
                    Action::make('post_to_loan')
                        ->label('Post To Loan')
                        ->icon('heroicon-o-banknotes')
                        ->color('warning')
                        ->visible(fn (BankTransaction $r) => $r->transaction_type === 'debit' && blank($r->loan_disbursement_id))
                        ->schema([
                            Forms\Components\Select::make('loan_id')
                                ->label('Loan')
                                ->options(fn () => Loan::query()
                                    ->with('member.user')
                                    ->where('status', 'approved')
                                    ->whereRaw('COALESCE(amount_disbursed, 0) < COALESCE(amount_approved, 0)')
                                    ->orderByDesc('id')
                                    ->get()
                                    ->mapWithKeys(fn (Loan $loan) => [
                                        $loan->id => sprintf(
                                            '#%d — %s (%s) · Remaining SAR %s',
                                            $loan->id,
                                            $loan->member?->user?->name ?? 'Member',
                                            $loan->member?->member_number ?? '—',
                                            number_format((float) $loan->remainingToDisburse(), 2)
                                        ),
                                    ]))
                                ->searchable()
                                ->required()
                                ->helperText('Posts this debit amount as a loan disbursement on the selected approved loan.'),
                        ])
                        ->action(function (BankTransaction $record, array $data) {
                            $loan = Loan::query()
                                ->with(['member.user', 'member.accounts', 'loanTier'])
                                ->findOrFail($data['loan_id']);

                            if ($loan->status !== 'approved') {
                                throw new \InvalidArgumentException('Only approved loans can receive a new disbursement.');
                            }

                            $amount = (float) $record->amount;
                            if ($amount <= 0) {
                                throw new \InvalidArgumentException('Debit amount must be greater than zero.');
                            }

                            $remaining = (float) $loan->remainingToDisburse();
                            if ($amount > $remaining + 0.01) {
                                throw new \InvalidArgumentException(
                                    'Debit amount exceeds remaining loan disbursement amount (SAR ' . number_format($remaining, 2) . ').'
                                );
                            }

                            // Installment count at full disbursement uses fund balance before posting this tranche.
                            $memberFundBalanceBefore = (float) ($loan->member->fundAccount()?->balance ?? 0);

                            $disbursement = LoanDisbursement::create([
                                'loan_id' => $loan->id,
                                'amount' => $amount,
                                'member_portion' => 0,
                                'master_portion' => 0,
                                'disbursed_at' => $record->transaction_date ?? now(),
                                'disbursed_by_id' => auth()->id(),
                                'notes' => 'Posted from bank transaction #' . $record->id . ($record->reference ? (' (ref ' . $record->reference . ')') : ''),
                            ]);

                            try {
                                app(AccountingService::class)->postPartialLoanDisbursement($loan, $amount, $disbursement);
                            } catch (\Throwable $e) {
                                $disbursement->delete();
                                throw $e;
                            }

                            $loan->refresh();

                            if ($loan->isFullyDisbursed()) {
                                $disbursedAt = now();
                                $minInstall = (float) ($loan->loanTier?->min_monthly_installment ?? 1000);
                                $threshold = (float) ($loan->settlement_threshold ?: Setting::loanSettlementThreshold());
                                $count = Loan::computeInstallmentsCount(
                                    (float) $loan->amount_approved,
                                    $memberFundBalanceBefore,
                                    $minInstall,
                                    $threshold,
                                );

                                $exemption = Loan::computeExemptionAndFirstRepayment($disbursedAt);
                                $exemption = Loan::adjustFirstRepaymentIfContributionAlreadyMade($loan->member, $exemption);

                                \Illuminate\Support\Facades\DB::transaction(function () use ($loan, $disbursedAt, $count, $minInstall, $exemption, $memberFundBalanceBefore): void {
                                    $amountApproved = (float) $loan->amount_approved;
                                    $memberPortion = min(max(0.0, $memberFundBalanceBefore), $amountApproved);
                                    $masterPortion = $amountApproved - $memberPortion;

                                    $loan->update([
                                        'status' => 'active',
                                        'installments_count' => $count,
                                        'disbursed_at' => $disbursedAt,
                                        'due_date' => $disbursedAt->copy()->addMonths($count)->toDateString(),
                                        'member_portion' => $memberPortion,
                                        'master_portion' => $masterPortion,
                                    ] + $exemption);

                                    $startDate = \Carbon\Carbon::create(
                                        $exemption['first_repayment_year'],
                                        $exemption['first_repayment_month'],
                                        5
                                    );

                                    for ($i = 1; $i <= $count; $i++) {
                                        LoanInstallment::create([
                                            'loan_id' => $loan->id,
                                            'installment_number' => $i,
                                            'amount' => $minInstall,
                                            'due_date' => $startDate->copy()->addMonths($i - 1)->toDateString(),
                                            'status' => 'pending',
                                        ]);
                                    }
                                });
                            }

                            $record->update([
                                'member_id' => $loan->member_id,
                                'loan_id' => $loan->id,
                                'loan_disbursement_id' => $disbursement->id,
                                'posted_at' => $record->posted_at ?? now(),
                                'posted_by' => $record->posted_by ?? auth()->id(),
                            ]);

                            $loan->refresh();

                            Notification::make()
                                ->title('Posted To Loan')
                                ->body(
                                    'Debit mapped to Loan #' . $loan->id
                                    . ' as disbursement #' . $disbursement->id
                                    . '. Remaining to disburse: SAR ' . number_format($loan->remainingToDisburse(), 2) . '.'
                                )
                                ->success()
                                ->send();
                        }),
                    DeleteAction::make()
                        ->modalDescription('Soft-deletes this import row. If it was posted to cash, the matching master and member cash ledger lines are reversed first.')
                        ->using(function (BankTransaction $record) {
                            app(AccountingService::class)->safeDeleteBankTransaction($record);

                            return true;
                        }),
                    RestoreAction::make(),
                    ForceDeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->modalDescription('Deletes selected rows; posted transactions are reversed from the ledger first.')
                        ->using(function (DeleteBulkAction $action, $records) {
                            $accounting = app(AccountingService::class);
                            foreach ($records as $record) {
                                try {
                                    $accounting->safeDeleteBankTransaction($record);
                                } catch (\Throwable $e) {
                                    $action->reportBulkProcessingFailure(message: $e->getMessage());
                                    report($e);
                                }
                            }
                        }),
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    BulkAction::make('bulk_post_to_cash')
                        ->label('Post Selected')
                        ->icon('heroicon-o-arrow-right-circle')
                        ->color('primary')
                        ->schema([
                            Forms\Components\Select::make('member_id')
                                ->label('Member (optional)')
                                ->options($memberOptions)
                                ->searchable()
                                ->helperText('Optional: mirror to a member’s Cash Account. Leave empty to post credits to the master Cash Account only. Debits are skipped when no member is selected (use the row Post to Cash action for debits).'),
                        ])
                        ->action(function (Collection $records, array $data) {
                            $member = filled($data['member_id'] ?? null)
                                ? Member::findOrFail($data['member_id'])
                                : null;
                            $service = app(AccountingService::class);
                            $posted = 0;
                            $skippedPosted = 0;
                            $skippedDebitNoMember = 0;
                            $failed = 0;

                            foreach ($records as $tx) {
                                if ($tx->isPosted()) {
                                    $skippedPosted++;

                                    continue;
                                }
                                try {
                                    if ($member !== null) {
                                        $service->postBankTransactionToCash($tx, $member);
                                    } elseif ($tx->transaction_type === 'debit') {
                                        $skippedDebitNoMember++;

                                        continue;
                                    } else {
                                        $service->postBankTransactionToCashWithOptionalMember($tx, null);
                                    }
                                    $posted++;
                                } catch (\Throwable $e) {
                                    $failed++;
                                    report($e);
                                }
                            }

                            $body = "Posted: {$posted} | Already posted (skipped): {$skippedPosted}";
                            if ($skippedDebitNoMember > 0) {
                                $body .= " | Debits skipped (choose a member): {$skippedDebitNoMember}";
                            }
                            if ($failed > 0) {
                                $body .= " | Failed: {$failed} (see logs)";
                            }

                            $notification = Notification::make()
                                ->title('Bulk Post Complete')
                                ->body($body);

                            if ($failed > 0) {
                                $notification->warning();
                            } else {
                                $notification->success();
                            }

                            $notification->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBankTransactions::route('/'),
            'create' => Pages\CreateBankTransaction::route('/create'),
            'view' => Pages\ViewBankTransaction::route('/{record}'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->withTrashed();
    }
}
