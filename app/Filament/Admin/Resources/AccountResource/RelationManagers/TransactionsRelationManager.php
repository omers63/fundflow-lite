<?php

namespace App\Filament\Admin\Resources\AccountResource\RelationManagers;

use App\Models\AccountTransaction;
use App\Models\Member;
use App\Services\AccountingService;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class TransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'transactions';

    protected static ?string $title = 'Ledger Entries';

    public function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('description')
            ->defaultSort('transacted_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('transacted_at')
                    ->label('Date')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('entry_type')
                    ->label('Type')
                    ->colors(['success' => 'credit', 'danger' => 'debit']),
                Tables\Columns\TextColumn::make('amount')
                    ->money('SAR')
                    ->sortable()
                    ->color(fn(AccountTransaction $r) => $r->entry_type === 'credit' ? 'success' : 'danger'),
                Tables\Columns\TextColumn::make('description')
                    ->limit(60)
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('member.user.name')
                    ->label('Member')
                    ->placeholder('—')
                    ->searchable(),
                Tables\Columns\TextColumn::make('source_type')
                    ->label('Source')
                    ->formatStateUsing(fn($state) => $state ? class_basename($state) : '—'),
                Tables\Columns\TextColumn::make('postedBy.name')
                    ->label('Posted By')
                    ->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('member_id')
                    ->label('Member')
                    ->searchable()
                    ->options(fn() => Member::with('user')->orderBy('member_number')->get()
                        ->mapWithKeys(fn(Member $m) => [$m->id => "{$m->member_number} – {$m->user->name}"])),
                Tables\Filters\SelectFilter::make('entry_type')
                    ->label('Type')
                    ->options(['credit' => 'Credit', 'debit' => 'Debit']),
                Tables\Filters\Filter::make('transacted_at')
                    ->schema([
                        Forms\Components\DateTimePicker::make('from')->label('From'),
                        Forms\Components\DateTimePicker::make('until')->label('Until'),
                    ])
                    ->columns(2)
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'] ?? null, fn($q) => $q->where('transacted_at', '>=', $data['from']))
                            ->when($data['until'] ?? null, fn($q) => $q->where('transacted_at', '<=', $data['until']));
                    }),
                Tables\Filters\SelectFilter::make('source_type')
                    ->label('Source type')
                    ->options([
                        'App\Models\BankTransaction' => 'Bank import',
                        'App\Models\SmsTransaction' => 'SMS import',
                        'App\Models\Contribution' => 'Contribution',
                        'App\Models\Loan' => 'Loan',
                        'App\Models\LoanInstallment' => 'Loan installment',
                    ]),
                Tables\Filters\Filter::make('amount')
                    ->schema([
                        Forms\Components\TextInput::make('amount_min')->label('Min (SAR)')->numeric(),
                        Forms\Components\TextInput::make('amount_max')->label('Max (SAR)')->numeric(),
                    ])
                    ->columns(2)
                    ->query(function ($query, array $data) {
                        return $query
                            ->when(filled($data['amount_min'] ?? null), fn($q) => $q->where('amount', '>=', $data['amount_min']))
                            ->when(filled($data['amount_max'] ?? null), fn($q) => $q->where('amount', '<=', $data['amount_max']));
                    }),
            ])
            ->recordActions([
                DeleteAction::make()
                    ->authorize(fn() => auth()->user()?->can('update', $this->getOwnerRecord()) ?? false)
                    ->modalDescription('Reverses this line on the account balance and removes the row. If this was one leg of a paired posting, delete or adjust the other leg separately if needed.')
                    ->using(function (AccountTransaction $record) {
                        app(AccountingService::class)->safeDeleteAccountTransaction($record);

                        return true;
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->authorize(fn() => auth()->user()?->can('update', $this->getOwnerRecord()) ?? false)
                        ->modalDescription('Reverses each selected line on its account balance, then deletes it.')
                        ->using(function (DeleteBulkAction $action, $records) {
                            $accounting = app(AccountingService::class);
                            foreach ($records as $record) {
                                try {
                                    $accounting->safeDeleteAccountTransaction($record);
                                } catch (\Throwable $e) {
                                    $action->reportBulkProcessingFailure(message: $e->getMessage());
                                    report($e);
                                }
                            }
                        }),
                ]),
            ])
            ->paginated([25, 50, 100]);
    }
}
