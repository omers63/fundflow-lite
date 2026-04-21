<?php

namespace App\Filament\Member\Resources;

use App\Filament\Member\Resources\MyStatementsResource\Pages;
use App\Models\MonthlyStatement;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MyStatementsResource extends Resource
{
    protected static ?string $model = MonthlyStatement::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static ?string $navigationLabel = 'My Statements';

    protected static ?int $navigationSort = 2;

    public static function getNavigationLabel(): string
    {
        return __('My Statements');
    }

    public static function getModelLabel(): string
    {
        return __('app.resource.statement');
    }

    public static function getPluralModelLabel(): string
    {
        return __('My Statements');
    }

    public static function getNavigationGroup(): ?string
    {
        return 'my_finance';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(fn () => MonthlyStatement::whereHas('member', fn ($q) => $q->where('user_id', auth()->id())))
            ->columns([
                Tables\Columns\TextColumn::make('period')
                    ->label(__('app.field.period'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('opening_balance')
                    ->label(__('Opening'))
                    ->visibleFrom('md')
                    ->money('SAR'),
                Tables\Columns\TextColumn::make('total_contributions')
                    ->label(__('Contributions'))
                    ->visibleFrom('lg')
                    ->money('SAR'),
                Tables\Columns\TextColumn::make('total_repayments')
                    ->label(__('Repayments'))
                    ->visibleFrom('lg')
                    ->money('SAR'),
                Tables\Columns\TextColumn::make('closing_balance')
                    ->label(__('Closing Balance'))
                    ->money('SAR')
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('generated_at')
                    ->label(__('Generated'))
                    ->formatStateUsing(fn ($state) => $state instanceof \Carbon\CarbonInterface
                        ? $state->locale(app()->getLocale())->translatedFormat('d M Y')
                        : '')
                    ->visibleFrom('sm'),
            ])
            ->defaultSort('period', 'desc')
            ->filters([
                Tables\Filters\Filter::make('period')
                    ->schema([Forms\Components\TextInput::make('period')->placeholder(__('YYYY-MM'))])
                    ->query(fn ($query, $data) => ($data['period'] ?? null) ? $query->where('period', $data['period']) : $query),
                Tables\Filters\SelectFilter::make('period_year')
                    ->label(__('Year'))
                    ->options(
                        collect(range((int) now()->year, (int) now()->year - 15))
                            ->mapWithKeys(fn ($y) => [(string) $y => (string) $y])
                            ->all()
                    )
                    ->query(fn ($query, array $data) => filled($data['value'] ?? null)
                        ? $query->where('period', 'like', $data['value'].'-%')
                        : $query
                    ),
                Tables\Filters\Filter::make('closing_balance')
                    ->schema([
                        Forms\Components\TextInput::make('min')->label(__('Min closing (SAR)'))->numeric(),
                        Forms\Components\TextInput::make('max')->label(__('Max closing (SAR)'))->numeric(),
                    ])
                    ->columns(2)
                    ->query(function ($query, array $data) {
                        return $query
                            ->when(filled($data['min'] ?? null), fn ($q) => $q->where('closing_balance', '>=', $data['min']))
                            ->when(filled($data['max'] ?? null), fn ($q) => $q->where('closing_balance', '<=', $data['max']));
                    }),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('download')
                        ->label(__('Download PDF'))
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('gray')
                        ->url(fn (MonthlyStatement $record) => route('member.statement.pdf', $record))
                        ->openUrlInNewTab(),
                ])
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->label('')
                    ->button(),
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
