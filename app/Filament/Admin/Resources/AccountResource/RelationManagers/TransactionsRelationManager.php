<?php

namespace App\Filament\Admin\Resources\AccountResource\RelationManagers;

use App\Models\AccountTransaction;
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
                    ->color(fn (AccountTransaction $r) => $r->entry_type === 'credit' ? 'success' : 'danger'),
                Tables\Columns\TextColumn::make('description')
                    ->limit(60)
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('member.user.name')
                    ->label('Member')
                    ->placeholder('—')
                    ->searchable(),
                Tables\Columns\TextColumn::make('source_type')
                    ->label('Source')
                    ->formatStateUsing(fn ($state) => $state ? class_basename($state) : '—'),
                Tables\Columns\TextColumn::make('postedBy.name')
                    ->label('Posted By')
                    ->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('entry_type')
                    ->label('Type')
                    ->options(['credit' => 'Credit', 'debit' => 'Debit']),
            ])
            ->paginated([25, 50, 100]);
    }
}
