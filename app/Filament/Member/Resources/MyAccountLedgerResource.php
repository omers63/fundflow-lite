<?php

namespace App\Filament\Member\Resources;

use App\Filament\Member\Resources\MyAccountLedgerResource\Pages;
use App\Models\Account;
use App\Models\AccountTransaction;
use App\Models\Member;
use App\Support\FilamentTableSummaries;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MyAccountLedgerResource extends Resource
{
    protected static ?string $model = AccountTransaction::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'My Ledger';

    protected static ?int $navigationSort = 3;

    public static function getNavigationLabel(): string
    {
        return __('My Ledger');
    }

    public static function getModelLabel(): string
    {
        return __('Transaction');
    }

    public static function getPluralModelLabel(): string
    {
        return __('My Ledger');
    }

    public static function getNavigationGroup(): ?string
    {
        return 'my_finance';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(function () {
                $member = Member::where('user_id', auth()->id())->first();

                return AccountTransaction::whereHas(
                    'account',
                    fn($q) => $q->where('member_id', $member?->id ?? 0)
                )->with(['account', 'postedBy', 'source']);
            })
            ->columns([
                Tables\Columns\TextColumn::make('transacted_at')
                    ->label(__('Date'))
                    ->dateTime('d M Y, H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('account.type')
                    ->label(__('Account'))
                    ->visibleFrom('sm')
                    ->badge()
                    ->formatStateUsing(fn(string $state) => match ($state) {
                        Account::TYPE_MEMBER_CASH => __('Cash'),
                        Account::TYPE_MEMBER_FUND => __('Fund'),
                        Account::TYPE_LOAN => __('Loan'),
                        default => ucfirst($state),
                    })
                    ->color(fn(string $state) => match ($state) {
                        Account::TYPE_MEMBER_CASH => 'info',
                        Account::TYPE_MEMBER_FUND => 'success',
                        Account::TYPE_LOAN => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('entry_type')
                    ->label(__('Type'))
                    ->badge()
                    ->formatStateUsing(fn(string $state) => match ($state) {
                        'credit' => __('Credit (In)'),
                        'debit' => __('Debit (Out)'),
                        default => __(ucfirst($state)),
                    })
                    ->color(fn(string $state) => $state === 'credit' ? 'success' : 'danger'),
                Tables\Columns\TextColumn::make('amount')
                    ->label(__('Amount (SAR)'))
                    ->money('SAR')
                    ->weight('bold')
                    ->summarize(FilamentTableSummaries::countSumAverageMoney()),
                Tables\Columns\TextColumn::make('description')
                    ->label(__('Description'))
                    ->visibleFrom('md')
                    ->wrap()
                    ->limit(80),
            ])
            ->recordAction('view_details')
            ->recordActions([
                Action::make('view_details')
                    ->label(__('View details'))
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->modalHeading(__('Transaction details'))
                    ->modalDescription(__('Review the full posting information for this ledger transaction.'))
                    ->modalWidth('2xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('Close'))
                    ->schema([
                        \Filament\Schemas\Components\Section::make(__('Transaction summary'))
                            ->schema([
                                Forms\Components\Placeholder::make('amount')
                                    ->label(__('Amount'))
                                    ->content(fn(AccountTransaction $record): string => __('SAR :amount', [
                                        'amount' => number_format((float) $record->amount, 2),
                                    ])),
                                Forms\Components\Placeholder::make('entry_type')
                                    ->label(__('Type'))
                                    ->content(fn(AccountTransaction $record): string => $record->entry_type === 'credit'
                                        ? __('Credit (In)')
                                        : __('Debit (Out)')),
                                Forms\Components\Placeholder::make('account')
                                    ->label(__('Account'))
                                    ->content(fn(AccountTransaction $record): string => match ($record->account?->type) {
                                        Account::TYPE_MEMBER_CASH => __('Cash'),
                                        Account::TYPE_MEMBER_FUND => __('Fund'),
                                        Account::TYPE_LOAN => __('Loan'),
                                        default => (string) ($record->account?->type ?? __('—')),
                                    }),
                                Forms\Components\Placeholder::make('posted_at')
                                    ->label(__('Posted at'))
                                    ->content(fn(AccountTransaction $record): string => $record->transacted_at instanceof CarbonInterface
                                        ? $record->transacted_at->locale(app()->getLocale())->translatedFormat('d M Y, H:i:s')
                                        : __('—')),
                            ])
                            ->columns(2),
                        \Filament\Schemas\Components\Section::make(__('Description'))
                            ->schema([
                                Forms\Components\Placeholder::make('description')
                                    ->label('')
                                    ->content(fn(AccountTransaction $record): string => (string) ($record->description ?: __('—'))),
                            ]),
                        \Filament\Schemas\Components\Section::make(__('Technical details'))
                            ->collapsible()
                            ->collapsed()
                            ->schema([
                                Forms\Components\Placeholder::make('source')
                                    ->label(__('Source'))
                                    ->content(fn(AccountTransaction $record): string => $record->source_type && $record->source_id
                                        ? class_basename((string) $record->source_type) . ' #' . $record->source_id
                                        : __('—')),
                                Forms\Components\Placeholder::make('posted_by')
                                    ->label(__('Posted by'))
                                    ->content(fn(AccountTransaction $record): string => (string) ($record->postedBy?->name ?: __('System'))),
                                Forms\Components\Placeholder::make('transaction_id')
                                    ->label(__('Transaction ID'))
                                    ->content(fn(AccountTransaction $record): string => (string) $record->id),
                            ])
                            ->columns(2),
                    ]),
            ])
            ->defaultSort('transacted_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('account_type')
                    ->label(__('Account'))
                    ->options([
                        Account::TYPE_MEMBER_CASH => __('Cash'),
                        Account::TYPE_MEMBER_FUND => __('Fund'),
                    ])
                    ->query(function ($query, array $data) {
                        if (!$data['value']) {
                            return $query;
                        }

                        return $query->whereHas('account', fn($q) => $q->where('type', $data['value']));
                    }),
                Tables\Filters\SelectFilter::make('entry_type')
                    ->label(__('Type'))
                    ->options(['credit' => __('Credit (In)'), 'debit' => __('Debit (Out)')]),
                Tables\Filters\Filter::make('transacted_at')
                    ->schema([
                        Forms\Components\DatePicker::make('from')->label(__('From date')),
                        Forms\Components\DatePicker::make('until')->label(__('Until date')),
                    ])
                    ->columns(2)
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'] ?? null, fn($q) => $q->whereDate('transacted_at', '>=', $data['from']))
                            ->when($data['until'] ?? null, fn($q) => $q->whereDate('transacted_at', '<=', $data['until']));
                    }),
                Tables\Filters\Filter::make('amount')
                    ->schema([
                        Forms\Components\TextInput::make('amount_min')->label(__('Min (SAR)'))->numeric(),
                        Forms\Components\TextInput::make('amount_max')->label(__('Max (SAR)'))->numeric(),
                    ])
                    ->columns(2)
                    ->query(function ($query, array $data) {
                        return $query
                            ->when(filled($data['amount_min'] ?? null), fn($q) => $q->where('amount', '>=', $data['amount_min']))
                            ->when(filled($data['amount_max'] ?? null), fn($q) => $q->where('amount', '<=', $data['amount_max']));
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMyAccountLedger::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
