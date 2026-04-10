<?php

namespace App\Filament\Admin\Resources\MemberResource\RelationManagers;

use App\Filament\Admin\Resources\LoanResource;
use App\Filament\Admin\Resources\MemberResource\Concerns\InteractsWithMemberCycleHeaderActions;
use App\Models\FundTier;
use App\Models\Loan;
use App\Models\LoanTier;
use App\Services\AccountingService;
use App\Services\LoanEligibilityService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class LoansRelationManager extends RelationManager
{
    use InteractsWithMemberCycleHeaderActions;

    protected static string $relationship = 'loans';

    protected static ?string $title = 'Loans';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->defaultSort('applied_at', 'desc')
            ->striped()
            ->headerActions([
                Action::make('new_loan')
                    ->label('New loan')
                    ->icon('heroicon-o-plus-circle')
                    ->url(fn(): string => LoanResource::getUrl('create') . '?member_id=' . $this->getOwnerRecord()->getKey())
                    ->visible(fn(): bool => LoanResource::canCreate()
                        && app(LoanEligibilityService::class)->isEligible($this->getOwnerRecord())),
                $this->repaymentCycleHeaderAction(),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('Loan #')->toggleable(),
                Tables\Columns\TextColumn::make('amount_requested')->label('Requested')->money('SAR')->toggleable(),
                Tables\Columns\TextColumn::make('amount_approved')->label('Approved')->money('SAR')->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('installments_count')->label('Months')->toggleable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => 'pending',
                        'info' => 'approved',
                        'success' => 'active',
                        'gray' => 'completed',
                        'danger' => 'rejected',
                    ])
                    ->toggleable(),
                Tables\Columns\TextColumn::make('paid_installments_count')
                    ->label('Paid / Total')
                    ->getStateUsing(fn(Loan $r) => $r->paid_installments_count . ' / ' . $r->installments_count)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('remaining_amount')
                    ->label('Remaining')
                    ->money('SAR')
                    ->getStateUsing(fn(Loan $r) => $r->remaining_amount)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('applied_at')->label('Applied')->date('d M Y')->sortable()->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'pending' => 'Pending',
                    'approved' => 'Approved',
                    'active' => 'Active',
                    'completed' => 'Completed',
                    'early_settled' => 'Early Settled',
                    'rejected' => 'Rejected',
                    'cancelled' => 'Cancelled',
                ]),
                Tables\Filters\SelectFilter::make('loan_tier_id')
                    ->label('Loan tier')
                    ->options(fn() => LoanTier::query()->orderBy('tier_number')->pluck('label', 'id')),
                Tables\Filters\SelectFilter::make('fund_tier_id')
                    ->label('Fund tier')
                    ->options(fn() => FundTier::query()->orderBy('label')->pluck('label', 'id')),
                Tables\Filters\TernaryFilter::make('is_emergency')->label('Emergency'),
                Tables\Filters\TernaryFilter::make('disbursed')
                    ->label('Disbursed')
                    ->trueLabel('Disbursed')
                    ->falseLabel('Not disbursed')
                    ->queries(
                        true: fn($q) => $q->whereNotNull('disbursed_at'),
                        false: fn($q) => $q->whereNull('disbursed_at'),
                    ),
                Tables\Filters\Filter::make('applied_at')
                    ->schema([
                        Forms\Components\DatePicker::make('from')->label('Applied from'),
                        Forms\Components\DatePicker::make('until')->label('Applied until'),
                    ])
                    ->columns(2)
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'] ?? null, fn($q) => $q->whereDate('applied_at', '>=', $data['from']))
                            ->when($data['until'] ?? null, fn($q) => $q->whereDate('applied_at', '<=', $data['until']));
                    }),
                Tables\Filters\Filter::make('amount_requested')
                    ->schema([
                        Forms\Components\TextInput::make('min')->label('Min requested (SAR)')->numeric(),
                        Forms\Components\TextInput::make('max')->label('Max requested (SAR)')->numeric(),
                    ])
                    ->columns(2)
                    ->query(function ($query, array $data) {
                        return $query
                            ->when(filled($data['min'] ?? null), fn($q) => $q->where('amount_requested', '>=', $data['min']))
                            ->when(filled($data['max'] ?? null), fn($q) => $q->where('amount_requested', '<=', $data['max']));
                    }),
                Tables\Filters\Filter::make('amount_approved')
                    ->schema([
                        Forms\Components\TextInput::make('min')->label('Min approved (SAR)')->numeric(),
                        Forms\Components\TextInput::make('max')->label('Max approved (SAR)')->numeric(),
                    ])
                    ->columns(2)
                    ->query(function ($query, array $data) {
                        return $query
                            ->when(filled($data['min'] ?? null), fn($q) => $q->where('amount_approved', '>=', $data['min']))
                            ->when(filled($data['max'] ?? null), fn($q) => $q->where('amount_approved', '<=', $data['max']));
                    }),
            ])
            ->recordActions([
                DeleteAction::make()
                    ->modalDescription(
                        'Reverses ledger postings for this loan, removes installments and the loan account, then deletes the loan. Same behavior as Finance → Loans delete.'
                    )
                    ->using(function (Loan $record) {
                        app(AccountingService::class)->safeDeleteLoan($record);

                        return true;
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->modalDescription(
                            'Deletes each selected loan with full ledger reversal. Failures are reported individually.'
                        )
                        ->using(function (DeleteBulkAction $action, $records) {
                            $accounting = app(AccountingService::class);
                            foreach ($records as $record) {
                                try {
                                    $accounting->safeDeleteLoan($record);
                                } catch (\Throwable $e) {
                                    $action->reportBulkProcessingFailure(message: $e->getMessage());
                                    report($e);
                                }
                            }
                        }),
                ]),
            ]);
    }
}
