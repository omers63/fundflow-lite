<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\SmsTransactionResource\Pages;
use App\Models\Bank;
use App\Models\Member;
use App\Models\SmsImportSession;
use App\Models\SmsTransaction;
use App\Services\AccountingService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
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
use Illuminate\Database\Eloquent\Collection;

class SmsTransactionResource extends Resource
{
    protected static ?string $model = SmsTransaction::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-device-phone-mobile';

    protected static ?string $navigationLabel = 'SMS Transactions';

    protected static ?int $navigationSort = 22;

    public static function getNavigationLabel(): string
    {
        return __('SMS Transactions');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function getNavigationGroup(): ?string
    {
        return __('app.nav.group.finance');
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
        return __('Flagged duplicates');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make(__('Transaction Details'))->schema([
                Forms\Components\TextInput::make('bank.name')->label(__('Bank'))->disabled(),
                Forms\Components\TextInput::make('transaction_date')->disabled(),
                Forms\Components\TextInput::make('amount')->disabled(),
                Forms\Components\TextInput::make('transaction_type')->disabled(),
                Forms\Components\TextInput::make('reference')->placeholder('—')->disabled(),
                Forms\Components\TextInput::make('member.user.name')
                    ->label(__('Auto-matched / Posted Member'))
                    ->placeholder(__('Not matched'))
                    ->disabled(),
            ])->columns(2),

            Section::make(__('Raw SMS'))->schema([
                Forms\Components\Textarea::make('raw_sms')
                    ->label(__('Original SMS Text'))
                    ->disabled()
                    ->rows(4)
                    ->columnSpanFull(),
            ]),

            Section::make(__('Raw CSV Row'))->schema([
                Forms\Components\KeyValue::make('raw_data')
                    ->label('')
                    ->disabled()
                    ->columnSpanFull(),
            ]),
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
                Tables\Columns\TextColumn::make('bank.name')->label(__('Bank'))
                    ->placeholder('—')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('transaction_date')
                    ->label(__('Date'))->date('d M Y')->sortable(),
                Tables\Columns\TextColumn::make('amount')
                    ->money('SAR')
                    ->sortable()
                    ->color(fn (SmsTransaction $record) => $record->transaction_type === 'credit' ? 'success' : 'danger'),
                Tables\Columns\BadgeColumn::make('transaction_type')
                    ->label(__('Type'))
                    ->colors(['success' => 'credit', 'danger' => 'debit']),
                Tables\Columns\TextColumn::make('reference')->placeholder('—')->searchable(),
                Tables\Columns\TextColumn::make('member.user.name')
                    ->label(__('Member'))
                    ->placeholder('—')
                    ->searchable(),
                Tables\Columns\TextColumn::make('raw_sms')
                    ->label(__('SMS'))
                    ->limit(55)
                    ->tooltip(fn (SmsTransaction $record) => $record->raw_sms)
                    ->searchable(),
                Tables\Columns\IconColumn::make('posted_at')
                    ->label(__('Posted'))
                    ->boolean()
                    ->getStateUsing(fn (SmsTransaction $r) => $r->posted_at !== null)
                    ->trueIcon('heroicon-o-check-badge')
                    ->falseIcon('heroicon-o-clock')
                    ->trueColor('success')
                    ->falseColor('gray'),
                Tables\Columns\IconColumn::make('is_duplicate')
                    ->label(__('Dup.'))
                    ->boolean()
                    ->trueColor('warning')
                    ->falseColor('success')
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->falseIcon('heroicon-o-check-circle'),
            ])
            ->defaultSort('transaction_date', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('bank_id')
                    ->label(__('Bank'))
                    ->options(Bank::active()->pluck('name', 'id')),
                Tables\Filters\SelectFilter::make('import_session_id')
                    ->label(__('Import Session'))
                    ->options(
                        SmsImportSession::with('bank')->latest()->get()
                            ->mapWithKeys(fn ($s) => [
                                $s->id => ($s->bank?->name ?? __('No Bank')).' — '.$s->filename.' ('.$s->created_at->format('d M Y').')',
                            ])
                    ),
                Tables\Filters\SelectFilter::make('transaction_type')
                    ->options(['credit' => __('Credit'), 'debit' => __('Debit')]),
                Tables\Filters\TernaryFilter::make('has_member')
                    ->label(__('Member Matched'))
                    ->trueLabel(__('Matched only'))
                    ->falseLabel(__('Unmatched only'))
                    ->placeholder(__('All'))
                    ->queries(
                        true: fn ($q) => $q->whereNotNull('member_id'),
                        false: fn ($q) => $q->whereNull('member_id'),
                    ),
                Tables\Filters\TernaryFilter::make('posted')
                    ->label(__('Posting Status'))
                    ->trueLabel(__('Posted only'))
                    ->falseLabel(__('Unposted only'))
                    ->placeholder(__('All'))
                    ->queries(
                        true: fn ($q) => $q->whereNotNull('posted_at'),
                        false: fn ($q) => $q->whereNull('posted_at'),
                    ),
                Tables\Filters\TernaryFilter::make('is_duplicate')
                    ->label(__('Duplicates'))
                    ->trueLabel(__('Duplicates only'))
                    ->falseLabel(__('Non-duplicates only'))
                    ->placeholder(__('All')),
                Tables\Filters\Filter::make('date_range')
                    ->schema([
                        Forms\Components\DatePicker::make('date_from')->label(__('From')),
                        Forms\Components\DatePicker::make('date_to')->label(__('To')),
                    ])
                    ->query(fn ($query, $data) => $query
                        ->when($data['date_from'], fn ($q, $v) => $q->whereDate('transaction_date', '>=', $v))
                        ->when($data['date_to'], fn ($q, $v) => $q->whereDate('transaction_date', '<=', $v)))
                    ->columns(2),
                Tables\Filters\SelectFilter::make('member_id')
                    ->label(__('Member'))
                    ->searchable()
                    ->options($memberOptions),
                Tables\Filters\Filter::make('amount')
                    ->schema([
                        Forms\Components\TextInput::make('amount_min')->label(__('Min amount (SAR)'))->numeric(),
                        Forms\Components\TextInput::make('amount_max')->label(__('Max amount (SAR)'))->numeric(),
                    ])
                    ->columns(2)
                    ->query(function ($query, array $data) {
                        return $query
                            ->when(filled($data['amount_min'] ?? null), fn ($q) => $q->where('amount', '>=', $data['amount_min']))
                            ->when(filled($data['amount_max'] ?? null), fn ($q) => $q->where('amount', '<=', $data['amount_max']));
                    }),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    // Post a single transaction; member pre-filled if auto-matched
                    Action::make('post_to_cash')
                        ->label(__('Post to Cash'))
                        ->icon('heroicon-o-arrow-right-circle')
                        ->color('primary')
                        ->visible(fn (SmsTransaction $r) => ! $r->isPosted())
                        ->fillForm(fn (SmsTransaction $r) => ['member_id' => $r->member_id])
                        ->schema([
                            Forms\Components\Select::make('member_id')
                                ->label(__('Post for Member'))
                                ->options($memberOptions)
                                ->searchable()
                                ->required()
                                ->helperText(__('Auto-matched from SMS template, or select manually.')),
                        ])
                        ->action(function (SmsTransaction $record, array $data) {
                            $member = Member::findOrFail($data['member_id']);
                            app(AccountingService::class)->postSmsTransactionToCash($record, $member);

                            Notification::make()
                                ->title(__('Posted to Cash Account'))
                                ->body(__('SMS transaction posted for :name.', ['name' => $member->user->name]))
                                ->success()
                                ->send();
                        }),
                    DeleteAction::make()
                        ->modalDescription(__('Soft-deletes this SMS import row. If it was posted to cash, the matching master and member cash ledger lines are reversed first.'))
                        ->using(function (SmsTransaction $record) {
                            app(AccountingService::class)->safeDeleteSmsTransaction($record);

                            return true;
                        }),
                    RestoreAction::make(),
                    ForceDeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->modalDescription('Deletes selected rows; posted transactions are reversed from the ledger first.')
                        ->using(function (DeleteBulkAction $action, $records) {
                            $accounting = app(AccountingService::class);
                            foreach ($records as $record) {
                                try {
                                    $accounting->safeDeleteSmsTransaction($record);
                                } catch (\Throwable $e) {
                                    $action->reportBulkProcessingFailure(message: $e->getMessage());
                                    report($e);
                                }
                            }
                        }),
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    // Auto-post all selected that have a matched member
                    BulkAction::make('bulk_auto_post')
                        ->label(__('Auto-post Matched Transactions'))
                        ->icon('heroicon-o-bolt')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalDescription(__('This will post all selected transactions that already have an auto-matched member. Unmatched transactions will be skipped.'))
                        ->action(function (Collection $records) {
                            $service = app(AccountingService::class);
                            $posted = 0;
                            $skipped = 0;

                            foreach ($records as $tx) {
                                if ($tx->isPosted() || ! $tx->member_id) {
                                    $skipped++;

                                    continue;
                                }
                                $service->postSmsTransactionToCash($tx, $tx->member);
                                $posted++;
                            }

                            Notification::make()
                                ->title(__('Auto-post Complete'))
                                ->body(__('Posted: :posted | Skipped (no member or already posted): :skipped', ['posted' => $posted, 'skipped' => $skipped]))
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    // Manual bulk post — all selected go to one selected member
                    BulkAction::make('bulk_post_to_cash')
                        ->label(__('Bulk Post to a Single Member'))
                        ->icon('heroicon-o-arrow-right-circle')
                        ->color('primary')
                        ->schema([
                            Forms\Components\Select::make('member_id')
                                ->label(__('Post all selected for Member'))
                                ->options($memberOptions)
                                ->searchable()
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data) {
                            $member = Member::findOrFail($data['member_id']);
                            $service = app(AccountingService::class);
                            $posted = 0;
                            $skipped = 0;

                            foreach ($records as $tx) {
                                if ($tx->isPosted()) {
                                    $skipped++;

                                    continue;
                                }
                                $service->postSmsTransactionToCash($tx, $member);
                                $posted++;
                            }

                            Notification::make()
                                ->title(__('Bulk Post Complete'))
                                ->body(__('Posted: :posted | Already posted (skipped): :skipped', ['posted' => $posted, 'skipped' => $skipped]))
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
            'index' => Pages\ListSmsTransactions::route('/'),
            'view' => Pages\ViewSmsTransaction::route('/{record}'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->withTrashed();
    }
}
