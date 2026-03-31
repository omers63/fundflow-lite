<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\SmsImportTemplateResource\Pages;
use App\Models\Bank;
use App\Models\SmsImportTemplate;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class SmsImportTemplateResource extends Resource
{
    protected static ?string $model = SmsImportTemplate::class;
    protected static string|\BackedEnum|null $navigationIcon = null;
    protected static ?string $navigationLabel = 'SMS Templates';
    protected static ?int $navigationSort = 21;

    public static function getNavigationGroup(): ?string
    {
        return 'Banking';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Tabs::make()->tabs([

                // ── Tab 1: General ───────────────────────────────────────
                Tab::make('General')->schema([
                    Forms\Components\Select::make('bank_id')
                        ->label('Bank (optional)')
                        ->options(Bank::active()->pluck('name', 'id'))
                        ->searchable()
                        ->nullable()
                        ->helperText('Associating a bank scopes duplicate detection to that bank.'),
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(100)
                        ->placeholder('e.g. Al-Rajhi SMS Export v1'),
                    Forms\Components\Toggle::make('is_default')
                        ->label('Set as default template for this bank'),
                    Forms\Components\Select::make('default_transaction_type')
                        ->label('Default type when no keyword matches')
                        ->options(['credit' => 'Credit', 'debit' => 'Debit'])
                        ->default('credit')
                        ->required(),
                ])->columns(2),

                // ── Tab 2: CSV Format ─────────────────────────────────────
                Tab::make('CSV Format')->schema([
                    Forms\Components\Select::make('delimiter')
                        ->options([
                            ','  => 'Comma  ( , )',
                            ';'  => 'Semicolon  ( ; )',
                            "\t" => 'Tab',
                            '|'  => 'Pipe  ( | )',
                        ])
                        ->required()
                        ->default(','),
                    Forms\Components\Select::make('encoding')
                        ->options([
                            'UTF-8'        => 'UTF-8',
                            'ISO-8859-1'   => 'ISO-8859-1 (Latin-1)',
                            'Windows-1256' => 'Windows-1256 (Arabic)',
                            'Windows-1252' => 'Windows-1252 (Western)',
                        ])
                        ->required()
                        ->default('UTF-8'),
                    Forms\Components\Toggle::make('has_header')
                        ->label('File has a header row')
                        ->default(true)
                        ->helperText('When enabled, use exact column header names in the mappings below. When disabled, use 0-based column indices.'),
                    Forms\Components\TextInput::make('skip_rows')
                        ->label('Skip rows at start')
                        ->numeric()
                        ->default(0)
                        ->minValue(0),
                ])->columns(2),

                // ── Tab 3: Column Mapping ─────────────────────────────────
                Tab::make('Column Mapping')->schema([
                    Section::make('SMS Text')->schema([
                        Forms\Components\TextInput::make('sms_column')
                            ->label('SMS text column')
                            ->required()
                            ->helperText('Header name or 0-based index of the column that contains the raw SMS message.'),
                    ]),

                    Section::make('Date Column (optional)')->schema([
                        Forms\Components\TextInput::make('date_column')
                            ->label('Date column')
                            ->helperText('If the CSV has a separate date column, specify it here. Leave blank to extract the date from the SMS text using the pattern below.'),
                        Forms\Components\TextInput::make('date_format')
                            ->label('Date format (PHP)')
                            ->default('Y-m-d H:i:s')
                            ->helperText('e.g. Y-m-d H:i:s · d/m/Y · d-M-Y · m/d/Y H:i'),
                    ])->columns(2),
                ]),

                // ── Tab 4: SMS Parsing Rules ──────────────────────────────
                Tab::make('SMS Parsing Rules')->schema([
                    Section::make('Amount Extraction')->schema([
                        Forms\Components\TextInput::make('amount_pattern')
                            ->label('Amount regex pattern')
                            ->placeholder('/SAR\s*(?P<amount>[\d,]+\.?\d*)/i')
                            ->helperText('Must contain a named capture group called "amount". Wrap with / delimiters or leave bare. Thousands commas are stripped automatically.')
                            ->columnSpanFull(),
                        Forms\Components\Placeholder::make('amount_hint')
                            ->label('')
                            ->content('Examples: /SAR\s*(?P<amount>[\d,]+\.?\d*)/i  ·  /Amount:\s*(?P<amount>[\d.]+)/  ·  /(?P<amount>[\d,]+\.\d{2})\s*SAR/i'),
                    ]),

                    Section::make('Date Extraction from SMS (used when no date column is mapped)')->schema([
                        Forms\Components\TextInput::make('date_pattern')
                            ->label('Date regex pattern')
                            ->placeholder('/on\s+(?P<date>\d{2}\/\d{2}\/\d{4})/i')
                            ->helperText('Must contain a named capture group called "date".')
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('date_pattern_format')
                            ->label('Date format for extracted value (PHP)')
                            ->placeholder('d/m/Y')
                            ->helperText('PHP format string matching the captured date string.'),
                        Forms\Components\Placeholder::make('date_hint')
                            ->label('')
                            ->content('Example pattern: /on\s+(?P<date>\d{2}\/\d{2}\/\d{4})/i  →  format: d/m/Y'),
                    ])->columns(2),

                    Section::make('Reference Extraction')->schema([
                        Forms\Components\TextInput::make('reference_pattern')
                            ->label('Reference regex pattern')
                            ->placeholder('/[Rr]ef[:\s]+(?P<reference>\d+)/')
                            ->helperText('Must contain a named capture group called "reference".')
                            ->columnSpanFull(),
                        Forms\Components\Placeholder::make('reference_hint')
                            ->label('')
                            ->content('Examples: /[Rr]ef[:\s]+(?P<reference>\w+)/  ·  /TRN[:\s]*(?P<reference>\d+)/i'),
                    ]),

                    Section::make('Transaction Type Detection')->schema([
                        Forms\Components\TagsInput::make('credit_keywords')
                            ->label('Credit keywords')
                            ->default(['credited', 'received', 'deposit', 'credit'])
                            ->helperText('If any of these words (case-insensitive) are found in the SMS text, the transaction is classified as Credit. Press Enter after each keyword.')
                            ->separator(','),
                        Forms\Components\TagsInput::make('debit_keywords')
                            ->label('Debit keywords')
                            ->default(['debited', 'paid', 'purchase', 'debit', 'withdraw'])
                            ->helperText('If any of these words (case-insensitive) are found in the SMS text, the transaction is classified as Debit.')
                            ->separator(','),
                    ])->columns(2),
                ]),

                // ── Tab 5: Duplicate Detection ────────────────────────────
                Tab::make('Duplicate Detection')->schema([
                    Forms\Components\CheckboxList::make('duplicate_match_fields')
                        ->label('Match duplicates on these fields')
                        ->options([
                            'date'      => 'Transaction Date',
                            'amount'    => 'Amount',
                            'type'      => 'Transaction Type (credit / debit)',
                            'reference' => 'Reference Number',
                            'raw_sms'   => 'Exact SMS Text',
                        ])
                        ->default(['date', 'amount', 'reference'])
                        ->columns(2)
                        ->helperText('A message is flagged as a duplicate only when ALL selected fields match an existing record.'),
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
                Tables\Columns\TextColumn::make('bank.name')->label('Bank')->placeholder('Any')->sortable(),
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\IconColumn::make('is_default')->label('Default')->boolean(),
                Tables\Columns\TextColumn::make('delimiter')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        ','  => 'Comma',
                        ';'  => 'Semicolon',
                        "\t" => 'Tab',
                        '|'  => 'Pipe',
                        default => $state,
                    }),
                Tables\Columns\IconColumn::make('has_header')->label('Has Header')->boolean(),
                Tables\Columns\TextColumn::make('sms_column')->label('SMS Col.'),
                Tables\Columns\TextColumn::make('duplicate_match_fields')
                    ->label('Dup. Fields')
                    ->formatStateUsing(fn ($state) => is_array($state) ? implode(', ', $state) : $state),
            ])
            ->defaultSort('name')
            ->filters([
                Tables\Filters\SelectFilter::make('bank_id')
                    ->label('Bank')
                    ->options(Bank::active()->pluck('name', 'id')),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListSmsImportTemplates::route('/'),
            'create' => Pages\CreateSmsImportTemplate::route('/create'),
            'edit'   => Pages\EditSmsImportTemplate::route('/{record}/edit'),
        ];
    }
}
