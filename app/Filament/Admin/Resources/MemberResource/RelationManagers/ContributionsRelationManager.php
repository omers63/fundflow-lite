<?php

namespace App\Filament\Admin\Resources\MemberResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class ContributionsRelationManager extends RelationManager
{
    protected static string $relationship = 'contributions';

    protected static ?string $title = 'Contributions';

    public function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->defaultSort('year', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('year')->sortable(),
                Tables\Columns\TextColumn::make('month')
                    ->formatStateUsing(fn ($state) => date('F', mktime(0, 0, 0, $state, 1))),
                Tables\Columns\TextColumn::make('amount')->money('SAR'),
                Tables\Columns\BadgeColumn::make('payment_method')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'cash' => 'Cash',
                        'bank_transfer' => 'Bank Transfer',
                        'online' => 'Online',
                        default => $state ?? '—',
                    }),
                Tables\Columns\TextColumn::make('reference_number')->placeholder('—'),
                Tables\Columns\TextColumn::make('paid_at')->label('Paid On')
                    ->dateTime('d M Y')->sortable(),
            ])
            ->defaultSort('paid_at', 'desc');
    }
}
