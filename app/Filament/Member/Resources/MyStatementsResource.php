<?php

namespace App\Filament\Member\Resources;

use App\Filament\Member\Resources\MyStatementsResource\Pages;
use App\Models\MonthlyStatement;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MyStatementsResource extends Resource
{
    protected static ?string $model = MonthlyStatement::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static ?string $navigationLabel = 'My Statements';

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): ?string
    {
        return __('app.nav.group.my_finance');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(fn () => MonthlyStatement::whereHas('member', fn ($q) => $q->where('user_id', auth()->id())))
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
            ->filters([
                Tables\Filters\Filter::make('period')
                    ->schema([Forms\Components\TextInput::make('period')->placeholder('YYYY-MM')])
                    ->query(fn ($query, $data) => ($data['period'] ?? null) ? $query->where('period', $data['period']) : $query),
                Tables\Filters\SelectFilter::make('period_year')
                    ->label('Year')
                    ->options(
                        collect(range((int) now()->year, (int) now()->year - 15))
                            ->mapWithKeys(fn($y) => [(string) $y => (string) $y])
                            ->all()
                    )
                    ->query(fn($query, array $data) => filled($data['value'] ?? null)
                        ? $query->where('period', 'like', $data['value'] . '-%')
                        : $query
                    ),
                Tables\Filters\Filter::make('closing_balance')
                    ->schema([
                        Forms\Components\TextInput::make('min')->label('Min closing (SAR)')->numeric(),
                        Forms\Components\TextInput::make('max')->label('Max closing (SAR)')->numeric(),
                    ])
                    ->columns(2)
                    ->query(function ($query, array $data) {
                        return $query
                            ->when(filled($data['min'] ?? null), fn ($q) => $q->where('closing_balance', '>=', $data['min']))
                            ->when(filled($data['max'] ?? null), fn ($q) => $q->where('closing_balance', '<=', $data['max']));
                    }),
            ])
            ->recordActions([
                Action::make('download')
                    ->label('Download PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->url(fn (MonthlyStatement $record) => route('member.statement.pdf', $record))
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
