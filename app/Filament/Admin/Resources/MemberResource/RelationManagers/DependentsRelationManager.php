<?php

namespace App\Filament\Admin\Resources\MemberResource\RelationManagers;

use App\Models\Account;
use App\Models\Member;
use App\Services\AccountingService;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class DependentsRelationManager extends RelationManager
{
    protected static string $relationship = 'dependents';

    protected static ?string $title = 'Dependent Members';

    public function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('member_number')
            ->emptyStateHeading('No dependent members')
            ->emptyStateDescription('This member has no dependents assigned.')
            ->columns([
                Tables\Columns\TextColumn::make('member_number')->label('Member #')->sortable(),
                Tables\Columns\TextColumn::make('user.name')->label('Name')->searchable(),
                Tables\Columns\TextColumn::make('monthly_contribution_amount')
                    ->label('Monthly Allocation')
                    ->money('SAR'),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->colors(['success' => 'active', 'warning' => 'suspended', 'danger' => 'delinquent']),
                Tables\Columns\TextColumn::make('cash_balance')
                    ->label('Cash Balance')
                    ->money('SAR')
                    ->getStateUsing(fn(Member $r) => $r->cash_balance)
                    ->color(fn(Member $r) => $r->cash_balance >= 0 ? 'success' : 'danger'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'suspended' => 'Suspended',
                        'delinquent' => 'Delinquent',
                    ]),
                Tables\Filters\SelectFilter::make('monthly_contribution_amount')
                    ->label('Monthly allocation')
                    ->options(Member::contributionAmountOptions()),
                Tables\Filters\Filter::make('cash_balance')
                    ->label('Cash balance (SAR)')
                    ->schema([
                        Forms\Components\TextInput::make('min')->label('Min')->numeric(),
                        Forms\Components\TextInput::make('max')->label('Max')->numeric(),
                    ])
                    ->columns(2)
                    ->query(function ($query, array $data) {
                        return $query->whereHas('accounts', function ($q) use ($data) {
                            $q->where('type', Account::TYPE_MEMBER_CASH);
                            if (filled($data['min'] ?? null)) {
                                $q->where('balance', '>=', $data['min']);
                            }
                            if (filled($data['max'] ?? null)) {
                                $q->where('balance', '<=', $data['max']);
                            }
                        });
                    }),
            ])
            ->recordActions([
                // Change this dependent's allocation amount
                Action::make('set_allocation')
                    ->label('Set Allocation')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->color('warning')
                    ->fillForm(fn(Member $record) => [
                        'monthly_contribution_amount' => $record->monthly_contribution_amount,
                    ])
                    ->schema([
                        Forms\Components\Select::make('monthly_contribution_amount')
                            ->label('Monthly Contribution Amount')
                            ->options(Member::contributionAmountOptions())
                            ->required(),
                    ])
                    ->action(function (Member $record, array $data) {
                        $record->update([
                            'monthly_contribution_amount' => $data['monthly_contribution_amount'],
                        ]);

                        Notification::make()
                            ->title('Allocation Updated')
                            ->body("Monthly allocation for {$record->user->name} set to SAR " . number_format($data['monthly_contribution_amount']))
                            ->success()
                            ->send();
                    }),

                // Fund dependent's cash account from this parent's cash account
                Action::make('fund_cash')
                    ->label('Fund Cash Account')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->schema([
                        Forms\Components\TextInput::make('amount')
                            ->label('Amount (SAR)')
                            ->numeric()
                            ->minValue(1)
                            ->required()
                            ->prefix('SAR')
                            ->helperText(
                                fn(Member $record) => "Dependent's cash balance: SAR " . number_format($record->cash_balance, 2) .
                                ' | Your cash balance: SAR ' . number_format($this->getOwnerRecord()->cash_balance, 2)
                            ),
                        Forms\Components\TextInput::make('note')
                            ->label('Note (optional)')
                            ->maxLength(200),
                    ])
                    ->action(function (Member $record, array $data) {
                        $parent = $this->getOwnerRecord();

                        try {
                            app(AccountingService::class)->fundDependentCashAccount(
                                parent: $parent,
                                dependent: $record,
                                amount: (float) $data['amount'],
                                note: $data['note'] ?? '',
                            );

                            Notification::make()
                                ->title('Cash Account Funded')
                                ->body('SAR ' . number_format($data['amount'], 2) . " transferred to {$record->user->name}'s cash account.")
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Transfer Failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ]);
    }
}
