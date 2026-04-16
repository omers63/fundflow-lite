<?php

namespace App\Filament\Member\Resources;

use App\Filament\Member\Resources\MyContributionsResource\Pages;
use App\Models\Contribution;
use Filament\Actions\Action;
use Filament\Forms;
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
                    ->label('Source')
                    ->badge()
                    ->formatStateUsing(fn(?string $state): string => Contribution::paymentMethodLabel($state)),
                Tables\Columns\TextColumn::make('reference_number')
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('paid_at')
                    ->dateTime('d M Y')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_late')
                    ->label('Late')
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->falseIcon('heroicon-o-check-circle')
                    ->trueColor('warning')
                    ->falseColor('success'),
            ])
            ->defaultSort('paid_at', 'desc')
            ->recordActions([
                Action::make('download_receipt')
                    ->label('Receipt')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->url(fn(Contribution $record): string => route('member.contribution.receipt', $record))
                    ->openUrlInNewTab(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('month')
                    ->options(array_combine(range(1, 12), array_map(fn($m) => date('F', mktime(0, 0, 0, $m, 1)), range(1, 12)))),
                Tables\Filters\Filter::make('year')
                    ->schema([Forms\Components\TextInput::make('year')->numeric()->default(now()->year)])
                    ->query(fn($query, $data) => ($data['year'] ?? null) ? $query->where('year', $data['year']) : $query),
                Tables\Filters\SelectFilter::make('payment_method')
                    ->label('Source')
                    ->options(fn(): array => Contribution::paymentMethodOptions()),
                Tables\Filters\TernaryFilter::make('is_late')
                    ->label('Late payment')
                    ->trueLabel('Late only')
                    ->falseLabel('On-time only'),
                Tables\Filters\Filter::make('paid_at')
                    ->schema([
                        Forms\Components\DatePicker::make('paid_from')->label('Paid from'),
                        Forms\Components\DatePicker::make('paid_until')->label('Paid until'),
                    ])
                    ->columns(2)
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['paid_from'] ?? null, fn($q) => $q->whereDate('paid_at', '>=', $data['paid_from']))
                            ->when($data['paid_until'] ?? null, fn($q) => $q->whereDate('paid_at', '<=', $data['paid_until']));
                    }),
                Tables\Filters\Filter::make('amount')
                    ->schema([
                        Forms\Components\TextInput::make('amount_min')->label('Min amount (SAR)')->numeric(),
                        Forms\Components\TextInput::make('amount_max')->label('Max amount (SAR)')->numeric(),
                    ])
                    ->columns(2)
                    ->query(function ($query, array $data) {
                        return $query
                            ->when(filled($data['amount_min'] ?? null), fn($q) => $q->where('amount', '>=', $data['amount_min']))
                            ->when(filled($data['amount_max'] ?? null), fn($q) => $q->where('amount', '<=', $data['amount_max']));
                    }),
            ]);
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
