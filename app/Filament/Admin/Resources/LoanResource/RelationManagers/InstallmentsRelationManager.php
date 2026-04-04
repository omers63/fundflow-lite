<?php

namespace App\Filament\Admin\Resources\LoanResource\RelationManagers;

use App\Models\LoanInstallment;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class InstallmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'installments';

    protected static ?string $title = 'Installments';

    public function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('installment_number')
            ->defaultSort('installment_number')
            ->columns([
                Tables\Columns\TextColumn::make('installment_number')
                    ->label('#')
                    ->sortable(),
                Tables\Columns\TextColumn::make('due_date')
                    ->label('Due Date')
                    ->date('d M Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount')
                    ->money('SAR'),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => 'paid',
                        'warning' => 'pending',
                        'danger' => 'overdue',
                    ]),
                Tables\Columns\TextColumn::make('paid_at')
                    ->label('Paid On')
                    ->dateTime('d M Y H:i')
                    ->placeholder('—'),
            ])
            ->recordActions([
                Action::make('mark_paid')
                    ->label('Mark Paid')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (LoanInstallment $record) => $record->status !== 'paid')
                    ->requiresConfirmation()
                    ->action(function (LoanInstallment $record) {
                        $record->update([
                            'status' => 'paid',
                            'paid_at' => now(),
                        ]);

                        Notification::make()
                            ->title('Installment marked as paid')
                            ->success()
                            ->send();
                    }),
            ]);
    }
}
