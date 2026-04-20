<?php

namespace App\Filament\Member\Resources;

use App\Filament\Member\Resources\MyAccountLedgerResource\Pages;
use App\Models\Account;
use App\Models\AccountTransaction;
use App\Models\Member;
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

    public static function getNavigationGroup(): ?string
    {
        return __('app.nav.group.my_finance');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(function () {
                $member = Member::where('user_id', auth()->id())->first();

                return AccountTransaction::whereHas(
                    'account',
                    fn ($q) => $q->where('member_id', $member?->id ?? 0)
                )->with('account');
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
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        Account::TYPE_MEMBER_CASH => __('Cash'),
                        Account::TYPE_MEMBER_FUND => __('Fund'),
                        Account::TYPE_LOAN => __('Loan'),
                        default => ucfirst($state),
                    })
                    ->color(fn (string $state) => match ($state) {
                        Account::TYPE_MEMBER_CASH => 'info',
                        Account::TYPE_MEMBER_FUND => 'success',
                        Account::TYPE_LOAN => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('entry_type')
                    ->label(__('Type'))
                    ->badge()
                    ->formatStateUsing(fn (string $state) => ucfirst($state))
                    ->color(fn (string $state) => $state === 'credit' ? 'success' : 'danger'),
                Tables\Columns\TextColumn::make('amount')
                    ->label(__('Amount (SAR)'))
                    ->money('SAR')
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('description')
                    ->label(__('Description'))
                    ->visibleFrom('md')
                    ->wrap()
                    ->limit(80),
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
                        if (! $data['value']) {
                            return $query;
                        }

                        return $query->whereHas('account', fn ($q) => $q->where('type', $data['value']));
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
                            ->when($data['from'] ?? null, fn ($q) => $q->whereDate('transacted_at', '>=', $data['from']))
                            ->when($data['until'] ?? null, fn ($q) => $q->whereDate('transacted_at', '<=', $data['until']));
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
