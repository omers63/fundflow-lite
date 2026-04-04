<?php

namespace App\Filament\Member\Resources;

use App\Filament\Member\Resources\MyContributionsResource\Pages;
use App\Models\Contribution;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MyContributionsResource extends Resource
{
    protected static ?string $model = Contribution::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationLabel = 'My Contributions';
    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): ?string
    {
        return __('app.nav.group.my_finance');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(fn() => Contribution::whereHas('member', fn($q) => $q->where('user_id', auth()->id())))
            ->columns([
                Tables\Columns\TextColumn::make('amount')
                    ->money('SAR')
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('month')
                    ->formatStateUsing(fn($state) => date('F', mktime(0, 0, 0, $state, 1))),
                Tables\Columns\TextColumn::make('year'),
                Tables\Columns\TextColumn::make('payment_method')
                    ->badge()
                    ->formatStateUsing(fn($state) => match ($state) {
                        'cash' => 'Cash',
                        'bank_transfer' => 'Bank Transfer',
                        'online' => 'Online',
                        default => $state ?? '-',
                    }),
                Tables\Columns\TextColumn::make('reference_number')
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('paid_at')
                    ->dateTime('d M Y')
                    ->sortable(),
            ])
            ->defaultSort('paid_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMyContributions::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
