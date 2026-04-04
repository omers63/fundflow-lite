<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\AccountResource\Pages;
use App\Filament\Admin\Resources\AccountResource\RelationManagers\TransactionsRelationManager;
use App\Models\Account;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;

class AccountResource extends Resource
{
    protected static ?string $model = Account::class;

    protected static string|\BackedEnum|null $navigationIcon = null;

    protected static ?string $navigationLabel = 'Accounts';

    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): ?string
    {
        return __('app.nav.group.finance');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::SemiBold),
                Tables\Columns\BadgeColumn::make('type')
                    ->label('Account Type')
                    ->formatStateUsing(fn(Account $r) => $r->type_label)
                    ->color(fn(Account $r) => $r->type_color),
                Tables\Columns\TextColumn::make('member.user.name')
                    ->label('Member')
                    ->placeholder('—')
                    ->searchable(),
                Tables\Columns\TextColumn::make('member.member_number')
                    ->label('Member #')
                    ->placeholder('—')
                    ->searchable(),
                Tables\Columns\TextColumn::make('loan_id')
                    ->label('Loan #')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('balance')
                    ->label('Balance (SAR)')
                    ->money('SAR')
                    ->sortable()
                    ->color(fn(Account $r) => (float) $r->balance >= 0 ? 'success' : 'danger')
                    ->weight(FontWeight::Bold),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->defaultSort('type')
            ->groups([
                Tables\Grouping\Group::make('type')
                    ->label('Account Type')
                    ->titlePrefixedWithLabel(false)
                    ->getTitleFromRecordUsing(fn(Account $r) => $r->type_label),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        Account::TYPE_MASTER_CASH => 'Master Cash',
                        Account::TYPE_MASTER_FUND => 'Master Fund',
                        Account::TYPE_MEMBER_CASH => 'Member Cash',
                        Account::TYPE_MEMBER_FUND => 'Member Fund',
                        Account::TYPE_LOAN => 'Loan',
                    ]),
            ])
            ->recordActions([
                ViewAction::make()->label('Ledger'),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            TransactionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAccounts::route('/'),
            'view' => Pages\ViewAccount::route('/{record}'),
        ];
    }
}
