<?php

namespace App\Filament\Admin\Resources\MemberResource\RelationManagers;

use App\Models\Account;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class AccountsRelationManager extends RelationManager
{
    protected static string $relationship = 'accounts';
    protected static ?string $title = 'Virtual Accounts';

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
                    ->formatStateUsing(fn (Account $r) => $r->type_label)
                    ->color(fn (Account $r) => $r->type_color),
                Tables\Columns\TextColumn::make('loan_id')->label('Loan #')->placeholder('—'),
                Tables\Columns\TextColumn::make('balance')
                    ->label('Balance (SAR)')
                    ->money('SAR')
                    ->color(fn (Account $r) => (float) $r->balance >= 0 ? 'success' : 'danger')
                    ->weight(\Filament\Support\Enums\FontWeight::Bold),
            ]);
    }
}
