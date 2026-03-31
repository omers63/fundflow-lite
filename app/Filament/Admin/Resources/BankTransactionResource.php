<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\BankTransactionResource\Pages;
use App\Models\Bank;
use App\Models\BankImportSession;
use App\Models\BankTransaction;
use App\Models\Member;
use App\Services\AccountingService;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class BankTransactionResource extends Resource
{
    protected static ?string $model = BankTransaction::class;
    protected static string|\BackedEnum|null $navigationIcon = null;
    protected static ?string $navigationLabel = 'Bank Transactions';
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
                Forms\Components\TextInput::make('reference')->placeholder('—')->disabled(),
                Forms\Components\TextInput::make('member.user.name')->label('Posted to Member')->placeholder('Not yet posted')->disabled(),
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
        $memberOptions = fn () => Member::with('user')
            ->active()
            ->get()
            ->mapWithKeys(fn ($m) => [$m->id => "{$m->member_number} – {$m->user->name}"]);

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
                    ->colors(['success' => 'credit', 'danger' => 'debit']),
                Tables\Columns\TextColumn::make('reference')->placeholder('—')->searchable(),
                Tables\Columns\TextColumn::make('description')->limit(40)->placeholder('—')->searchable(),
                Tables\Columns\TextColumn::make('member.user.name')
                    ->label('Member')
                    ->placeholder('—')
                    ->searchable(),
                Tables\Columns\IconColumn::make('posted_at')
                    ->label('Posted')
                    ->boolean()
                    ->getStateUsing(fn (BankTransaction $r) => $r->posted_at !== null)
                    ->trueIcon('heroicon-o-check-badge')
                    ->falseIcon('heroicon-o-clock')
                    ->trueColor('success')
                    ->falseColor('gray'),
                Tables\Columns\IconColumn::make('is_duplicate')
                    ->label('Dup.')
                    ->boolean()
                    ->trueColor('warning')
                    ->falseColor('success')
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->falseIcon('heroicon-o-check-circle'),
            ])
            ->defaultSort('transaction_date', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('bank_id')
                    ->label('Bank')
                    ->options(Bank::active()->pluck('name', 'id')),
                Tables\Filters\SelectFilter::make('import_session_id')
                    ->label('Import Session')
                    ->options(
                        BankImportSession::with('bank')->latest()->get()
                            ->mapWithKeys(fn ($s) => [$s->id => "{$s->bank->name} — {$s->filename} ({$s->created_at->format('d M Y')})"])
                    ),
                Tables\Filters\SelectFilter::make('transaction_type')
                    ->options(['credit' => 'Credit', 'debit' => 'Debit']),
                Tables\Filters\TernaryFilter::make('is_duplicate')
                    ->label('Duplicates')
                    ->trueLabel('Duplicates only')
                    ->falseLabel('Non-duplicates only')
                    ->placeholder('All'),
                Tables\Filters\TernaryFilter::make('posted')
                    ->label('Posting Status')
                    ->trueLabel('Posted only')
                    ->falseLabel('Unposted only')
                    ->placeholder('All')
                    ->queries(
                        true:  fn ($q) => $q->whereNotNull('posted_at'),
                        false: fn ($q) => $q->whereNull('posted_at'),
                    ),
                Tables\Filters\Filter::make('date_range')
                    ->schema([
                        Forms\Components\DatePicker::make('date_from')->label('From'),
                        Forms\Components\DatePicker::make('date_to')->label('To'),
                    ])
                    ->query(fn ($query, $data) => $query
                        ->when($data['date_from'], fn ($q, $v) => $q->whereDate('transaction_date', '>=', $v))
                        ->when($data['date_to'],   fn ($q, $v) => $q->whereDate('transaction_date', '<=', $v)))
                    ->columns(2),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('post_to_cash')
                    ->label('Post to Cash')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->color('primary')
                    ->visible(fn (BankTransaction $r) => ! $r->isPosted())
                    ->schema([
                        Forms\Components\Select::make('member_id')
                            ->label('Post for Member')
                            ->options($memberOptions)
                            ->searchable()
                            ->required()
                            ->helperText('Select the member this transaction belongs to.'),
                    ])
                    ->action(function (BankTransaction $record, array $data) {
                        $member = Member::findOrFail($data['member_id']);
                        app(AccountingService::class)->postBankTransactionToCash($record, $member);

                        Notification::make()
                            ->title('Posted to Cash Account')
                            ->body("Transaction posted for {$member->user->name}.")
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('bulk_post_to_cash')
                        ->label('Post Selected to Cash Account')
                        ->icon('heroicon-o-arrow-right-circle')
                        ->color('primary')
                        ->schema([
                            Forms\Components\Select::make('member_id')
                                ->label('Post all selected for Member')
                                ->options($memberOptions)
                                ->searchable()
                                ->required()
                                ->helperText('All selected transactions will be posted to this member\'s Cash Account.'),
                        ])
                        ->action(function (Collection $records, array $data) {
                            $member    = Member::findOrFail($data['member_id']);
                            $service   = app(AccountingService::class);
                            $posted    = 0;
                            $skipped   = 0;

                            foreach ($records as $tx) {
                                if ($tx->isPosted()) {
                                    $skipped++;
                                    continue;
                                }
                                $service->postBankTransactionToCash($tx, $member);
                                $posted++;
                            }

                            Notification::make()
                                ->title('Bulk Post Complete')
                                ->body("Posted: {$posted} | Already posted (skipped): {$skipped}")
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
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
