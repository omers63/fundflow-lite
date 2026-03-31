<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\BankTransactionResource\Pages;
use App\Models\Bank;
use App\Models\BankImportSession;
use App\Models\BankTransaction;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class BankTransactionResource extends Resource
{
    protected static ?string $model = BankTransaction::class;
    protected static string|\BackedEnum|null $navigationIcon = null;
    protected static ?string $navigationLabel = 'Transactions';
    protected static ?int $navigationSort = 12;

    public static function getNavigationGroup(): ?string
    {
        return 'Banking';
    }

    public static function getNavigationBadge(): ?string
    {
        $count = BankTransaction::where('is_duplicate', true)->count();
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
            Section::make()->schema([
                Forms\Components\TextInput::make('bank.name')->label('Bank')->disabled(),
                Forms\Components\TextInput::make('transaction_date')->disabled(),
                Forms\Components\TextInput::make('amount')->disabled(),
                Forms\Components\TextInput::make('transaction_type')->disabled(),
                Forms\Components\TextInput::make('reference')->disabled(),
                Forms\Components\Textarea::make('description')->disabled()->columnSpanFull(),
                Forms\Components\KeyValue::make('raw_data')
                    ->label('Raw CSV Data')
                    ->disabled()
                    ->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('bank.name')->label('Bank')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('transaction_date')
                    ->label('Date')->date('d M Y')->sortable(),
                Tables\Columns\TextColumn::make('amount')
                    ->money('SAR')
                    ->sortable()
                    ->color(fn (BankTransaction $record) => $record->transaction_type === 'credit' ? 'success' : 'danger'),
                Tables\Columns\BadgeColumn::make('transaction_type')
                    ->label('Type')
                    ->colors([
                        'success' => 'credit',
                        'danger'  => 'debit',
                    ]),
                Tables\Columns\TextColumn::make('reference')->placeholder('—')->searchable(),
                Tables\Columns\TextColumn::make('description')->limit(50)->placeholder('—')->searchable(),
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
                        BankImportSession::with('bank')
                            ->latest()
                            ->get()
                            ->mapWithKeys(fn ($s) => [$s->id => "{$s->bank->name} — {$s->filename} ({$s->created_at->format('d M Y')})"])
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
            'index' => Pages\ListBankTransactions::route('/'),
            'view'  => Pages\ViewBankTransaction::route('/{record}'),
        ];
    }
}
