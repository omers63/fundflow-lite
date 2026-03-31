<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\BankImportSessionResource\Pages;
use App\Models\Bank;
use App\Models\BankImportSession;
use App\Models\BankImportTemplate;
use App\Services\BankImportService;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class BankImportSessionResource extends Resource
{
    protected static ?string $model = BankImportSession::class;
    protected static string|\BackedEnum|null $navigationIcon = null;
    protected static ?string $navigationLabel = 'Import History';
    protected static ?int $navigationSort = 13;

    public static function getNavigationGroup(): ?string
    {
        return 'Banking';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make()->schema([
                Forms\Components\Select::make('bank_id')->label('Bank')
                    ->options(Bank::active()->pluck('name', 'id'))
                    ->disabled()->dehydrated(false),
                Forms\Components\TextInput::make('filename')->disabled(),
                Forms\Components\TextInput::make('status')->disabled(),
                Forms\Components\TextInput::make('total_rows')->disabled(),
                Forms\Components\TextInput::make('imported_count')->disabled(),
                Forms\Components\TextInput::make('duplicate_count')->disabled(),
                Forms\Components\TextInput::make('error_count')->disabled(),
                Forms\Components\Textarea::make('notes')->disabled()->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('bank.name')->label('Bank')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('filename')->searchable()->limit(40),
                Tables\Columns\TextColumn::make('template.name')->label('Template')->limit(30),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'completed'           => 'success',
                        'processing'          => 'warning',
                        'partially_completed' => 'warning',
                        'failed'              => 'danger',
                        default               => 'gray',
                    }),
                Tables\Columns\TextColumn::make('total_rows')->label('Rows')->alignCenter(),
                Tables\Columns\TextColumn::make('imported_count')->label('Imported')
                    ->color('success')->alignCenter(),
                Tables\Columns\TextColumn::make('duplicate_count')->label('Duplicates')
                    ->color('warning')->alignCenter(),
                Tables\Columns\TextColumn::make('error_count')->label('Errors')
                    ->color('danger')->alignCenter(),
                Tables\Columns\TextColumn::make('importer.name')->label('Imported By'),
                Tables\Columns\TextColumn::make('created_at')->label('Date')
                    ->dateTime('d M Y H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('bank_id')->label('Bank')
                    ->options(Bank::active()->pluck('name', 'id')),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'completed'           => 'Completed',
                        'partially_completed' => 'Partially Completed',
                        'failed'              => 'Failed',
                        'processing'          => 'Processing',
                    ]),
            ])
            ->headerActions([
                Action::make('new_import')
                    ->label('Import CSV')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('primary')
                    ->schema([
                        Forms\Components\Select::make('bank_id')
                            ->label('Bank')
                            ->options(Bank::active()->pluck('name', 'id'))
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn ($set) => $set('template_id', null)),
                        Forms\Components\Select::make('template_id')
                            ->label('Import Template')
                            ->options(fn ($get) => BankImportTemplate::where('bank_id', $get('bank_id'))
                                ->pluck('name', 'id'))
                            ->required()
                            ->live()
                            ->helperText('Configure templates under Banking → Import Templates'),
                        Forms\Components\FileUpload::make('csv_file')
                            ->label('CSV File')
                            ->disk('local')
                            ->directory('bank-imports')
                            ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'])
                            ->required(),
                        Forms\Components\Textarea::make('notes')
                            ->label('Notes (optional)')
                            ->rows(2),
                    ])
                    ->action(function (array $data) {
                        $template = BankImportTemplate::findOrFail($data['template_id']);

                        $session = BankImportSession::create([
                            'bank_id'     => $data['bank_id'],
                            'template_id' => $template->id,
                            'imported_by' => auth()->id(),
                            'filename'    => basename($data['csv_file']),
                            'file_path'   => $data['csv_file'],
                            'notes'       => $data['notes'] ?? null,
                            'status'      => 'pending',
                        ]);

                        app(BankImportService::class)->import($session);

                        $session->refresh();

                        Notification::make()
                            ->title('Import ' . ucfirst($session->status))
                            ->body(
                                "Imported: {$session->imported_count} | " .
                                "Duplicates: {$session->duplicate_count} | " .
                                "Errors: {$session->error_count}"
                            )
                            ->color($session->status === 'completed' ? 'success' : 'warning')
                            ->send();
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('view_transactions')
                    ->label('Transactions')
                    ->icon('heroicon-o-table-cells')
                    ->url(fn (BankImportSession $record) => BankTransactionResource::getUrl('index', [
                        'tableFilters[import_session_id][value]' => $record->id,
                    ])),
                Action::make('retry')
                    ->label('Re-import')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn (BankImportSession $record) => in_array($record->status, ['failed', 'partially_completed']))
                    ->requiresConfirmation()
                    ->action(function (BankImportSession $record) {
                        $record->transactions()->delete();
                        $record->update([
                            'status'          => 'pending',
                            'imported_count'  => 0,
                            'duplicate_count' => 0,
                            'error_count'     => 0,
                            'error_log'       => null,
                        ]);

                        app(BankImportService::class)->import($record);

                        $record->refresh();

                        Notification::make()
                            ->title('Re-import ' . ucfirst($record->status))
                            ->body("Imported: {$record->imported_count} | Duplicates: {$record->duplicate_count}")
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBankImportSessions::route('/'),
            'view'  => Pages\ViewBankImportSession::route('/{record}'),
        ];
    }
}
