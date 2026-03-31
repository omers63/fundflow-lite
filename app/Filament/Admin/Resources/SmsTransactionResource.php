<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\SmsTransactionResource\Pages;
use App\Models\Bank;
use App\Models\SmsImportSession;
use App\Models\SmsTransaction;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class SmsTransactionResource extends Resource
{
    protected static ?string $model = SmsTransaction::class;
    protected static string|\BackedEnum|null $navigationIcon = null;
    protected static ?string $navigationLabel = 'SMS Transactions';
    protected static ?int $navigationSort = 22;

    public static function getNavigationGroup(): ?string
    {
        return 'Banking';
    }

    public static function getNavigationBadge(): ?string
    {
        $count = SmsTransaction::where('is_duplicate', true)->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Flagged duplicates';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Transaction Details')->schema([
                Forms\Components\TextInput::make('bank.name')->label('Bank')->disabled(),
                Forms\Components\TextInput::make('transaction_date')->disabled(),
                Forms\Components\TextInput::make('amount')->disabled(),
                Forms\Components\TextInput::make('transaction_type')->disabled(),
                Forms\Components\TextInput::make('reference')->disabled(),
            ])->columns(2),

            Section::make('Raw SMS')->schema([
                Forms\Components\Textarea::make('raw_sms')
                    ->label('Original SMS Text')
                    ->disabled()
                    ->rows(4)
                    ->columnSpanFull(),
            ]),

            Section::make('Raw CSV Row')->schema([
                Forms\Components\KeyValue::make('raw_data')
                    ->label('')
                    ->disabled()
                    ->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('bank.name')->label('Bank')
                    ->placeholder('—')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('transaction_date')
                    ->label('Date')->date('d M Y')->sortable(),
                Tables\Columns\TextColumn::make('amount')
                    ->money('SAR')
                    ->sortable()
                    ->color(fn (SmsTransaction $record) => $record->transaction_type === 'credit' ? 'success' : 'danger'),
                Tables\Columns\BadgeColumn::make('transaction_type')
                    ->label('Type')
                    ->colors([
                        'success' => 'credit',
                        'danger'  => 'debit',
                    ]),
                Tables\Columns\TextColumn::make('reference')->placeholder('—')->searchable(),
                Tables\Columns\TextColumn::make('raw_sms')
                    ->label('SMS')
                    ->limit(60)
                    ->tooltip(fn (SmsTransaction $record) => $record->raw_sms)
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_duplicate')
                    ->label('Duplicate')
                    ->boolean()
                    ->trueColor('warning')
                    ->falseColor('success')
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->falseIcon('heroicon-o-check-circle'),
                Tables\Columns\TextColumn::make('importSession.created_at')
                    ->label('Import Date')->date('d M Y')->sortable(),
            ])
            ->defaultSort('transaction_date', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('bank_id')
                    ->label('Bank')
                    ->options(Bank::active()->pluck('name', 'id')),
                Tables\Filters\SelectFilter::make('import_session_id')
                    ->label('Import Session')
                    ->options(
                        SmsImportSession::with('bank')
                            ->latest()
                            ->get()
                            ->mapWithKeys(fn ($s) => [
                                $s->id => ($s->bank?->name ?? 'No Bank') . ' — ' . $s->filename . ' (' . $s->created_at->format('d M Y') . ')',
                            ])
                    ),
                Tables\Filters\SelectFilter::make('transaction_type')
                    ->options(['credit' => 'Credit', 'debit' => 'Debit']),
                Tables\Filters\TernaryFilter::make('is_duplicate')
                    ->label('Duplicates')
                    ->trueLabel('Duplicates only')
                    ->falseLabel('Non-duplicates only')
                    ->placeholder('All transactions'),
                Tables\Filters\Filter::make('date_range')
                    ->schema([
                        Forms\Components\DatePicker::make('date_from')->label('From'),
                        Forms\Components\DatePicker::make('date_to')->label('To'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['date_from'], fn ($q, $v) => $q->whereDate('transaction_date', '>=', $v))
                            ->when($data['date_to'],   fn ($q, $v) => $q->whereDate('transaction_date', '<=', $v));
                    })
                    ->columns(2),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSmsTransactions::route('/'),
            'view'  => Pages\ViewSmsTransaction::route('/{record}'),
        ];
    }
}
