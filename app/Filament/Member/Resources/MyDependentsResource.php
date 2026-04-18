<?php

namespace App\Filament\Member\Resources;

use App\Filament\Member\Resources\MyDependentsResource\Pages;
use App\Models\DependentAllocationChange;
use App\Models\Member;
use App\Services\AccountingService;
use App\Services\AllocationService;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\HtmlString;

class MyDependentsResource extends Resource
{
    protected static ?string $model = Member::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'My Dependents';

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): ?string
    {
        return __('app.nav.group.account');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return true;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public static function table(Table $table): Table
    {
        $parentMember = fn () => Member::where('user_id', auth()->id())->first();

        return $table
            ->heading('Your dependents')
            ->description('Members sponsored under your account. Review allocations and balances, update allocation amounts instantly, fund dependent cash, and open history per dependent.')
            ->query(function () use ($parentMember) {
                $member = $parentMember();

                return Member::where('parent_id', $member?->id ?? 0)
                    ->with([
                        'user',
                        'accounts',
                        'allocationChangesReceived' => fn (Relation $query) => $query->latest()->limit(1),
                    ]);
            })
            ->columns([
                Tables\Columns\TextColumn::make('member_number')
                    ->label('Member #')
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('monthly_contribution_amount')
                    ->label('Monthly Allocation')
                    ->money('SAR')
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('last_allocation_change')
                    ->label('Last Changed')
                    ->getStateUsing(function (Member $record): ?string {
                        $last = DependentAllocationChange::where('dependent_member_id', $record->id)
                            ->latest()
                            ->first();
                        if (! $last) {
                            return null;
                        }
                        $dir = $last->isIncrease() ? '↑' : '↓';

                        return "{$dir} {$last->deltaLabel()} · {$last->created_at->diffForHumans()}";
                    })
                    ->placeholder('Never changed')
                    ->color(fn (Member $record): ?string => null),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'active' => 'success',
                        'suspended' => 'warning',
                        'delinquent', 'terminated' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('cash_balance')
                    ->label('Cash Balance')
                    ->money('SAR')
                    ->getStateUsing(fn (Member $r) => $r->cash_balance)
                    ->color(fn (Member $r) => $r->cash_balance >= 0 ? 'success' : 'danger'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'suspended' => 'Suspended',
                        'delinquent' => 'Delinquent',
                        'terminated' => 'Terminated',
                    ]),
                Tables\Filters\SelectFilter::make('monthly_contribution_amount')
                    ->label('Monthly allocation')
                    ->options(Member::contributionAmountOptions()),
            ])
            ->headerActions([
                // ── Bulk update all dependents at once ───────────────────────
                Action::make('bulk_update_allocations')
                    ->label('Update allocations (all)')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->color('primary')
                    ->modalHeading('Update dependent allocations')
                    ->modalDescription(new HtmlString(
                        '<p class="text-sm text-gray-600 dark:text-gray-400">Changed amounts are applied immediately and administrators are notified automatically.</p>'
                    ))
                    ->modalWidth('xl')
                    ->schema(function () use ($parentMember): array {
                        $parent = $parentMember();
                        $dependents = $parent ? $parent->dependents()->with('user')->orderBy('member_number')->get() : collect();

                        if ($dependents->isEmpty()) {
                            return [
                                Forms\Components\Placeholder::make('none')
                                    ->label('')
                                    ->content('You have no dependents.'),
                            ];
                        }

                        $fields = [];

                        foreach ($dependents as $dep) {
                            $fields[] = Forms\Components\Select::make("amounts.{$dep->id}")
                                ->label("{$dep->member_number} — {$dep->user->name}")
                                ->options(Member::contributionAmountOptions())
                                ->default($dep->monthly_contribution_amount)
                                ->required()
                                ->helperText(fn () => 'Current: SAR '.number_format($dep->monthly_contribution_amount).' · Cash: SAR '.number_format($dep->cash_balance, 2));
                        }

                        $fields[] = Forms\Components\TextInput::make('note')
                            ->label('Note / Reason (optional)')
                            ->maxLength(200)
                            ->placeholder('e.g. Annual review adjustment')
                            ->columnSpanFull();

                        return $fields;
                    })
                    ->action(function (array $data) use ($parentMember) {
                        $parent = $parentMember();
                        if (! $parent) {
                            Notification::make()->title('Member record not found.')->danger()->send();

                            return;
                        }

                        $amounts = $data['amounts'] ?? [];
                        $note = $data['note'] ?? null;

                        if ($amounts === []) {
                            Notification::make()->title('No dependents to update.')->warning()->send();

                            return;
                        }

                        $results = app(AllocationService::class)->changeMultiple(
                            parent: $parent,
                            updates: $amounts,
                            note: is_string($note) ? $note : null,
                            changedBy: auth()->user(),
                        );

                        $body = app(AllocationService::class)->buildSummary($results);
                        $updated = collect($results)->filter(fn (array $row): bool => $row['change'] !== null)->count();

                        Notification::make()
                            ->title($updated > 0 ? 'Allocations updated' : 'No changes applied')
                            ->body($body)
                            ->color($updated > 0 ? 'success' : 'info')
                            ->send();
                    }),
            ])
            ->recordActions([
                // ── Set single allocation ─────────────────────────────────────
                Action::make('set_allocation')
                    ->label('Update allocation')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->color('warning')
                    ->fillForm(fn (Member $record) => [
                        'monthly_contribution_amount' => $record->monthly_contribution_amount,
                        'note' => null,
                    ])
                    ->schema([
                        Forms\Components\Select::make('monthly_contribution_amount')
                            ->label('Monthly Contribution Amount')
                            ->options(Member::contributionAmountOptions())
                            ->required()
                            ->helperText(fn (Forms\Get $get, ?Member $record) => $record
                                ? 'Current: SAR '.number_format($record->monthly_contribution_amount).' · Cash balance: SAR '.number_format($record->cash_balance, 2)
                                : ''),
                        Forms\Components\TextInput::make('note')
                            ->label('Note / Reason (optional)')
                            ->maxLength(200)
                            ->placeholder('e.g. Income change'),
                    ])
                    ->action(function (Member $record, array $data) use ($parentMember) {
                        $parent = $parentMember();
                        if (! $parent) {
                            Notification::make()->title('Parent member not found.')->danger()->send();

                            return;
                        }

                        $newAmount = (int) $data['monthly_contribution_amount'];
                        if (! Member::isValidContributionAmount($newAmount)) {
                            Notification::make()->title('Invalid amount selected.')->danger()->send();
                            return;
                        }

                        try {
                            $change = app(AllocationService::class)->changeAllocation(
                                parent: $parent,
                                dependent: $record,
                                newAmount: $newAmount,
                                note: is_string($data['note'] ?? null) ? $data['note'] : null,
                                changedBy: auth()->user(),
                            );
                        } catch (\Throwable $e) {
                            Notification::make()->title('Could not update allocation')->body($e->getMessage())->danger()->send();
                            return;
                        }

                        if ($change === null) {
                            Notification::make()->title('No changes detected.')->info()->send();
                            return;
                        }

                        Notification::make()
                            ->title('Allocation updated')
                            ->body($record->user?->name.' allocation was updated successfully.')
                            ->success()
                            ->send();
                    }),

                // ── Fund cash account ─────────────────────────────────────────
                Action::make('fund_cash')
                    ->label('Fund Cash Account')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->schema(function (Member $record) use ($parentMember) {
                        $parent = $parentMember();

                        return [
                            Forms\Components\Placeholder::make('balances')
                                ->label('Balances')
                                ->content(
                                    'Your cash: SAR '.number_format($parent?->cash_balance ?? 0, 2).
                                    " | {$record->user->name}'s cash: SAR ".number_format($record->cash_balance, 2)
                                ),
                            Forms\Components\TextInput::make('amount')
                                ->label('Amount to Transfer (SAR)')
                                ->numeric()
                                ->minValue(1)
                                ->required()
                                ->prefix('SAR'),
                            Forms\Components\TextInput::make('note')
                                ->label('Note (optional)')
                                ->maxLength(200),
                        ];
                    })
                    ->action(function (Member $record, array $data) use ($parentMember) {
                        $parent = $parentMember();
                        if (! $parent) {
                            Notification::make()->title('Your member record was not found.')->danger()->send();

                            return;
                        }
                        try {
                            app(AccountingService::class)->fundDependentCashAccount(
                                parent: $parent,
                                dependent: $record,
                                amount: (float) $data['amount'],
                                note: $data['note'] ?? '',
                            );
                            Notification::make()
                                ->title('Transfer Successful')
                                ->body('SAR '.number_format($data['amount'], 2)." transferred to {$record->user->name}'s cash account.")
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('Transfer Failed')->body($e->getMessage())->danger()->send();
                        }
                    }),

                // ── View allocation history ───────────────────────────────────
                Action::make('view_history')
                    ->label('History')
                    ->icon('heroicon-o-clock')
                    ->color('gray')
                    ->modalHeading(fn (Member $record) => "Allocation History — {$record->user->name}")
                    ->modalContent(function (Member $record): HtmlString {
                        $changes = DependentAllocationChange::where('dependent_member_id', $record->id)
                            ->with('changedBy')
                            ->latest()
                            ->limit(30)
                            ->get();

                        if ($changes->isEmpty()) {
                            return new HtmlString('<p class="text-sm text-gray-500 p-4">No allocation changes recorded.</p>');
                        }

                        $rows = '';
                        foreach ($changes as $c) {
                            $dir = $c->isIncrease()
                                ? '<span class="text-emerald-600 font-bold">↑</span>'
                                : '<span class="text-amber-600 font-bold">↓</span>';
                            $delta = $c->isIncrease()
                                ? '<span class="text-emerald-600">+SAR '.number_format(abs($c->delta())).'</span>'
                                : '<span class="text-amber-600">−SAR '.number_format(abs($c->delta())).'</span>';
                            $by = e($c->changedBy?->name ?? 'System');
                            $note = $c->note ? '<br><span class="text-gray-400 text-xs">'.e($c->note).'</span>' : '';
                            $date = $c->created_at->format('d M Y H:i');

                            $rows .= "
                                <tr class=\"border-b border-gray-100 dark:border-gray-700\">
                                    <td class=\"py-2 px-3 text-xs text-gray-500\">{$date}</td>
                                    <td class=\"py-2 px-3 text-sm\">{$dir} SAR {$c->old_amount} → SAR {$c->new_amount}</td>
                                    <td class=\"py-2 px-3 text-sm\">{$delta}</td>
                                    <td class=\"py-2 px-3 text-sm\">{$by}{$note}</td>
                                </tr>";
                        }

                        return new HtmlString("
                            <div class=\"overflow-x-auto\">
                                <table class=\"w-full text-sm\">
                                    <thead>
                                        <tr class=\"bg-gray-50 dark:bg-gray-800 text-xs uppercase text-gray-500\">
                                            <th class=\"py-2 px-3 text-left\">Date</th>
                                            <th class=\"py-2 px-3 text-left\">Change</th>
                                            <th class=\"py-2 px-3 text-left\">Delta</th>
                                            <th class=\"py-2 px-3 text-left\">Changed By</th>
                                        </tr>
                                    </thead>
                                    <tbody>{$rows}</tbody>
                                </table>
                            </div>");
                    })
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
            ])
            ->emptyStateHeading('No dependents')
            ->emptyStateDescription('You have no dependent members assigned to you.');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMyDependents::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
