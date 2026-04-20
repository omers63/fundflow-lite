<?php

namespace App\Filament\Admin\Resources\BankImportSessionResource\RelationManagers;

use App\Models\BankTransaction;
use App\Models\Loan;
use App\Models\LoanDisbursement;
use App\Models\Member;
use App\Services\AccountingService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class TransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'transactions';

    protected static ?string $title = 'Imported Transactions';

    public function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public function table(Table $table): Table
    {
        $memberOptions = fn () => Member::with('user')
            ->active()
            ->get()
            ->mapWithKeys(fn ($m) => [$m->id => "{$m->member_number} – {$m->user->name}"]);

        return $table
            ->recordTitleAttribute('reference')
            ->defaultSort('transaction_date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('transaction_date')
                    ->label('Date')->date('d M Y')->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('amount')->money('SAR')
                    ->color(fn (BankTransaction $r) => $r->transaction_type === 'credit' ? 'success' : 'danger')
                    ->toggleable(),
                Tables\Columns\BadgeColumn::make('transaction_type')->label('Type')
                    ->colors(['success' => 'credit', 'danger' => 'debit'])
                    ->toggleable(),
                Tables\Columns\TextColumn::make('reference')->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('description')->limit(40)->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('member.user.name')->label('Member')->placeholder('—')
                    ->toggleable(),
                Tables\Columns\IconColumn::make('posted_at')->label('Posted')
                    ->boolean()
                    ->getStateUsing(fn (BankTransaction $r) => $r->posted_at !== null)
                    ->trueIcon('heroicon-o-check-badge')->falseIcon('heroicon-o-clock')
                    ->trueColor('success')->falseColor('gray')
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_duplicate')->label('Dup.')
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-triangle')->falseIcon('heroicon-o-check-circle')
                    ->trueColor('warning')->falseColor('success')
                    ->toggleable(),
            ])
            ->columnManager()
            ->deferColumnManager(false)
            ->filters([
                Tables\Filters\SelectFilter::make('transaction_type')
                    ->options(['credit' => 'Credit', 'debit' => 'Debit']),
                Tables\Filters\TernaryFilter::make('is_duplicate')
                    ->trueLabel('Duplicates only')->falseLabel('Non-duplicates only')->placeholder('All'),
                Tables\Filters\TernaryFilter::make('posted')
                    ->trueLabel('Posted')->falseLabel('Unposted')->placeholder('All')
                    ->queries(
                        true: fn ($q) => $q->whereNotNull('posted_at'),
                        false: fn ($q) => $q->whereNull('posted_at'),
                    ),
                Tables\Filters\SelectFilter::make('member_id')
                    ->label('Member')
                    ->searchable()
                    ->options($memberOptions),
                Tables\Filters\Filter::make('transaction_date')
                    ->schema([
                        Forms\Components\DatePicker::make('from')->label('From'),
                        Forms\Components\DatePicker::make('until')->label('Until'),
                    ])
                    ->columns(2)
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'] ?? null, fn ($q) => $q->whereDate('transaction_date', '>=', $data['from']))
                            ->when($data['until'] ?? null, fn ($q) => $q->whereDate('transaction_date', '<=', $data['until']));
                    }),
                Tables\Filters\Filter::make('amount')
                    ->schema([
                        Forms\Components\TextInput::make('amount_min')->label('Min (SAR)')->numeric(),
                        Forms\Components\TextInput::make('amount_max')->label('Max (SAR)')->numeric(),
                    ])
                    ->columns(2)
                    ->query(function ($query, array $data) {
                        return $query
                            ->when(filled($data['amount_min'] ?? null), fn ($q) => $q->where('amount', '>=', $data['amount_min']))
                            ->when(filled($data['amount_max'] ?? null), fn ($q) => $q->where('amount', '<=', $data['amount_max']));
                    }),
            ])
            ->recordActions([
                ActionGroup::make([
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
                                }),
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
                                ->required($record->transaction_type === 'debit'),
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
                                ->required($record->transaction_type === 'debit'),
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
                            Notification::make()->title('Posted to Cash Account')->success()->send();
                        }),
                ]),
            ]);
    }
}
