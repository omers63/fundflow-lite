<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\ContributionResource\Pages;
use App\Models\Contribution;
use App\Models\Member;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class ContributionResource extends Resource
{
    protected static ?string $model = Contribution::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): ?string
    {
        return __('app.nav.group.finance');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Forms\Components\Select::make('member_id')
                ->label('Member')
                ->options(fn() => Member::with('user')
                    ->get()
                    ->pluck('user.name', 'id')
                    ->prepend('-- Select Member --', ''))
                ->searchable()
                ->required(),
            Forms\Components\TextInput::make('amount')
                ->numeric()
                ->prefix('SAR')
                ->required()
                ->minValue(0),
            Forms\Components\Select::make('month')
                ->options(array_combine(range(1, 12), array_map(fn($m) => date('F', mktime(0, 0, 0, $m, 1)), range(1, 12))))
                ->required(),
            Forms\Components\TextInput::make('year')
                ->numeric()
                ->default(now()->year)
                ->required(),
            Forms\Components\DateTimePicker::make('paid_at')
                ->label('Payment Date')
                ->default(now()),
            Forms\Components\Select::make('payment_method')
                ->options(['cash' => 'Cash', 'bank_transfer' => 'Bank Transfer', 'online' => 'Online Payment'])
                ->nullable(),
            Forms\Components\TextInput::make('reference_number')
                ->label('Reference #')
                ->nullable(),
            Forms\Components\Textarea::make('notes')
                ->rows(2)
                ->nullable(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('member.member_number')
                    ->label('Member #')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('member.user.name')
                    ->label('Member Name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('amount')
                    ->money('SAR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('month')
                    ->formatStateUsing(fn($state) => date('F', mktime(0, 0, 0, $state, 1))),
                Tables\Columns\TextColumn::make('year')
                    ->sortable(),
                Tables\Columns\TextColumn::make('payment_method')
                    ->badge()
                    ->formatStateUsing(fn($state) => match ($state) {
                        'cash' => 'Cash',
                        'bank_transfer' => 'Bank Transfer',
                        'online' => 'Online',
                        default => $state ?? '-',
                    }),
                Tables\Columns\TextColumn::make('paid_at')
                    ->dateTime('d M Y')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_late')
                    ->label('Late?')
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->falseIcon('heroicon-o-check-circle')
                    ->trueColor('warning')
                    ->falseColor('success'),
            ])
            ->defaultSort('paid_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('member_id')
                    ->label('Member')
                    ->searchable()
                    ->options(fn() => Member::with('user')->orderBy('member_number')->get()
                        ->mapWithKeys(fn(Member $m) => [$m->id => "{$m->member_number} – {$m->user->name}"])),
                Tables\Filters\SelectFilter::make('month')
                    ->options(array_combine(range(1, 12), array_map(fn($m) => date('F', mktime(0, 0, 0, $m, 1)), range(1, 12)))),
                Tables\Filters\Filter::make('year')
                    ->schema([Forms\Components\TextInput::make('year')->numeric()->default(now()->year)])
                    ->query(fn($query, $data) => $data['year'] ? $query->where('year', $data['year']) : $query),
                Tables\Filters\SelectFilter::make('payment_method')
                    ->options(['cash' => 'Cash', 'bank_transfer' => 'Bank Transfer', 'online' => 'Online Payment']),
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
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContributions::route('/'),
            'create' => Pages\CreateContribution::route('/create'),
            'edit' => Pages\EditContribution::route('/{record}/edit'),
        ];
    }
}
