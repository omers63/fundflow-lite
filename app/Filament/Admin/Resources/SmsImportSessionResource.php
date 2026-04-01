<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\SmsImportSessionResource\Pages;
use App\Filament\Admin\Resources\SmsImportSessionResource\RelationManagers\TransactionsRelationManager;
use App\Models\Bank;
use App\Models\SmsImportSession;
use App\Models\SmsImportTemplate;
use App\Services\SmsImportService;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class SmsImportSessionResource extends Resource
{
    protected static ?string $model = SmsImportSession::class;
    protected static string|\BackedEnum|null $navigationIcon = null;
    protected static ?string $navigationLabel = 'SMS Import History';
    protected static ?int $navigationSort = 23;

    public static function getNavigationGroup(): ?string
    {
        return 'Banking';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make()->schema([
                Forms\Components\TextInput::make('bank.name')->label('Bank')->disabled(),
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
                Tables\Columns\TextColumn::make('bank.name')->label('Bank')->placeholder('—')->sortable(),
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
                Action::make('new_sms_import')
                    ->label('Import SMS File')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('primary')
                    ->schema([
                        Forms\Components\Select::make('bank_id')
                            ->label('Bank (optional)')
                            ->options(Bank::active()->pluck('name', 'id'))
                            ->nullable()
                            ->live()
                            ->afterStateUpdated(fn ($set) => $set('template_id', null)),
                        Forms\Components\Select::make('template_id')
                            ->label('SMS Template')
                            ->options(fn ($get) => SmsImportTemplate::when(
                                $get('bank_id'),
                                fn ($q, $id) => $q->where('bank_id', $id)
                            )->pluck('name', 'id'))
                            ->required()
                            ->live()
                            ->helperText('Configure templates under Banking → SMS Templates'),
                        Forms\Components\FileUpload::make('csv_file')
                            ->label('CSV / Text File')
                            ->disk('local')
                            ->directory('sms-imports')
                            ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'])
                            ->required(),
                        Forms\Components\Textarea::make('notes')
                            ->label('Notes (optional)')
                            ->rows(2),
                    ])
                    ->action(function (array $data) {
                        $template = SmsImportTemplate::findOrFail($data['template_id']);

                        $session = SmsImportSession::create([
                            'bank_id'     => $data['bank_id'] ?? $template->bank_id,
                            'template_id' => $template->id,
                            'imported_by' => auth()->id(),
                            'filename'    => basename($data['csv_file']),
                            'file_path'   => $data['csv_file'],
                            'notes'       => $data['notes'] ?? null,
                            'status'      => 'pending',
                        ]);

                        app(SmsImportService::class)->import($session);

                        $session->refresh();

                        Notification::make()
                            ->title('SMS Import ' . ucfirst(str_replace('_', ' ', $session->status)))
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
                    ->url(fn (SmsImportSession $record) => SmsTransactionResource::getUrl('index', [
                        'tableFilters[import_session_id][value]' => $record->id,
                    ])),
                Action::make('retry')
                    ->label('Re-import')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn (SmsImportSession $record) => in_array($record->status, ['failed', 'partially_completed']))
                    ->requiresConfirmation()
                    ->action(function (SmsImportSession $record) {
                        $record->transactions()->delete();
                        $record->update([
                            'status'          => 'pending',
                            'imported_count'  => 0,
                            'duplicate_count' => 0,
                            'error_count'     => 0,
                            'error_log'       => null,
                        ]);

                        app(SmsImportService::class)->import($record);
                        $record->refresh();

                        Notification::make()
                            ->title('Re-import ' . ucfirst(str_replace('_', ' ', $record->status)))
                            ->body("Imported: {$record->imported_count} | Duplicates: {$record->duplicate_count}")
                            ->success()
                            ->send();
                    }),
            ]);
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
            'index' => Pages\ListSmsImportSessions::route('/'),
            'view'  => Pages\ViewSmsImportSession::route('/{record}'),
        ];
    }
}
