<?php

namespace App\Filament\Member\Resources;

use App\Filament\Member\Resources\MyDependentsResource\Pages;
use App\Models\Account;
use App\Models\Member;
use App\Services\AccountingService;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class MyDependentsResource extends Resource
{
    protected static ?string $model = Member::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'My Dependents';

    protected static ?int $navigationSort = 11;

    public static function getNavigationGroup(): ?string
    {
        return __('app.nav.group.account');
    }

    /** Only show in navigation if the logged-in member actually has dependents. */
    public static function shouldRegisterNavigation(): bool
    {
        $member = Member::where('user_id', auth()->id())->first();

        return $member && $member->dependents()->exists();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public static function table(Table $table): Table
    {
        $parentMember = fn() => Member::where('user_id', auth()->id())->first();

        return $table
            ->query(function () use ($parentMember) {
                $member = $parentMember();

                return Member::where('parent_id', $member?->id ?? 0);
            })
            ->columns([
                Tables\Columns\TextColumn::make('member_number')->label('Member #')->sortable(),
                Tables\Columns\TextColumn::make('user.name')->label('Name')->searchable(),
                Tables\Columns\TextColumn::make('monthly_contribution_amount')
                    ->label('Monthly Allocation')
                    ->money('SAR'),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->color(fn(string $state) => match ($state) {
                        'active' => 'success',
                        'suspended' => 'warning',
                        'delinquent', 'terminated' => 'danger',
                        default => 'gray',
                    }),
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
                        'terminated' => 'Terminated',
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
                // Parent changes the dependent's allocation
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

                // Parent funds the dependent's cash account
                Action::make('fund_cash')
                    ->label('Fund Cash Account')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->schema(function (Member $record) use ($parentMember) {
                        $parent = $parentMember();

                        return [
                            Forms\Components\Placeholder::make('balances')
                                ->label('Balances')
                                ->content(
                                    'Your cash balance: SAR ' . number_format($parent?->cash_balance ?? 0, 2) .
                                    " | {$record->user->name}'s cash balance: SAR " . number_format($record->cash_balance, 2)
                                ),
                            Forms\Components\TextInput::make('amount')
                                ->label('Amount to Transfer (SAR)')
                                ->numeric()
                                ->minValue(1)
                                ->required()
                                ->prefix('SAR'),
                            Forms\Components\TextInput::make('note')
                                ->label('Note (optional)')
                                ->maxLength(200),
                        ];
                    })
                    ->action(function (Member $record, array $data) use ($parentMember) {
                        $parent = $parentMember();

                        if (!$parent) {
                            Notification::make()->title('Your member record was not found.')->danger()->send();

                            return;
                        }

                        try {
                            app(AccountingService::class)->fundDependentCashAccount(
                                parent: $parent,
                                dependent: $record,
                                amount: (float) $data['amount'],
                                note: $data['note'] ?? '',
                            );

                            Notification::make()
                                ->title('Transfer Successful')
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
            ])
            ->emptyStateHeading('No dependents')
            ->emptyStateDescription('You have no dependent members assigned to you.');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMyDependents::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
