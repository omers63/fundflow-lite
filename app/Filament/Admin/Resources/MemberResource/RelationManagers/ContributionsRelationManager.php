<?php

namespace App\Filament\Admin\Resources\MemberResource\RelationManagers;

use Filament\Forms;
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
                    ->formatStateUsing(fn($state) => date('F', mktime(0, 0, 0, $state, 1))),
                Tables\Columns\TextColumn::make('amount')->money('SAR'),
                Tables\Columns\BadgeColumn::make('payment_method')
                    ->formatStateUsing(fn($state) => match ($state) {
                        'cash' => 'Cash',
                        'bank_transfer' => 'Bank Transfer',
                        'online' => 'Online',
                        default => $state ?? '—',
                    }),
                Tables\Columns\TextColumn::make('reference_number')->placeholder('—'),
                Tables\Columns\TextColumn::make('paid_at')->label('Paid On')
                    ->dateTime('d M Y')->sortable(),
                Tables\Columns\IconColumn::make('is_late')
                    ->label('Late')
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->falseIcon('heroicon-o-check-circle')
                    ->trueColor('warning')
                    ->falseColor('success'),
            ])
            ->defaultSort('paid_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('month')
                    ->options(array_combine(range(1, 12), array_map(fn($m) => date('F', mktime(0, 0, 0, $m, 1)), range(1, 12)))),
                Tables\Filters\Filter::make('year')
                    ->schema([Forms\Components\TextInput::make('year')->numeric()->default(now()->year)])
                    ->query(fn($query, $data) => ($data['year'] ?? null) ? $query->where('year', $data['year']) : $query),
                Tables\Filters\SelectFilter::make('payment_method')
                    ->options(['cash' => 'Cash', 'bank_transfer' => 'Bank Transfer', 'online' => 'Online Payment']),
                Tables\Filters\TernaryFilter::make('is_late')
                    ->label('Late payment')
                    ->trueLabel('Late only')
                    ->falseLabel('On-time only'),
                Tables\Filters\Filter::make('paid_at')
                    ->schema([
                        Forms\Components\DatePicker::make('paid_from')->label('Paid from'),
                        Forms\Components\DatePicker::make('paid_until')->label('Paid until'),
                    ])
                    ->columns(2)
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['paid_from'] ?? null, fn($q) => $q->whereDate('paid_at', '>=', $data['paid_from']))
                            ->when($data['paid_until'] ?? null, fn($q) => $q->whereDate('paid_at', '<=', $data['paid_until']));
                    }),
                Tables\Filters\Filter::make('amount')
                    ->schema([
                        Forms\Components\TextInput::make('amount_min')->label('Min amount (SAR)')->numeric(),
                        Forms\Components\TextInput::make('amount_max')->label('Max amount (SAR)')->numeric(),
                    ])
                    ->columns(2)
                    ->query(function ($query, array $data) {
                        return $query
                            ->when(filled($data['amount_min'] ?? null), fn($q) => $q->where('amount', '>=', $data['amount_min']))
                            ->when(filled($data['amount_max'] ?? null), fn($q) => $q->where('amount', '<=', $data['amount_max']));
                    }),
            ]);
    }
}
