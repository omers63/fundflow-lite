<?php

namespace App\Filament\Member\Resources;

use App\Filament\Member\Resources\MyInstallmentsResource\Pages;
use App\Models\LoanInstallment;
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
        if (! $member) {
            return null;
        }
        $count = LoanInstallment::whereHas('loan', fn ($q) => $q->where('member_id', $member->id))
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
                    fn ($q) => $q->where('member_id', $member?->id ?? 0)
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
                    ->color(fn (string $state) => match ($state) {
                        'pending' => 'warning',
                        'paid' => 'success',
                        'overdue' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('paid_at')
                    ->dateTime('d M Y')
                    ->placeholder('—'),
            ])
            ->defaultSort('due_date', 'asc');
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
