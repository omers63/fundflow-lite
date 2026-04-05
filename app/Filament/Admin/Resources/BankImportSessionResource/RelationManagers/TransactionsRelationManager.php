<?php

namespace App\Filament\Admin\Resources\BankImportSessionResource\RelationManagers;

use App\Models\BankTransaction;
use App\Models\Member;
use App\Services\AccountingService;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
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
        $memberOptions = fn() => Member::with('user')
            ->active()
            ->get()
            ->mapWithKeys(fn($m) => [$m->id => "{$m->member_number} – {$m->user->name}"]);

        return $table
            ->recordTitleAttribute('reference')
            ->defaultSort('transaction_date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('transaction_date')
                    ->label('Date')->date('d M Y')->sortable(),
                Tables\Columns\TextColumn::make('amount')->money('SAR')
                    ->color(fn(BankTransaction $r) => $r->transaction_type === 'credit' ? 'success' : 'danger'),
                Tables\Columns\BadgeColumn::make('transaction_type')->label('Type')
                    ->colors(['success' => 'credit', 'danger' => 'debit']),
                Tables\Columns\TextColumn::make('reference')->placeholder('—'),
                Tables\Columns\TextColumn::make('description')->limit(40)->placeholder('—'),
                Tables\Columns\TextColumn::make('member.user.name')->label('Member')->placeholder('—'),
                Tables\Columns\IconColumn::make('posted_at')->label('Posted')
                    ->boolean()
                    ->getStateUsing(fn(BankTransaction $r) => $r->posted_at !== null)
                    ->trueIcon('heroicon-o-check-badge')->falseIcon('heroicon-o-clock')
                    ->trueColor('success')->falseColor('gray'),
                Tables\Columns\IconColumn::make('is_duplicate')->label('Dup.')
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-triangle')->falseIcon('heroicon-o-check-circle')
                    ->trueColor('warning')->falseColor('success'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('transaction_type')
                    ->options(['credit' => 'Credit', 'debit' => 'Debit']),
                Tables\Filters\TernaryFilter::make('is_duplicate')
                    ->trueLabel('Duplicates only')->falseLabel('Non-duplicates only')->placeholder('All'),
                Tables\Filters\TernaryFilter::make('posted')
                    ->trueLabel('Posted')->falseLabel('Unposted')->placeholder('All')
                    ->queries(
                        true: fn($q) => $q->whereNotNull('posted_at'),
                        false: fn($q) => $q->whereNull('posted_at'),
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
                            ->when($data['from'] ?? null, fn($q) => $q->whereDate('transaction_date', '>=', $data['from']))
                            ->when($data['until'] ?? null, fn($q) => $q->whereDate('transaction_date', '<=', $data['until']));
                    }),
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
                Action::make('post_to_cash')
                    ->label('Post to Cash')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->color('primary')
                    ->visible(fn(BankTransaction $r) => !$r->isPosted())
                    ->schema([
                        Forms\Components\Select::make('member_id')
                            ->label('Member')
                            ->options($memberOptions)
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (BankTransaction $record, array $data) {
                        $member = Member::findOrFail($data['member_id']);
                        app(AccountingService::class)->postBankTransactionToCash($record, $member);
                        Notification::make()->title('Posted to Cash Account')->success()->send();
                    }),
            ]);
    }
}
