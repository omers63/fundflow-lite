<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\BankTransactionResource\Pages;
use App\Models\Bank;
use App\Models\BankImportSession;
use App\Models\BankTransaction;
use App\Models\Loan;
use App\Models\LoanDisbursement;
use App\Models\Member;
use App\Services\AccountingService;
use Filament\Actions\Action;
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

    public static function getNavigationGroup(): ?string
    {
        return 'Banking';
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
                Tables\Columns\TextColumn::make('bank.name')->label('Bank')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('transaction_date')
                    ->label('Date')->date('d M Y')->sortable(),
                Tables\Columns\TextColumn::make('amount')
                    ->money('SAR')
                    ->sortable()
                    ->color(fn (BankTransaction $record) => $record->transaction_type === 'credit' ? 'success' : 'danger'),
                Tables\Columns\BadgeColumn::make('transaction_type')
                    ->label('Type')
                    ->colors(['success' => 'credit', 'danger' => 'debit']),
                Tables\Columns\TextColumn::make('reference')->placeholder('—')->searchable(),
                Tables\Columns\TextColumn::make('description')->limit(40)->placeholder('—')->searchable(),
                Tables\Columns\TextColumn::make('member.user.name')
                    ->label('Member')
                    ->placeholder('—')
                    ->searchable(),
                Tables\Columns\TextColumn::make('loan_id')
                    ->label('Loan')
                    ->formatStateUsing(fn ($state) => $state ? "#{$state}" : '—')
                    ->sortable()
                    ->searchable(),
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
                    ->falseColor('gray'),
                Tables\Columns\IconColumn::make('is_duplicate')
                    ->label('Dup.')
                    ->boolean()
                    ->trueColor('warning')
                    ->falseColor('success')
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->falseIcon('heroicon-o-check-circle'),
            ])
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
                DeleteAction::make()
                    ->modalDescription('Soft-deletes this import row. If it was posted to cash, the matching master and member cash ledger lines are reversed first.')
                    ->using(function (BankTransaction $record) {
                        app(AccountingService::class)->safeDeleteBankTransaction($record);

                        return true;
                    }),
                RestoreAction::make(),
                ForceDeleteAction::make(),
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
                        ->label('Post Selected to Cash Account')
                        ->icon('heroicon-o-arrow-right-circle')
                        ->color('primary')
                        ->schema([
                            Forms\Components\Select::make('member_id')
                                ->label('Post all selected for Member')
                                ->options($memberOptions)
                                ->searchable()
                                ->required()
                                ->helperText('All selected transactions will be posted to this member\'s Cash Account.'),
                        ])
                        ->action(function (Collection $records, array $data) {
                            $member = Member::findOrFail($data['member_id']);
                            $service = app(AccountingService::class);
                            $posted = 0;
                            $skipped = 0;

                            foreach ($records as $tx) {
                                if ($tx->isPosted()) {
                                    $skipped++;

                                    continue;
                                }
                                $service->postBankTransactionToCash($tx, $member);
                                $posted++;
                            }

                            Notification::make()
                                ->title('Bulk Post Complete')
                                ->body("Posted: {$posted} | Already posted (skipped): {$skipped}")
                                ->success()
                                ->send();
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
