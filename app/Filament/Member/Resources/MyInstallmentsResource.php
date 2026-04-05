<?php

namespace App\Filament\Member\Resources;

use App\Filament\Member\Resources\MyInstallmentsResource\Pages;
use App\Models\Loan;
use App\Models\LoanInstallment;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MyInstallmentsResource extends Resource
{
    protected static ?string $model = LoanInstallment::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationLabel = 'My Installments';

    protected static ?int $navigationSort = 3;

    public static function getNavigationGroup(): ?string
    {
        return __('app.nav.group.my_finance');
    }

    public static function getNavigationBadge(): ?string
    {
        $member = auth()->user()?->member;
        if (!$member) {
            return null;
        }
        $count = LoanInstallment::whereHas('loan', fn($q) => $q->where('member_id', $member->id))
            ->where('status', 'overdue')
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'danger';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(function () {
                $member = auth()->user()?->member;

                return LoanInstallment::whereHas(
                    'loan',
                    fn($q) => $q->where('member_id', $member?->id ?? 0)
                );
            })
            ->columns([
                Tables\Columns\TextColumn::make('loan_id')
                    ->label('Loan #')
                    ->sortable(),
                Tables\Columns\TextColumn::make('installment_number')
                    ->label('#'),
                Tables\Columns\TextColumn::make('amount')
                    ->money('SAR')
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('due_date')
                    ->date('d M Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state) => match ($state) {
                        'pending' => 'warning',
                        'paid' => 'success',
                        'overdue' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('paid_at')
                    ->dateTime('d M Y')
                    ->placeholder('—'),
            ])
            ->defaultSort('due_date', 'asc')
            ->filters([
                Tables\Filters\SelectFilter::make('loan_id')
                    ->label('Loan')
                    ->options(function () {
                        $member = auth()->user()?->member;
                        if (!$member) {
                            return [];
                        }

                        return Loan::query()->where('member_id', $member->id)->orderByDesc('id')->get()
                            ->mapWithKeys(fn(Loan $l) => [$l->id => 'Loan #' . $l->id]);
                    }),
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
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMyInstallments::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
