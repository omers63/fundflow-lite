<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\BankImportSessionResource\Pages;
use App\Filament\Admin\Resources\BankImportSessionResource\RelationManagers\TransactionsRelationManager;
use App\Models\Bank;
use App\Models\BankImportSession;
use App\Models\BankImportTemplate;
use App\Models\BankTransaction;
use App\Models\User;
use App\Services\AccountingService;
use App\Services\BankImportService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

class BankImportSessionResource extends Resource
{
    protected static ?string $model = BankImportSession::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-up-tray';

    protected static ?string $navigationLabel = 'Import History';

    protected static ?int $navigationSort = 13;

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
            ->selectable()
            ->columns([
                Tables\Columns\TextColumn::make('bank.name')->label('Bank')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('filename')->searchable()->limit(40),
                Tables\Columns\TextColumn::make('template.name')->label('Template')->limit(30),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'completed' => 'success',
                        'processing' => 'warning',
                        'partially_completed' => 'warning',
                        'failed' => 'danger',
                        default => 'gray',
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
                Tables\Filters\SelectFilter::make('template_id')
                    ->label('Template')
                    ->searchable()
                    ->options(fn () => BankImportTemplate::query()
                        ->with('bank')
                        ->orderBy('name')
                        ->get()
                        ->mapWithKeys(fn (BankImportTemplate $t) => [
                            $t->id => ($t->bank?->name ?? '—').' — '.$t->name,
                        ])),
                Tables\Filters\SelectFilter::make('imported_by')
                    ->label('Imported by')
                    ->searchable()
                    ->options(fn () => User::query()->orderBy('name')->pluck('name', 'id')),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'processing' => 'Processing',
                        'completed' => 'Completed',
                        'partially_completed' => 'Partially Completed',
                        'failed' => 'Failed',
                    ]),
                Tables\Filters\Filter::make('imported_between')
                    ->schema([
                        Forms\Components\DatePicker::make('from')->label('From'),
                        Forms\Components\DatePicker::make('until')->label('Until'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'] ?? null, fn ($q) => $q->whereDate('created_at', '>=', $data['from']))
                            ->when($data['until'] ?? null, fn ($q) => $q->whereDate('created_at', '<=', $data['until']));
                    }),
                TrashedFilter::make(),
            ])
            ->headerActions([
                Action::make('new_import')
                    ->label('Import Bank Transactions')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('primary')
                    ->schema([
                        Forms\Components\Placeholder::make('sample_file')
                            ->label('Sample file')
                            ->content(new HtmlString(
                                '<a href="'.route('downloads.bank-import-sample').'" target="_blank" class="text-primary-600 underline">Download sample bank import CSV</a>'
                            ))
                            ->columnSpanFull(),
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
                            ->helperText('Configure CSV templates under Finance → Banking → Templates.'),
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
                        if (! Storage::disk('local')->exists((string) $data['csv_file'])) {
                            Notification::make()
                                ->title('Import file not found')
                                ->body('The uploaded CSV file could not be found in local storage. Please upload the file again.')
                                ->danger()
                                ->send();

                            return;
                        }

                        $template = BankImportTemplate::findOrFail($data['template_id']);

                        $session = BankImportSession::create([
                            'bank_id' => $data['bank_id'],
                            'template_id' => $template->id,
                            'imported_by' => auth()->id(),
                            'filename' => basename($data['csv_file']),
                            'file_path' => $data['csv_file'],
                            'notes' => $data['notes'] ?? null,
                            'status' => 'pending',
                        ]);

                        app(BankImportService::class)->import($session);

                        $session->refresh();

                        Notification::make()
                            ->title('Import '.ucfirst($session->status))
                            ->body(
                                "Imported: {$session->imported_count} | ".
                                "Duplicates: {$session->duplicate_count} | ".
                                "Errors: {$session->error_count}"
                            )
                            ->color($session->status === 'completed' ? 'success' : 'warning')
                            ->send();
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                DeleteAction::make()
                    ->label('Delete')
                    ->modalHeading('Delete import history')
                    ->modalDescription('Removes this import run and deletes every transaction from it. Posted rows are reversed in the ledger first, then soft-deleted.')
                    ->using(function (BankImportSession $record) {
                        static::deleteSessionAndTransactions($record);

                        return true;
                    }),
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
                        BankTransaction::query()
                            ->where('import_session_id', $record->id)
                            ->forceDelete();
                        $record->update([
                            'status' => 'pending',
                            'imported_count' => 0,
                            'duplicate_count' => 0,
                            'error_count' => 0,
                            'error_log' => null,
                        ]);

                        app(BankImportService::class)->import($record);

                        $record->refresh();

                        Notification::make()
                            ->title('Re-import '.ucfirst($record->status))
                            ->body("Imported: {$record->imported_count} | Duplicates: {$record->duplicate_count}")
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Delete selected')
                        ->modalHeading('Delete import history')
                        ->modalDescription('Removes the selected import runs and deletes all transactions from each. Posted rows are reversed in the ledger first, then soft-deleted.')
                        ->using(function (DeleteBulkAction $action, $records) {
                            foreach ($records as $record) {
                                try {
                                    static::deleteSessionAndTransactions($record);
                                } catch (\Throwable $e) {
                                    $action->reportBulkProcessingFailure(message: $e->getMessage());
                                    report($e);
                                }
                            }
                        }),
                ]),
            ]);
    }

    public static function deleteSessionAndTransactions(BankImportSession $record): void
    {
        DB::transaction(function () use ($record) {
            $accounting = app(AccountingService::class);
            foreach ($record->transactions()->orderBy('id')->cursor() as $tx) {
                $accounting->safeDeleteBankTransaction($tx);
            }
            $record->delete();
        });
    }

    public static function getRelations(): array
    {
        return [
            TransactionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBankImportSessions::route('/'),
            'view' => Pages\ViewBankImportSession::route('/{record}'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->withTrashed();
    }
}
