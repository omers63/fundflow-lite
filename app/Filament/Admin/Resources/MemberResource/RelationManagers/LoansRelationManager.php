<?php

namespace App\Filament\Admin\Resources\MemberResource\RelationManagers;

use App\Models\Loan;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class LoansRelationManager extends RelationManager
{
    protected static string $relationship = 'loans';
    protected static ?string $title = 'Loans';

    public function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->defaultSort('applied_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('Loan #'),
                Tables\Columns\TextColumn::make('amount_requested')->label('Requested')->money('SAR'),
                Tables\Columns\TextColumn::make('amount_approved')->label('Approved')->money('SAR')->placeholder('—'),
                Tables\Columns\TextColumn::make('installments_count')->label('Months'),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => 'pending',
                        'info'    => 'approved',
                        'success' => 'active',
                        'gray'    => 'completed',
                        'danger'  => 'rejected',
                    ]),
                Tables\Columns\TextColumn::make('paid_installments_count')
                    ->label('Paid / Total')
                    ->getStateUsing(fn (Loan $r) => $r->paid_installments_count . ' / ' . $r->installments_count),
                Tables\Columns\TextColumn::make('remaining_amount')
                    ->label('Remaining')
                    ->money('SAR')
                    ->getStateUsing(fn (Loan $r) => $r->remaining_amount),
                Tables\Columns\TextColumn::make('applied_at')->label('Applied')->date('d M Y')->sortable(),
            ]);
    }
}
