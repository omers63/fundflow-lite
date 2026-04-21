<?php

namespace App\Filament\Member\Resources;

use App\Filament\Member\Resources\MyDependentsResource\Pages;
use App\Models\DependentAllocationChange;
use App\Models\Member;
use App\Services\AccountingService;
use App\Services\AllocationService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
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

    public static function getNavigationLabel(): string
    {
        return __('My Dependents');
    }

    public static function getModelLabel(): string
    {
        return __('app.resource.member');
    }

    public static function getPluralModelLabel(): string
    {
        return __('My Dependents');
    }

    public static function getNavigationGroup(): ?string
    {
        return 'account';
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
            ->heading(__('Your dependents'))
            ->description(__('Members sponsored under your account. Review allocations and balances, update allocation amounts instantly, fund dependent cash, and open history per dependent.'))
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
                    ->label(__('Member #'))
                    ->visibleFrom('sm')
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label(__('Name'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('monthly_contribution_amount')
                    ->label(__('Monthly Allocation'))
                    ->money('SAR')
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('last_allocation_change')
                    ->label(__('Last Changed'))
                    ->visibleFrom('md')
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
                    ->placeholder(__('Never changed'))
                    ->color(fn (Member $record): ?string => null),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('app.field.status'))
                    ->badge()
                    ->visibleFrom('sm')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'active' => __('Active'),
                        'suspended' => __('Suspended'),
                        'delinquent' => __('Delinquent'),
                        'terminated' => __('Terminated'),
                        default => __(ucfirst(str_replace('_', ' ', $state))),
                    })
                    ->color(fn (string $state) => match ($state) {
                        'active' => 'success',
                        'suspended' => 'warning',
                        'delinquent', 'terminated' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('cash_balance')
                    ->label(__('Cash Balance'))
                    ->money('SAR')
                    ->visibleFrom('md')
                    ->getStateUsing(fn (Member $r) => $r->cash_balance)
                    ->color(fn (Member $r) => $r->cash_balance >= 0 ? 'success' : 'danger'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => __('Active'),
                        'suspended' => __('Suspended'),
                        'delinquent' => __('Delinquent'),
                        'terminated' => __('Terminated'),
                    ]),
                Tables\Filters\SelectFilter::make('monthly_contribution_amount')
                    ->label(__('Monthly allocation'))
                    ->options(Member::contributionAmountOptions()),
            ])
            ->headerActions([
                // ── Bulk update all dependents at once ───────────────────────
                Action::make('bulk_update_allocations')
                    ->label(__('Update allocations (all)'))
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->color('primary')
                    ->modalHeading(__('Update dependent allocations'))
                    ->modalDescription(new HtmlString(
                        '<p class="text-sm text-gray-600 dark:text-gray-400">'.e(__('Changed amounts are applied immediately and administrators are notified automatically.')).'</p>'
                    ))
                    ->modalWidth('xl')
                    ->schema(function () use ($parentMember): array {
                        $parent = $parentMember();
                        $dependents = $parent ? $parent->dependents()->with('user')->orderBy('member_number')->get() : collect();

                        if ($dependents->isEmpty()) {
                            return [
                                Forms\Components\Placeholder::make('none')
                                    ->label('')
                                    ->content(__('You have no dependents.')),
                            ];
                        }

                        $fields = [];

                        foreach ($dependents as $dep) {
                            $fields[] = Forms\Components\Select::make("amounts.{$dep->id}")
                                ->label("{$dep->member_number} — {$dep->user->name}")
                                ->options(Member::contributionAmountOptions())
                                ->default($dep->monthly_contribution_amount)
                                ->required()
                                ->helperText(fn () => __('Current allocation: :currency :alloc · Cash: :currency :cash', [
                                    'currency' => __('SAR'),
                                    'alloc' => number_format($dep->monthly_contribution_amount),
                                    'cash' => number_format($dep->cash_balance, 2),
                                ]));
                        }

                        $fields[] = Forms\Components\TextInput::make('note')
                            ->label(__('Note / Reason (optional)'))
                            ->maxLength(200)
                            ->placeholder(__('e.g. Annual review adjustment'))
                            ->columnSpanFull();

                        return $fields;
                    })
                    ->action(function (array $data) use ($parentMember) {
                        $parent = $parentMember();
                        if (! $parent) {
                            Notification::make()->title(__('Member record not found.'))->danger()->send();

                            return;
                        }

                        $amounts = $data['amounts'] ?? [];
                        $note = $data['note'] ?? null;

                        if ($amounts === []) {
                            Notification::make()->title(__('No dependents to update.'))->warning()->send();

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
                            ->title($updated > 0 ? __('Allocations updated') : __('No changes applied'))
                            ->body($body)
                            ->color($updated > 0 ? 'success' : 'info')
                            ->send();
                    }),
            ])
            ->recordActions([
                ActionGroup::make([
                    // ── Set single allocation ─────────────────────────────────────
                    Action::make('set_allocation')
                        ->label(__('Update allocation'))
                        ->icon('heroicon-o-adjustments-horizontal')
                        ->color('warning')
                        ->fillForm(fn (Member $record) => [
                            'monthly_contribution_amount' => $record->monthly_contribution_amount,
                            'note' => null,
                        ])
                        ->schema([
                            Forms\Components\Select::make('monthly_contribution_amount')
                                ->label(__('Monthly Contribution Amount'))
                                ->options(Member::contributionAmountOptions())
                                ->required()
                                ->helperText(fn (?Member $record) => $record
                                    ? __('Current allocation: :currency :alloc · Cash balance: :currency :cash', [
                                        'currency' => __('SAR'),
                                        'alloc' => number_format($record->monthly_contribution_amount),
                                        'cash' => number_format($record->cash_balance, 2),
                                    ])
                                    : ''),
                            Forms\Components\TextInput::make('note')
                                ->label(__('Note / Reason (optional)'))
                                ->maxLength(200)
                                ->placeholder(__('e.g. Income change')),
                        ])
                        ->action(function (Member $record, array $data) use ($parentMember) {
                            $parent = $parentMember();
                            if (! $parent) {
                                Notification::make()->title(__('Parent member not found.'))->danger()->send();

                                return;
                            }

                            $newAmount = (int) $data['monthly_contribution_amount'];
                            if (! Member::isValidContributionAmount($newAmount)) {
                                Notification::make()->title(__('Invalid amount selected.'))->danger()->send();

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
                                Notification::make()->title(__('Could not update allocation'))->body($e->getMessage())->danger()->send();

                                return;
                            }

                            if ($change === null) {
                                Notification::make()->title(__('No changes detected.'))->info()->send();

                                return;
                            }

                            Notification::make()
                                ->title(__('Allocation updated'))
                                ->body(__('Allocation was updated successfully for :name.', ['name' => $record->user?->name ?? __('Member')]))
                                ->success()
                                ->send();
                        }),

                    // ── Fund cash account ─────────────────────────────────────────
                    Action::make('fund_cash')
                        ->label(__('Fund Cash Account'))
                        ->icon('heroicon-o-banknotes')
                        ->color('success')
                        ->schema(function (Member $record) use ($parentMember) {
                            $parent = $parentMember();

                            return [
                                Forms\Components\Placeholder::make('balances')
                                    ->label(__('Balances'))
                                    ->content(
                                        __('Your cash: :currency :parent · :name cash: :currency :dependent', [
                                            'currency' => __('SAR'),
                                            'parent' => number_format($parent?->cash_balance ?? 0, 2),
                                            'name' => $record->user->name,
                                            'dependent' => number_format($record->cash_balance, 2),
                                        ])
                                    ),
                                Forms\Components\TextInput::make('amount')
                                    ->label(__('Amount to Transfer (SAR)'))
                                    ->numeric()
                                    ->minValue(1)
                                    ->required()
                                    ->prefix(__('SAR')),
                                Forms\Components\TextInput::make('note')
                                    ->label(__('Note (optional)'))
                                    ->maxLength(200),
                            ];
                        })
                        ->action(function (Member $record, array $data) use ($parentMember) {
                            $parent = $parentMember();
                            if (! $parent) {
                                Notification::make()->title(__('Your member record was not found.'))->danger()->send();

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
                                    ->title(__('Transfer Successful'))
                                    ->body(__(':currency :amount transferred to :name cash account.', [
                                        'currency' => __('SAR'),
                                        'amount' => number_format((float) $data['amount'], 2),
                                        'name' => $record->user->name,
                                    ]))
                                    ->success()
                                    ->send();
                            } catch (\Throwable $e) {
                                Notification::make()->title(__('Transfer Failed'))->body($e->getMessage())->danger()->send();
                            }
                        }),

                    // ── View allocation history ───────────────────────────────────
                    Action::make('view_history')
                        ->label(__('History'))
                        ->icon('heroicon-o-clock')
                        ->color('gray')
                        ->modalHeading(fn (Member $record) => __('Allocation History — :name', ['name' => $record->user?->name ?? '']))
                        ->modalContent(function (Member $record): HtmlString {
                            $changes = DependentAllocationChange::where('dependent_member_id', $record->id)
                                ->with('changedBy')
                                ->latest()
                                ->limit(30)
                                ->get();

                            if ($changes->isEmpty()) {
                                return new HtmlString('<p class="text-sm text-gray-500 p-4">' . e(__('No allocation changes recorded.')) . '</p>');
                            }

                            $rows = '';
                            foreach ($changes as $c) {
                                $dir = $c->isIncrease()
                                    ? '<span class="text-emerald-600 font-bold">↑</span>'
                                    : '<span class="text-amber-600 font-bold">↓</span>';
                                $delta = $c->isIncrease()
                                    ? '<span class="text-emerald-600">+'.e(__('SAR')).' '.number_format(abs($c->delta())).'</span>'
                                    : '<span class="text-amber-600">−'.e(__('SAR')).' '.number_format(abs($c->delta())).'</span>';
                                $by = e($c->changedBy?->name ?? __('System'));
                                $note = $c->note ? '<br><span class="text-gray-400 text-xs">'.e($c->note).'</span>' : '';
                                $date = $c->created_at->locale(app()->getLocale())->translatedFormat('d M Y H:i');

                                $rows .= "
                                <tr class=\"border-b border-gray-100 dark:border-gray-700\">
                                    <td class=\"py-2 px-3 text-xs text-gray-500\">{$date}</td>
                                    <td class=\"py-2 px-3 text-sm\">{$dir} ".e(__('SAR'))." {$c->old_amount} → ".e(__('SAR'))." {$c->new_amount}</td>
                                    <td class=\"py-2 px-3 text-sm\">{$delta}</td>
                                    <td class=\"py-2 px-3 text-sm\">{$by}{$note}</td>
                                </tr>";
                            }

                            return new HtmlString("
                            <div class=\"overflow-x-auto\">
                                <table class=\"w-full text-sm\">
                                    <thead>
                                        <tr class=\"bg-gray-50 dark:bg-gray-800 text-xs uppercase text-gray-500\">
                                            <th class=\"py-2 px-3 text-left\">" . e(__('Date')) . "</th>
                                            <th class=\"py-2 px-3 text-left\">" . e(__('Change')) . "</th>
                                            <th class=\"py-2 px-3 text-left\">" . e(__('Delta')) . "</th>
                                            <th class=\"py-2 px-3 text-left\">" . e(__('Changed By')) . "</th>
                                        </tr>
                                    </thead>
                                    <tbody>{$rows}</tbody>
                                </table>
                            </div>");
                        })
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel(__('Close')),
                ])
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->label('')
                    ->button(),
            ])
            ->emptyStateHeading(__('No dependents'))
            ->emptyStateDescription(__('You have no dependent members assigned to you.'));
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
