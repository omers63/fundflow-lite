<?php

namespace App\Filament\Admin\Resources\AccountResource\RelationManagers;

use App\Models\Account;
use App\Models\AccountTransaction;
use App\Models\BankTransaction;
use App\Models\Member;
use App\Services\AccountingService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

class TransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'transactions';

    protected static ?string $title = null;

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('Ledger Entries');
    }

    /**
     * Allow edit/create actions on account View pages when the panel would otherwise
     * mark relation managers read-only.
     */
    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        $account = $this->getOwnerRecord();

        return $this->defaultForm($schema)->schema([
            Forms\Components\TextInput::make('amount')
                ->label(__('Amount (SAR)'))
                ->numeric()
                ->required()
                ->minValue(0.01)
                ->step(0.01),
            Forms\Components\Select::make('entry_type')
                ->label(__('Type'))
                ->options(['credit' => __('Credit'), 'debit' => __('Debit')])
                ->required(),
            Forms\Components\Textarea::make('description')
                ->label(__('Description'))
                ->required()
                ->rows(3)
                ->maxLength(1000),
            Forms\Components\DateTimePicker::make('transacted_at')
                ->label(__('Transaction date'))
                ->required()
                ->seconds(false),
            Forms\Components\Select::make('member_id')
                ->label(__('Member tag'))
                ->helperText(__('For master accounts only — ties this line to a member in filters and reports.'))
                ->options(fn () => Member::query()->with('user')->orderBy('member_number')->get()
                    ->mapWithKeys(fn (Member $m) => [$m->id => "{$m->member_number} – {$m->user->name}"]))
                ->searchable()
                ->placeholder(__('—'))
                ->visible(fn () => $account->member_id === null),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('description')
            ->defaultSort('transacted_at', 'desc')
            ->striped()
            ->headerActions([
                Action::make('createLedgerCredit')
                    ->label(__('Credit'))
                    ->icon('heroicon-o-arrow-trending-up')
                    ->color('success')
                    ->modalHeading(__('Post credit entry'))
                    ->modalDescription(__('Adds a credit to this account only. If you need matching master and member lines, post each account separately or use the standard finance workflows.'))
                    ->modalSubmitActionLabel(__('Post credit'))
                    ->authorize(fn () => auth()->user()?->can('update', $this->getOwnerRecord()) ?? false)
                    ->schema($this->manualLedgerEntryFormSchema())
                    ->action(fn (array $data) => $this->postManualLedgerLineFromAction($data, 'credit')),
                Action::make('createLedgerDebit')
                    ->label(__('Debit'))
                    ->icon('heroicon-o-arrow-trending-down')
                    ->color('danger')
                    ->modalHeading(__('Post debit entry'))
                    ->modalDescription(__('Adds a debit to this account only. If you need matching master and member lines, post each account separately or use the standard finance workflows.'))
                    ->modalSubmitActionLabel(__('Post debit'))
                    ->authorize(fn () => auth()->user()?->can('update', $this->getOwnerRecord()) ?? false)
                    ->schema($this->manualLedgerEntryFormSchema())
                    ->action(fn (array $data) => $this->postManualLedgerLineFromAction($data, 'debit')),
                // Refund — only visible on member cash accounts
                Action::make('refundMemberCash')
                    ->label(__('Refund'))
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->modalHeading(__('Post Refund'))
                    ->modalDescription(__('Debits both this member cash account and master cash — recording money returned to the member. The matching debit will appear on the next imported bank statement.'))
                    ->modalSubmitActionLabel(__('Post Refund'))
                    ->visible(fn (): bool => $this->getOwnerRecord()->type === Account::TYPE_MEMBER_CASH)
                    ->authorize(fn () => auth()->user()?->can('update', $this->getOwnerRecord()) ?? false)
                    ->schema(function (): array {
                        $account = $this->getOwnerRecord();
                        $balance = (float) $account->balance;

                        return [
                            Forms\Components\Placeholder::make('balance_info')
                                ->label(__('Available balance'))
                                ->content(new HtmlString(
                                    '<span class="text-lg font-bold ' . ($balance > 0 ? 'text-emerald-600' : 'text-red-600') . '">'
                                    . __('SAR') . ' ' . number_format($balance, 2)
                                    . '</span>'
                                )),
                            Forms\Components\TextInput::make('amount')
                                ->label(__('Amount (SAR)'))
                                ->numeric()
                                ->required()
                                ->minValue(0.01)
                                ->maxValue($balance > 0 ? $balance : null)
                                ->default($balance > 0 ? $balance : null)
                                ->step(0.01),
                            Forms\Components\Textarea::make('description')
                                ->label(__('Reason / Description'))
                                ->required()
                                ->rows(2)
                                ->maxLength(500),
                            Forms\Components\DateTimePicker::make('transacted_at')
                                ->label(__('Transaction date'))
                                ->default(now())
                                ->required()
                                ->seconds(false),
                        ];
                    })
                    ->action(function (array $data): void {
                        $account = $this->getOwnerRecord();
                        try {
                            app(AccountingService::class)->refundMemberCash(
                                $account,
                                (float) $data['amount'],
                                (string) $data['description'],
                                $account->member,
                                $data['transacted_at'] ? \Illuminate\Support\Carbon::parse($data['transacted_at']) : null,
                            );
                        } catch (\Throwable $e) {
                            report($e);
                            Notification::make()
                                ->title(__('Refund failed'))
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                            throw new Halt;
                        }

                        Notification::make()
                            ->title(__('Refund of SAR :amount posted for :name', [
                                'amount' => number_format((float) $data['amount'], 2),
                                'name'   => $account->member?->user?->name ?? __('Member'),
                            ]))
                            ->success()
                            ->send();

                        $this->resetTable();
                        $this->dispatchAccountWidgetsRefresh();
                    }),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('transacted_at')
                    ->label(__('Date'))
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\BadgeColumn::make('entry_type')
                    ->label(__('Type'))
                    ->colors(['success' => 'credit', 'danger' => 'debit'])
                    ->toggleable(),
                Tables\Columns\TextColumn::make('amount')
                    ->money('SAR')
                    ->sortable()
                    ->color(fn (AccountTransaction $r) => $r->entry_type === 'credit' ? 'success' : 'danger')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('description')
                    ->limit(60)
                    ->placeholder(__('—'))
                    ->toggleable(),
                Tables\Columns\TextColumn::make('member.user.name')
                    ->label(__('Member'))
                    ->placeholder(__('—'))
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('source_type')
                    ->label(__('Source'))
                    ->formatStateUsing(fn ($state) => $state ? class_basename($state) : __('—'))
                    ->toggleable(),
                Tables\Columns\TextColumn::make('postedBy.name')
                    ->label(__('Posted By'))
                    ->placeholder(__('—'))
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('member_id')
                    ->label(__('Member'))
                    ->searchable()
                    ->options(fn () => Member::with('user')->orderBy('member_number')->get()
                        ->mapWithKeys(fn (Member $m) => [$m->id => "{$m->member_number} – {$m->user->name}"])),
                Tables\Filters\SelectFilter::make('entry_type')
                    ->label(__('Type'))
                    ->options(['credit' => __('Credit'), 'debit' => __('Debit')]),
                Tables\Filters\Filter::make('transacted_at')
                    ->schema([
                        Forms\Components\DateTimePicker::make('from')->label(__('From')),
                        Forms\Components\DateTimePicker::make('until')->label(__('Until')),
                    ])
                    ->columns(2)
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'] ?? null, fn ($q) => $q->where('transacted_at', '>=', $data['from']))
                            ->when($data['until'] ?? null, fn ($q) => $q->where('transacted_at', '<=', $data['until']));
                    }),
                Tables\Filters\SelectFilter::make('source_type')
                    ->label(__('Source type'))
                    ->options([
                        'App\Models\BankTransaction' => __('Bank import'),
                        'App\Models\SmsTransaction' => __('SMS import'),
                        'App\Models\Contribution' => __('Contribution'),
                        'App\Models\Loan' => __('Loan'),
                        'App\Models\LoanInstallment' => __('Loan installment'),
                        'App\Models\Member' => __('Member'),
                        'App\Models\User' => __('User (manual / system)'),
                    ]),
                Tables\Filters\Filter::make('amount')
                    ->schema([
                        Forms\Components\TextInput::make('amount_min')->label(__('Min (SAR)'))->numeric(),
                        Forms\Components\TextInput::make('amount_max')->label(__('Max (SAR)'))->numeric(),
                    ])
                    ->columns(2)
                    ->query(function ($query, array $data) {
                        return $query
                            ->when(filled($data['amount_min'] ?? null), fn ($q) => $q->where('amount', '>=', $data['amount_min']))
                            ->when(filled($data['amount_max'] ?? null), fn ($q) => $q->where('amount', '<=', $data['amount_max']));
                    }),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    // Split Transaction — master cash only
                    Action::make('splitTransaction')
                        ->label(__('Split Transaction'))
                        ->icon('heroicon-o-scissors')
                        ->color('info')
                        ->modalHeading(__('Split Transaction'))
                        ->modalDescription(fn (AccountTransaction $record): string => __(
                            'Divide SAR :amount into labelled parts. Parts must sum to the original amount.',
                            ['amount' => number_format((float) $record->amount, 2)]
                        ))
                        ->modalSubmitActionLabel(__('Split into parts'))
                        ->modalWidth('3xl')
                        ->visible(fn (AccountTransaction $record): bool =>
                            $this->getOwnerRecord()->type === Account::TYPE_MASTER_CASH
                            && $record->entry_type === 'credit'
                            && $record->trashed() === false
                        )
                        ->authorize(fn (): bool => auth()->user()?->can('update', $this->getOwnerRecord()) ?? false)
                        ->schema(fn (AccountTransaction $record): array => [
                            Forms\Components\Placeholder::make('original_info')
                                ->label(__('Original entry'))
                                ->content(new HtmlString(
                                    '<span class="text-base font-semibold text-emerald-600">'
                                    . __('SAR') . ' ' . number_format((float) $record->amount, 2)
                                    . '</span>'
                                    . ' — ' . e($record->description ?? '—')
                                )),
                            Forms\Components\Repeater::make('parts')
                                ->label(__('Split into parts'))
                                ->minItems(2)
                                ->addActionLabel(__('Add part'))
                                ->defaultItems(2)
                                ->columns(3)
                                ->schema([
                                    Forms\Components\Select::make('category')
                                        ->label(__('Category'))
                                        ->options([
                                            'contribution'        => __('Contribution'),
                                            'late_fee'            => __('Late Fee'),
                                            'membership_fee'      => __('Membership Fee'),
                                            'annual_subscription' => __('Annual Subscription'),
                                            'other'               => __('Other'),
                                        ])
                                        ->required()
                                        ->reactive()
                                        ->afterStateUpdated(function ($state, callable $set): void {
                                            $labels = [
                                                'contribution'        => __('Contribution'),
                                                'late_fee'            => __('Late Fee'),
                                                'membership_fee'      => __('Membership Fee'),
                                                'annual_subscription' => __('Annual Subscription Fee'),
                                            ];
                                            if (isset($labels[$state])) {
                                                $set('description', $labels[$state]);
                                            }
                                        }),
                                    Forms\Components\TextInput::make('description')
                                        ->label(__('Description'))
                                        ->required()
                                        ->maxLength(500),
                                    Forms\Components\TextInput::make('amount')
                                        ->label(__('Amount (SAR)'))
                                        ->numeric()
                                        ->required()
                                        ->minValue(0.01)
                                        ->step(0.01),
                                ]),
                            Forms\Components\Placeholder::make('running_total')
                                ->label(__('Running total'))
                                ->content(new HtmlString(
                                    '<span class="text-sm text-gray-500">'
                                    . __('Parts must sum to SAR :amount', ['amount' => number_format((float) $record->amount, 2)])
                                    . '</span>'
                                )),
                        ])
                        ->action(function (AccountTransaction $record, array $data): void {
                            $parts = collect($data['parts'] ?? [])
                                ->map(fn ($p) => [
                                    'amount'      => (float) ($p['amount'] ?? 0),
                                    'description' => trim($p['description'] ?? ''),
                                ])
                                ->all();

                            try {
                                app(AccountingService::class)->splitTransaction($record, $parts);
                            } catch (\Throwable $e) {
                                report($e);
                                Notification::make()
                                    ->title(__('Split failed'))
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                                throw new Halt;
                            }

                            Notification::make()
                                ->title(__('Transaction split into :count parts', ['count' => count($parts)]))
                                ->success()
                                ->send();

                            $this->resetTable();
                            $this->dispatchAccountWidgetsRefresh();
                        }),
                    Action::make('post_to_member')
                        ->label(__('Post to Member'))
                        ->icon('heroicon-o-user-plus')
                        ->color('primary')
                        ->modalHeading(__('Post credit to member Cash Account'))
                        ->modalDescription(
                            __('Creates the matching ledger line on the selected member’s Cash Account and links this bank import row. Only credits posted to master cash without a member can use this.')
                        )
                        ->modalSubmitActionLabel(__('Post to member'))
                        ->visible(fn (AccountTransaction $record): bool => $this->ledgerEntryCanPostToMember($record))
                        ->authorize(fn (): bool => auth()->user()?->can('update', $this->getOwnerRecord()) ?? false)
                        ->schema([
                            Forms\Components\Select::make('member_id')
                                ->label(__('Member'))
                                ->options(fn () => Member::query()->with('user')->orderBy('member_number')->get()
                                    ->mapWithKeys(fn (Member $m) => [$m->id => "{$m->member_number} – {$m->user->name}"]))
                                ->searchable()
                                ->required(),
                        ])
                        ->action(function (AccountTransaction $record, array $data): void {
                            $record->loadMissing('source');
                            $source = $record->source;
                            if (! $source instanceof BankTransaction) {
                                return;
                            }

                            try {
                                app(AccountingService::class)->mirrorBankCreditToMemberCash(
                                    $source,
                                    Member::findOrFail($data['member_id']),
                                    $record,
                                );
                            } catch (\Throwable $e) {
                                report($e);
                                Notification::make()
                                    ->title(__('Could not post to member'))
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();

                                throw new Halt;
                            }

                            Notification::make()
                                ->title(__('Posted to member Cash Account'))
                                ->success()
                                ->send();

                            $this->resetTable();
                            $this->dispatchAccountWidgetsRefresh();
                        }),
                    EditAction::make()
                        ->modalWidth('2xl')
                        ->modalDescription(
                            __('Changes to amount or type adjust this account’s running balance. The linked source record is not changed here — use the relevant finance workflow if that must stay aligned.')
                        )
                        ->authorize(fn (): bool => auth()->user()?->can('update', $this->getOwnerRecord()) ?? false)
                        ->using(function (array $data, Model $record): void {
                            if (! $record instanceof AccountTransaction) {
                                return;
                            }

                            try {
                                app(AccountingService::class)->updateLedgerEntry($record, $data);
                            } catch (\Throwable $e) {
                                report($e);
                                Notification::make()
                                    ->title(__('Could not save ledger entry'))
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();

                                throw new Halt;
                            }
                        })
                        ->after(fn () => $this->dispatchAccountWidgetsRefresh()),
                    DeleteAction::make()
                        ->authorize(fn () => auth()->user()?->can('update', $this->getOwnerRecord()) ?? false)
                        ->modalDescription(__('Reverses this line on the account balance and removes the row. If this was one leg of a paired posting, delete or adjust the other leg separately if needed.'))
                        ->using(function (AccountTransaction $record) {
                            app(AccountingService::class)->safeDeleteAccountTransaction($record);

                            return true;
                        })
                        ->after(fn () => $this->dispatchAccountWidgetsRefresh()),
                    ForceDeleteAction::make()
                        ->authorize(fn () => auth()->user()?->can('update', $this->getOwnerRecord()) ?? false)
                        ->modalDescription(__('Permanently removes this ledger row from the database. Only use after a normal delete (balance already reversed).'))
                        ->after(fn () => $this->dispatchAccountWidgetsRefresh()),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('bulk_post_to_member')
                        ->label(__('Post to Member'))
                        ->icon('heroicon-o-user-plus')
                        ->color('primary')
                        ->modalHeading(__('Post selected credits to one member'))
                        ->modalDescription(
                            __('Each selected row must be a master-cash ledger line for a bank credit not yet mirrored to a member. Other rows are skipped.')
                        )
                        ->modalSubmitActionLabel(__('Post to member'))
                        ->visible(fn (): bool => $this->getOwnerRecord()->type === Account::TYPE_MASTER_CASH)
                        ->authorize(fn (): bool => auth()->user()?->can('update', $this->getOwnerRecord()) ?? false)
                        ->schema([
                            Forms\Components\Select::make('member_id')
                                ->label(__('Member'))
                                ->options(fn () => Member::query()->with('user')->orderBy('member_number')->get()
                                    ->mapWithKeys(fn (Member $m) => [$m->id => "{$m->member_number} – {$m->user->name}"]))
                                ->searchable()
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $member = Member::findOrFail($data['member_id']);
                            $accounting = app(AccountingService::class);
                            $posted = 0;
                            $skipped = 0;
                            $failed = 0;

                            foreach ($records as $record) {
                                if (! $record instanceof AccountTransaction) {
                                    continue;
                                }
                                $record->loadMissing('source');
                                $source = $record->source;
                                if (! $source instanceof BankTransaction || ! $accounting->canMirrorBankCreditToMemberCash($source)) {
                                    $skipped++;

                                    continue;
                                }
                                try {
                                    $accounting->mirrorBankCreditToMemberCash($source, $member, $record);
                                    $posted++;
                                } catch (\Throwable $e) {
                                    $failed++;
                                    report($e);
                                }
                            }

                            $body = __('Posted: :posted | Skipped: :skipped', ['posted' => $posted, 'skipped' => $skipped]);
                            if ($failed > 0) {
                                $body .= ' '.__('| Failed: :failed (see logs)', ['failed' => $failed]);
                            }

                            $notification = Notification::make()
                                ->title(__('Post to member complete'))
                                ->body($body);

                            if ($failed > 0) {
                                $notification->warning();
                            } else {
                                $notification->success();
                            }

                            $notification->send();

                            $this->resetTable();
                            $this->dispatchAccountWidgetsRefresh();
                        })
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make()
                        ->authorize(fn () => auth()->user()?->can('update', $this->getOwnerRecord()) ?? false)
                        ->modalDescription(__('Reverses each selected line on its account balance, then deletes it.'))
                        ->using(function (DeleteBulkAction $action, $records) {
                            $accounting = app(AccountingService::class);
                            foreach ($records as $record) {
                                try {
                                    $accounting->safeDeleteAccountTransaction($record);
                                } catch (\Throwable $e) {
                                    $action->reportBulkProcessingFailure(message: $e->getMessage());
                                    report($e);
                                }
                            }
                        })
                        ->after(fn () => $this->dispatchAccountWidgetsRefresh()),
                    ForceDeleteBulkAction::make()
                        ->authorize(fn () => auth()->user()?->can('update', $this->getOwnerRecord()) ?? false)
                        ->after(fn () => $this->dispatchAccountWidgetsRefresh()),
                ]),
            ])
            ->paginated([5, 10, 25, 50, 100]);
    }

    protected function manualLedgerEntryFormSchema(): array
    {
        $account = $this->getOwnerRecord();

        return [
            Forms\Components\TextInput::make('amount')
                ->label(__('Amount (SAR)'))
                ->numeric()
                ->required()
                ->minValue(0.01)
                ->step(0.01),
            Forms\Components\Textarea::make('description')
                ->label(__('Description'))
                ->required()
                ->rows(2)
                ->maxLength(1000),
            Forms\Components\DateTimePicker::make('transacted_at')
                ->label(__('Transaction date'))
                ->default(now())
                ->required()
                ->seconds(false),
            Forms\Components\Select::make('member_id')
                ->label(__('Member tag'))
                ->helperText(__('Optional for master accounts — used when filtering ledger lines by member. Member-owned accounts use their member automatically.'))
                ->options(fn () => Member::query()->with('user')->orderBy('member_number')->get()
                    ->mapWithKeys(fn (Member $m) => [$m->id => "{$m->member_number} – {$m->user->name}"]))
                ->searchable()
                ->placeholder(__('—'))
                ->visible(fn () => $account->member_id === null),
        ];
    }

    protected function postManualLedgerLineFromAction(array $data, string $entryType): void
    {
        $account = $this->getOwnerRecord();
        $memberId = $account->member_id;
        if ($memberId === null && filled($data['member_id'] ?? null)) {
            $memberId = (int) $data['member_id'];
        }

        try {
            app(AccountingService::class)->postManualLedgerEntry(
                $account,
                $entryType,
                (float) $data['amount'],
                (string) $data['description'],
                $memberId,
                $data['transacted_at'] ?? null,
            );
        } catch (\Throwable $e) {
            report($e);
            Notification::make()
                ->title(__('Could not post entry'))
                ->body($e->getMessage())
                ->danger()
                ->send();

            throw new Halt;
        }

        Notification::make()
            ->title(__('Entry posted'))
            ->success()
            ->send();

        $this->dispatchAccountWidgetsRefresh();
    }

    protected function dispatchAccountWidgetsRefresh(): void
    {
        $this->dispatch(
            'refresh-account-widgets',
            accountId: (int) $this->getOwnerRecord()->getKey(),
        );
    }

    protected function ledgerEntryCanPostToMember(AccountTransaction $record): bool
    {
        if ($this->getOwnerRecord()->type !== Account::TYPE_MASTER_CASH) {
            return false;
        }

        $record->loadMissing('source');
        $source = $record->source;

        return $source instanceof BankTransaction
            && app(AccountingService::class)->canMirrorBankCreditToMemberCash($source);
    }
}
