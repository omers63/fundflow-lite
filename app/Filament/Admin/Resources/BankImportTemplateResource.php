<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\BankImportTemplateResource\Pages;
use App\Models\Bank;
use App\Models\BankImportTemplate;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

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
                        ->label('Template name')
                        ->required()
                        ->maxLength(100)
                        ->placeholder('e.g. Al-Rajhi statement — current account'),
                    Forms\Components\Toggle::make('is_default')
                        ->label('Default for this bank')
                        ->helperText('When members import CSV for this bank, this template is selected first. Only one default per bank.'),
                ])->columns(2),

                Tab::make('File & CSV')->schema([
                    Section::make('Separator & character set')
                        ->description('Must match how the bank exports the file.')
                        ->compact()
                        ->schema([
                            Forms\Components\Select::make('delimiter')
                                ->label('Column separator')
                                ->options([
                                    ',' => 'Comma (,)',
                                    ';' => 'Semicolon (;)',
                                    "\t" => 'Tab',
                                    '|' => 'Pipe (|)',
                                ])
                                ->required()
                                ->default(','),
                            Forms\Components\Select::make('encoding')
                                ->label('File encoding')
                                ->options([
                                    'UTF-8' => 'UTF-8',
                                    'ISO-8859-1' => 'ISO-8859-1 (Latin-1)',
                                    'Windows-1256' => 'Windows-1256 (Arabic)',
                                    'Windows-1252' => 'Windows-1252 (Western)',
                                ])
                                ->required()
                                ->default('UTF-8'),
                        ])->columns(2),
                    Section::make('Layout')
                        ->compact()
                        ->schema([
                            Forms\Components\Toggle::make('has_header')
                                ->label('First data row is a header')
                                ->default(true)
                                ->live()
                                ->helperText('On: use exact column header text in mappings. Off: use 0-based column index (0, 1, 2…).'),
                            Forms\Components\TextInput::make('skip_rows')
                                ->label('Rows to skip before header/data')
                                ->numeric()
                                ->default(0)
                                ->minValue(0)
                                ->helperText('Use for title rows, logos, or metadata above the table.'),
                        ])->columns(2),
                ]),

                Tab::make('Column mapping')->schema([
                    Forms\Components\Placeholder::make('column_mapping_intro')
                        ->label('')
                        ->content(new HtmlString(
                            '<p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed max-w-4xl">'
                            .'Map each CSV column to a field. Amounts may include a currency code or symbol with spaces (e.g. <code class="text-xs rounded bg-gray-100 px-1 py-0.5 dark:bg-white/10">SAR 1,234.50</code>) — the importer strips that automatically.'
                            .'</p>'
                        ))
                        ->columnSpanFull(),

                    Section::make('Transaction date')
                        ->description('Which column holds the booking date, and how it is formatted.')
                        ->compact()
                        ->schema([
                            Forms\Components\TextInput::make('date_column')
                                ->label('CSV column')
                                ->required()
                                ->placeholder('Date')
                                ->helperText('Header name, or column index if the file has no header.'),
                            Forms\Components\TextInput::make('date_format')
                                ->label('PHP date format')
                                ->required()
                                ->default('Y-m-d')
                                ->placeholder('d/m/Y')
                                ->helperText('Examples: d/m/Y, m/d/Y, Y-m-d, d-M-Y'),
                        ])->columns(2),

                    Section::make('Amount')
                        ->description('How debit/credit amounts appear in the file.')
                        ->compact()
                        ->schema([
                            Forms\Components\Radio::make('amount_type')
                                ->label('Structure')
                                ->options([
                                    'single' => 'One amount column (negative often means debit)',
                                    'split' => 'Separate credit and debit columns',
                                ])
                                ->default('single')
                                ->live()
                                ->columnSpanFull(),
                            Forms\Components\TextInput::make('amount_column')
                                ->label('Amount column')
                                ->visible(fn ($get) => $get('amount_type') === 'single')
                                ->required(fn ($get) => $get('amount_type') === 'single')
                                ->placeholder('Amount')
                                ->helperText('Values may include currency text or spaces before the number.'),
                            Forms\Components\TextInput::make('credit_column')
                                ->label('Credit column')
                                ->visible(fn ($get) => $get('amount_type') === 'split')
                                ->required(fn ($get) => $get('amount_type') === 'split')
                                ->helperText('Column for incoming / credit amounts.'),
                            Forms\Components\TextInput::make('debit_column')
                                ->label('Debit column')
                                ->visible(fn ($get) => $get('amount_type') === 'split')
                                ->required(fn ($get) => $get('amount_type') === 'split')
                                ->helperText('Column for outgoing / debit amounts. Negative values are treated as debits using the absolute amount.'),
                        ])->columns(2),

                    Section::make('Optional CSV columns')
                        ->description('Map extra columns by key. Use keys reference, description, or balance to fill the main transaction fields (same as bank columns). Any other key (e.g. branch_code) is stored only on the import row. Header name or 0-based index.')
                        ->schema([
                            Forms\Components\Repeater::make('optional_columns')
                                ->label('Fields')
                                ->helperText('Example: key reference → CSV column "Reference No."; key description → "Narration"; key balance → "Balance".')
                                ->live()
                                ->schema([
                                    Forms\Components\TextInput::make('key')
                                        ->label('Key')
                                        ->required()
                                        ->maxLength(50)
                                        ->placeholder('reference')
                                        ->helperText('Use reference, description, or balance for standard fields, or any label for extras.'),
                                    Forms\Components\TextInput::make('column')
                                        ->label('CSV column')
                                        ->required()
                                        ->maxLength(100)
                                        ->placeholder('Reference or 4')
                                        ->helperText('Header name or 0-based index.'),
                                ])
                                ->defaultItems(0)
                                ->columns(2)
                                ->reorderable()
                                ->addActionLabel('Add field')
                                ->itemLabel(fn (array $state): ?string => filled($state['key'] ?? null)
                                    ? (string) $state['key']
                                    : 'New field')
                                ->columnSpanFull(),
                        ]),
                ]),

                Tab::make('Duplicate detection')->schema([
                    Section::make('Matching rules')
                        ->description('A row is treated as a duplicate only if every selected field matches an earlier transaction. Add optional column keys below when you need extra fields in the match.')
                        ->compact()
                        ->schema([
                            Forms\Components\CheckboxList::make('duplicate_match_fields')
                                ->label('Match on')
                                ->live()
                                ->options(function (Get $get): array {
                                    $options = [
                                        'date' => 'Date',
                                        'amount' => 'Amount',
                                    ];
                                    foreach ($get('optional_columns') ?? [] as $def) {
                                        if (! is_array($def)) {
                                            continue;
                                        }
                                        $key = trim((string) ($def['key'] ?? ''));
                                        if ($key === '') {
                                            continue;
                                        }
                                        if ($key === 'reference') {
                                            $options['reference'] = 'Reference';

                                            continue;
                                        }
                                        if ($key === 'description') {
                                            $options['description'] = 'Description';

                                            continue;
                                        }
                                        $options['optional:'.$key] = 'Custom: '.$key;
                                    }

                                    return $options;
                                })
                                ->default(['date', 'amount'])
                                ->columns(2)
                                ->helperText('Reference and Description appear here only when you map those keys under Optional CSV columns. If nothing valid is selected, the system uses date + amount (+ reference when mapped).'),
                            Forms\Components\TextInput::make('duplicate_date_tolerance')
                                ->label('Date tolerance (days)')
                                ->numeric()
                                ->default(0)
                                ->minValue(0)
                                ->maxValue(30)
                                ->helperText('0 = same calendar day (within the date column’s precision). Higher values allow nearby dates to match.'),
                        ]),
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
                    ->formatStateUsing(fn ($state) => match ($state) {
                        ',' => 'Comma',
                        ';' => 'Semicolon',
                        "\t" => 'Tab',
                        '|' => 'Pipe',
                        default => $state,
                    }),
                Tables\Columns\IconColumn::make('has_header')->label('Has Header')->boolean(),
                Tables\Columns\TextColumn::make('amount_type')->badge()
                    ->color(fn ($state) => $state === 'split' ? 'info' : 'gray'),
                Tables\Columns\TextColumn::make('duplicate_match_fields')
                    ->label('Dup. Fields')
                    ->formatStateUsing(fn ($state) => is_array($state) ? implode(', ', $state) : $state),
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
                ActionGroup::make([
                    EditAction::make()
                        ->modal(false)
                        ->url(fn (BankImportTemplate $record): string => static::getUrl('edit', ['record' => $record])),
                    DeleteAction::make(),
                    RestoreAction::make(),
                    ForceDeleteAction::make(),
                ]),
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
