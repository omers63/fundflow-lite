<?php

namespace App\Filament\Admin\Resources\MemberResource\RelationManagers;

use App\Models\Account;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;

class AccountsRelationManager extends RelationManager
{
    protected static string $relationship = 'accounts';

    protected static ?string $title = 'Accounts';

    public function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name'),
                Tables\Columns\BadgeColumn::make('type')
                    ->formatStateUsing(fn(Account $r) => $r->type_label)
                    ->color(fn(Account $r) => $r->type_color),
                Tables\Columns\TextColumn::make('loan_id')->label('Loan #')->placeholder('—'),
                Tables\Columns\TextColumn::make('balance')
                    ->label('Balance (SAR)')
                    ->money('SAR')
                    ->color(fn(Account $r) => (float) $r->balance >= 0 ? 'success' : 'danger')
                    ->weight(FontWeight::Bold),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Account type')
                    ->options([
                        Account::TYPE_MEMBER_CASH => 'Member Cash',
                        Account::TYPE_MEMBER_FUND => 'Member Fund',
                        Account::TYPE_LOAN => 'Loan',
                    ]),
                Tables\Filters\TernaryFilter::make('is_active')->label('Active'),
                Tables\Filters\TernaryFilter::make('loan_linked')
                    ->label('Loan account')
                    ->trueLabel('Linked to a loan')
                    ->falseLabel('Not a loan account')
                    ->queries(
                        true: fn($q) => $q->whereNotNull('loan_id'),
                        false: fn($q) => $q->whereNull('loan_id'),
                    ),
                Tables\Filters\Filter::make('balance')
                    ->schema([
                        Forms\Components\TextInput::make('balance_min')->label('Min balance (SAR)')->numeric(),
                        Forms\Components\TextInput::make('balance_max')->label('Max balance (SAR)')->numeric(),
                    ])
                    ->columns(2)
                    ->query(function ($query, array $data) {
                        return $query
                            ->when(filled($data['balance_min'] ?? null), fn($q) => $q->where('balance', '>=', $data['balance_min']))
                            ->when(filled($data['balance_max'] ?? null), fn($q) => $q->where('balance', '<=', $data['balance_max']));
                    }),
            ]);
    }
}
