<?php

namespace App\Filament\Member\Resources;

use App\Filament\Member\Resources\MyStatementsResource\Pages;
use App\Models\MonthlyStatement;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MyStatementsResource extends Resource
{
    protected static ?string $model = MonthlyStatement::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-chart-bar';
    protected static ?string $navigationLabel = 'My Statements';
    protected static ?int $navigationSort = 4;

    public static function getNavigationGroup(): ?string
    {
        return __('app.nav.group.my_finance');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(fn() => MonthlyStatement::whereHas('member', fn($q) => $q->where('user_id', auth()->id())))
            ->columns([
                Tables\Columns\TextColumn::make('period')
                    ->sortable(),
                Tables\Columns\TextColumn::make('opening_balance')
                    ->label('Opening')
                    ->money('SAR'),
                Tables\Columns\TextColumn::make('total_contributions')
                    ->label('Contributions')
                    ->money('SAR'),
                Tables\Columns\TextColumn::make('total_repayments')
                    ->label('Repayments')
                    ->money('SAR'),
                Tables\Columns\TextColumn::make('closing_balance')
                    ->label('Closing Balance')
                    ->money('SAR')
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('generated_at')
                    ->dateTime('d M Y')
                    ->label('Generated'),
            ])
            ->defaultSort('period', 'desc')
            ->recordActions([
                Action::make('download')
                    ->label('Download PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->url(fn(MonthlyStatement $record) => route('member.statement.pdf', $record))
                    ->openUrlInNewTab(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMyStatements::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
