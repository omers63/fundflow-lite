<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\BankImportTemplateResource\Pages;
use App\Models\Bank;
use App\Models\BankImportTemplate;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BankImportTemplateResource extends Resource
{
    protected static ?string $model = BankImportTemplate::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-table-cells';

    protected static ?string $navigationLabel = 'Import Templates';

    protected static ?int $navigationSort = 11;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Banking';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Tabs::make()->tabs([

                Tab::make('General')->schema([
                    Forms\Components\Select::make('bank_id')
                        ->label('Bank')
                        ->options(Bank::active()->pluck('name', 'id'))
                        ->required()
                        ->searchable(),
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(100)
                        ->placeholder('e.g. Al-Rajhi Statement CSV v1'),
                    Forms\Components\Toggle::make('is_default')
                        ->label('Set as default template for this bank')
                        ->helperText('Only one template per bank can be the default.'),
                ])->columns(2),

                Tab::make('CSV Format')->schema([
                    Forms\Components\Select::make('delimiter')
                        ->options([
                            ',' => 'Comma  ( , )',
                            ';' => 'Semicolon  ( ; )',
                            "\t" => 'Tab',
                            '|' => 'Pipe  ( | )',
                        ])
                        ->required()
                        ->default(','),
                    Forms\Components\Select::make('encoding')
                        ->options([
                            'UTF-8' => 'UTF-8',
                            'ISO-8859-1' => 'ISO-8859-1 (Latin-1)',
                            'Windows-1256' => 'Windows-1256 (Arabic)',
                            'Windows-1252' => 'Windows-1252 (Western)',
                        ])
                        ->required()
                        ->default('UTF-8'),
                    Forms\Components\Toggle::make('has_header')
                        ->label('File has a header row')
                        ->default(true)
                        ->live()
                        ->helperText('When enabled, use the exact column header name in the mappings below. When disabled, use the 0-based column index (0, 1, 2...).'),
                    Forms\Components\TextInput::make('skip_rows')
                        ->label('Skip rows at start')
                        ->numeric()
                        ->default(0)
                        ->minValue(0)
                        ->helperText('Number of rows to skip before the header or data (e.g. bank logo / report title rows).'),
                ])->columns(2),

                Tab::make('Column Mapping')->schema([
                    Section::make('Date')->schema([
                        Forms\Components\TextInput::make('date_column')
                            ->label('Date column')
                            ->required()
                            ->helperText('Header name or column index'),
                        Forms\Components\TextInput::make('date_format')
                            ->label('Date format (PHP)')
                            ->required()
                            ->default('Y-m-d')
                            ->helperText('e.g. d/m/Y · m/d/Y · Y-m-d · d-M-Y'),
                    ])->columns(2),

                    Section::make('Amount')->schema([
                        Forms\Components\Radio::make('amount_type')
                            ->label('Amount column structure')
                            ->options([
                                'single' => 'Single column (positive = credit, negative = debit)',
                                'split' => 'Separate credit and debit columns',
                            ])
                            ->default('single')
                            ->live(),
                        Forms\Components\TextInput::make('amount_column')
                            ->label('Amount column')
                            ->visible(fn($get) => $get('amount_type') === 'single')
                            ->required(fn($get) => $get('amount_type') === 'single')
                            ->helperText('Header name or column index'),
                        Forms\Components\TextInput::make('credit_column')
                            ->label('Credit column')
                            ->visible(fn($get) => $get('amount_type') === 'split')
                            ->required(fn($get) => $get('amount_type') === 'split')
                            ->helperText('Header name or column index'),
                        Forms\Components\TextInput::make('debit_column')
                            ->label('Debit column')
                            ->visible(fn($get) => $get('amount_type') === 'split')
                            ->required(fn($get) => $get('amount_type') === 'split')
                            ->helperText('Header name or column index'),
                    ]),

                    Section::make('Transaction Type Indicator (optional)')->schema([
                        Forms\Components\TextInput::make('type_column')
                            ->label('Type column')
                            ->helperText('Leave blank if type is determined by sign of amount or split columns'),
                        Forms\Components\TextInput::make('credit_indicator')
                            ->label('Credit indicator value')
                            ->default('CR')
                            ->helperText('e.g. CR, C, CREDIT'),
                        Forms\Components\TextInput::make('debit_indicator')
                            ->label('Debit indicator value')
                            ->default('DR')
                            ->helperText('e.g. DR, D, DEBIT'),
                    ])->columns(3),

                    Section::make('Other Columns')->schema([
                        Forms\Components\TextInput::make('description_column')
                            ->label('Description column')
                            ->helperText('Header name or column index. Optional.'),
                        Forms\Components\TextInput::make('reference_column')
                            ->label('Reference / Transaction ID column')
                            ->helperText('Header name or column index. Optional but recommended for duplicate detection.'),
                    ])->columns(2),
                ]),

                Tab::make('Duplicate Detection')->schema([
                    Forms\Components\CheckboxList::make('duplicate_match_fields')
                        ->label('Match duplicates on these fields')
                        ->options([
                            'date' => 'Transaction Date',
                            'amount' => 'Amount',
                            'type' => 'Transaction Type (credit / debit)',
                            'reference' => 'Reference Number',
                            'description' => 'Description',
                        ])
                        ->default(['date', 'amount', 'reference'])
                        ->columns(2)
                        ->helperText('A transaction will be flagged as a duplicate only if all selected fields match an existing transaction.'),
                    Forms\Components\TextInput::make('duplicate_date_tolerance')
                        ->label('Date tolerance (days)')
                        ->numeric()
                        ->default(0)
                        ->minValue(0)
                        ->maxValue(30)
                        ->helperText('Allow this many days difference when matching by date (0 = exact match).'),
                ]),

            ])->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('bank.name')->label('Bank')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\IconColumn::make('is_default')->label('Default')->boolean(),
                Tables\Columns\TextColumn::make('delimiter')
                    ->formatStateUsing(fn($state) => match ($state) {
                        ',' => 'Comma',
                        ';' => 'Semicolon',
                        "\t" => 'Tab',
                        '|' => 'Pipe',
                        default => $state,
                    }),
                Tables\Columns\IconColumn::make('has_header')->label('Has Header')->boolean(),
                Tables\Columns\TextColumn::make('amount_type')->badge()
                    ->color(fn($state) => $state === 'split' ? 'info' : 'gray'),
                Tables\Columns\TextColumn::make('duplicate_match_fields')
                    ->label('Dup. Fields')
                    ->formatStateUsing(fn($state) => is_array($state) ? implode(', ', $state) : $state),
            ])
            ->defaultSort('bank_id')
            ->filters([
                Tables\Filters\SelectFilter::make('bank_id')
                    ->label('Bank')
                    ->options(Bank::active()->pluck('name', 'id')),
                Tables\Filters\TernaryFilter::make('is_default')->label('Default template'),
                Tables\Filters\TernaryFilter::make('has_header')->label('Has header row'),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
                ForceDeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBankImportTemplates::route('/'),
            'create' => Pages\CreateBankImportTemplate::route('/create'),
            'edit' => Pages\EditBankImportTemplate::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->withTrashed();
    }
}
