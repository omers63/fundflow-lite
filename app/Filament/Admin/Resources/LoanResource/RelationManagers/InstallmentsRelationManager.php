<?php

namespace App\Filament\Admin\Resources\LoanResource\RelationManagers;

use App\Filament\Admin\Resources\LoanResource;
use App\Models\Loan;
use App\Models\LoanInstallment;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Livewire\Attributes\On;
use Livewire\Component;

class InstallmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'installments';

    protected static ?string $title = 'Installments';

    /**
     * Loan disbursement (e.g. final tranche) creates installments on the server; the relation
     * table cache and eager-loaded relations otherwise stay stale until navigation.
     */
    #[On('fundflow-refresh-loan-installments')]
    public function refreshInstallmentsTable(): void
    {
        $this->ownerRecord->unsetRelation('installments');
        $this->resetTable();
    }

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
                Tables\Columns\IconColumn::make('is_late')
                    ->label('Late')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('late_fee_amount')
                    ->label('Late fee')
                    ->money('SAR')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                LoanResource::earlySettleLoanAction(function (Loan $loan, Component $livewire) {
                    $loan->refresh();
                    $livewire->resetTable();
                    $livewire->dispatch('fundflow-refresh-loan-installments');
                })
                    ->record(fn(): Loan => $this->getOwnerRecord()),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'paid' => 'Paid',
                        'overdue' => 'Overdue',
                    ]),
                Tables\Filters\Filter::make('due_date')
                    ->schema([
                        Forms\Components\DatePicker::make('from')->label('Due from'),
                        Forms\Components\DatePicker::make('until')->label('Due until'),
                    ])
                    ->columns(2)
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'] ?? null, fn($q) => $q->whereDate('due_date', '>=', $data['from']))
                            ->when($data['until'] ?? null, fn($q) => $q->whereDate('due_date', '<=', $data['until']));
                    }),
                Tables\Filters\Filter::make('amount')
                    ->schema([
                        Forms\Components\TextInput::make('amount_min')->label('Min (SAR)')->numeric(),
                        Forms\Components\TextInput::make('amount_max')->label('Max (SAR)')->numeric(),
                    ])
                    ->columns(2)
                    ->query(function ($query, array $data) {
                        return $query
                            ->when(filled($data['amount_min'] ?? null), fn($q) => $q->where('amount', '>=', $data['amount_min']))
                            ->when(filled($data['amount_max'] ?? null), fn($q) => $q->where('amount', '<=', $data['amount_max']));
                    }),
                Tables\Filters\Filter::make('paid_at')
                    ->schema([
                        Forms\Components\DatePicker::make('from')->label('Paid from'),
                        Forms\Components\DatePicker::make('until')->label('Paid until'),
                    ])
                    ->columns(2)
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'] ?? null, fn($q) => $q->whereDate('paid_at', '>=', $data['from']))
                            ->when($data['until'] ?? null, fn($q) => $q->whereDate('paid_at', '<=', $data['until']));
                    }),
            ])
            ->recordActions([
                Action::make('mark_paid')
                    ->label('Mark Paid')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn(LoanInstallment $record) => $record->status !== 'paid')
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

                Action::make('waive_late_fee')
                    ->label('Waive Late Fee')
                    ->icon('heroicon-o-x-circle')
                    ->color('warning')
                    ->visible(fn(LoanInstallment $record) => $record->is_late
                        && (float) $record->late_fee_amount > 0
                        && $record->status !== 'paid')
                    ->requiresConfirmation()
                    ->modalHeading('Waive Late Fee')
                    ->modalDescription(fn(LoanInstallment $record) => 'This will waive the SAR '
                        . number_format((float) $record->late_fee_amount, 2)
                        . ' late fee on installment #' . $record->installment_number
                        . '. The base amount is still owed. This cannot be undone.')
                    ->action(function (LoanInstallment $record) {
                        $record->update([
                            'late_fee_amount' => 0,
                            'is_late' => false,
                        ]);

                        // Refresh the member's aggregate late repayment stats
                        $loan = $record->loan()->with('member')->first();
                        if ($loan && $loan->member) {
                            $member = $loan->member;
                            $totalLate = $member->loans()
                                ->whereIn('status', ['active', 'completed', 'early_settled'])
                                ->sum('late_repayment_count');
                            $totalAmount = $member->loans()
                                ->whereIn('status', ['active', 'completed', 'early_settled'])
                                ->sum('late_repayment_amount');
                            $member->update([
                                'late_repayment_count'  => max(0, (int) $totalLate),
                                'late_repayment_amount' => max(0.0, (float) $totalAmount),
                            ]);
                        }

                        Notification::make()
                            ->title('Late fee waived')
                            ->body('The late fee for installment #' . $record->installment_number . ' has been removed.')
                            ->success()
                            ->send();
                    }),
            ]);
    }
}
