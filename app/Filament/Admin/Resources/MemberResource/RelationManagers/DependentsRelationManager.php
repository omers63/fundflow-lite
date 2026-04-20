<?php

namespace App\Filament\Admin\Resources\MemberResource\RelationManagers;

use App\Filament\Admin\Resources\MemberResource\Concerns\InteractsWithMemberCycleHeaderActions;
use App\Models\Account;
use App\Models\DependentAllocationChange;
use App\Models\Member;
use App\Services\AccountingService;
use App\Services\AllocationService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class DependentsRelationManager extends RelationManager
{
    use InteractsWithMemberCycleHeaderActions;

    protected static string $relationship = 'dependents';

    protected static ?string $title = 'Dependents';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('member_number')
            ->emptyStateHeading('No dependent members')
            ->emptyStateDescription('This member has no dependents assigned.')
            ->striped()
            ->headerActions([
                $this->addDependentHeaderAction(),
                $this->allocateCycleHeaderAction(),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('member_number')->label('Member #')->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('user.name')->label('Name')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('monthly_contribution_amount')
                    ->label('Monthly Allocation')
                    ->money('SAR')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->color(fn (string $state) => match ($state) {
                        'active' => 'success',
                        'suspended' => 'warning',
                        'delinquent', 'terminated' => 'danger',
                        default => 'gray',
                    })
                    ->toggleable(),
                Tables\Columns\TextColumn::make('cash_balance')
                    ->label('Cash Balance')
                    ->money('SAR')
                    ->getStateUsing(fn (Member $r) => $r->cash_balance)
                    ->color(fn (Member $r) => $r->cash_balance >= 0 ? 'success' : 'danger')
                    ->toggleable(),
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
                Tables\Filters\Filter::make('cash_balance')
                    ->label('Cash balance (SAR)')
                    ->schema([
                        Forms\Components\TextInput::make('min')->label('Min')->numeric(),
                        Forms\Components\TextInput::make('max')->label('Max')->numeric(),
                    ])
                    ->columns(2)
                    ->query(function ($query, array $data) {
                        return $query->whereHas('accounts', function ($q) use ($data) {
                            $q->where('type', Account::TYPE_MEMBER_CASH);
                            if (filled($data['min'] ?? null)) {
                                $q->where('balance', '>=', $data['min']);
                            }
                            if (filled($data['max'] ?? null)) {
                                $q->where('balance', '<=', $data['max']);
                            }
                        });
                    }),
            ])
            ->recordActions([
                ActionGroup::make([
                    // Change this dependent's allocation amount (notifies dependent + admin)
                    Action::make('set_allocation')
                        ->label('Set Allocation')
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
                                ->helperText(fn (Member $record) => 'Current: SAR '.number_format($record->monthly_contribution_amount)),
                            Forms\Components\TextInput::make('note')
                                ->label('Admin Note (optional)')
                                ->maxLength(200)
                                ->placeholder('Reason for change (sent to member)'),
                        ])
                        ->action(function (Member $record, array $data) {
                            $parent = $record->parent;
                            if (! $parent) {
                                // No parent: direct update without parent context
                                $old = $record->monthly_contribution_amount;
                                $new = (int) $data['monthly_contribution_amount'];
                                $record->update(['monthly_contribution_amount' => $new]);
                                Notification::make()
                                    ->title('Allocation Updated')
                                    ->body('SAR '.number_format($old).' → SAR '.number_format($new).' (no parent; no allocation change record).')
                                    ->warning()
                                    ->send();

                                return;
                            }

                            $change = app(AllocationService::class)->changeAllocation(
                                parent: $parent,
                                dependent: $record,
                                newAmount: (int) $data['monthly_contribution_amount'],
                                note: $data['note'] ?? null,
                            );

                            if ($change === null) {
                                Notification::make()->title('No change — same amount selected.')->info()->send();

                                return;
                            }

                            Notification::make()
                                ->title('Allocation Updated')
                                ->body("{$record->user->name}: SAR ".number_format($change->old_amount).' → SAR '.number_format($change->new_amount).'. Member notified.')
                                ->success()
                                ->send();
                        }),

                    // View allocation change history for this dependent
                    Action::make('view_allocation_history')
                        ->label('History')
                        ->icon('heroicon-o-clock')
                        ->color('gray')
                        ->modalHeading(fn (Member $record) => "Allocation History — {$record->user->name}")
                        ->modalContent(function (Member $record): HtmlString {
                            $changes = DependentAllocationChange::where('dependent_member_id', $record->id)
                                ->with('changedBy', 'parent.user')
                                ->latest()
                                ->limit(50)
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
                                $parent = e($c->parent?->user?->name ?? '—');
                                $by = e($c->changedBy?->name ?? 'System');
                                $note = $c->note ? '<br><span class="text-gray-400 text-xs">'.e($c->note).'</span>' : '';
                                $date = $c->created_at->format('d M Y H:i');

                                $rows .= "
                                <tr class=\"border-b border-gray-100 dark:border-gray-700\">
                                    <td class=\"py-2 px-3 text-xs text-gray-500\">{$date}</td>
                                    <td class=\"py-2 px-3\">{$dir} SAR {$c->old_amount} → SAR {$c->new_amount}</td>
                                    <td class=\"py-2 px-3\">{$delta}</td>
                                    <td class=\"py-2 px-3\">{$parent}</td>
                                    <td class=\"py-2 px-3\">{$by}{$note}</td>
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
                                            <th class=\"py-2 px-3 text-left\">Parent</th>
                                            <th class=\"py-2 px-3 text-left\">Changed By</th>
                                        </tr>
                                    </thead>
                                    <tbody>{$rows}</tbody>
                                </table>
                            </div>");
                        })
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Close'),

                    // Fund dependent's cash account from this parent's cash account
                    Action::make('fund_cash')
                        ->label('Fund Cash Account')
                        ->icon('heroicon-o-banknotes')
                        ->color('success')
                        ->schema([
                            Forms\Components\TextInput::make('amount')
                                ->label('Amount (SAR)')
                                ->numeric()
                                ->minValue(1)
                                ->required()
                                ->prefix('SAR')
                                ->helperText(
                                    fn (Member $record) => "Dependent's cash balance: SAR ".number_format($record->cash_balance, 2).
                                    ' | Your cash balance: SAR '.number_format($this->getOwnerRecord()->cash_balance, 2)
                                ),
                            Forms\Components\TextInput::make('note')
                                ->label('Note (optional)')
                                ->maxLength(200),
                        ])
                        ->action(function (Member $record, array $data) {
                            $parent = $this->getOwnerRecord();

                            try {
                                app(AccountingService::class)->fundDependentCashAccount(
                                    parent: $parent,
                                    dependent: $record,
                                    amount: (float) $data['amount'],
                                    note: $data['note'] ?? '',
                                );

                                Notification::make()
                                    ->title('Cash Account Funded')
                                    ->body('SAR '.number_format($data['amount'], 2)." transferred to {$record->user->name}'s cash account.")
                                    ->success()
                                    ->send();
                            } catch (\Throwable $e) {
                                Notification::make()
                                    ->title('Transfer Failed')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),
                ]),
            ]);
    }

    protected function addDependentHeaderAction(): Action
    {
        return Action::make('add_dependent')
            ->label('Add Dependent')
            ->icon('heroicon-o-user-plus')
            ->color('primary')
            ->authorize(
                fn (): bool => auth()->user()?->can('update', $this->cycleMember()) ?? false
            )
            ->visible(fn (): bool => $this->cycleMember()->parent_id === null)
            ->modalHeading('Add dependent')
            ->modalDescription(
                'Link another independent member as a dependent of this member. Only members with no sponsor and no dependents of their own are eligible.'
            )
            ->schema([
                Forms\Components\Select::make('member_id')
                    ->label('Member')
                    ->options(fn (): array => $this->eligibleIndependentMemberOptions())
                    ->searchable()
                    ->required()
                    ->helperText('Must be independent: not sponsored (no parent) and not already sponsoring others.'),
            ])
            ->action(function (array $data): void {
                $owner = $this->cycleMember();
                $dependent = Member::query()->findOrFail((int) $data['member_id']);

                if ($dependent->id === $owner->id) {
                    return;
                }

                if ($dependent->parent_id !== null) {
                    Notification::make()->title('This member is already sponsored.')->danger()->send();

                    return;
                }

                if ($dependent->dependents()->exists()) {
                    Notification::make()
                        ->title('This member cannot become a dependent')
                        ->body('Members who already sponsor dependents cannot be assigned a parent.')
                        ->danger()
                        ->send();

                    return;
                }

                $dependent->update(['parent_id' => $owner->id]);

                Notification::make()
                    ->title('Dependent linked')
                    ->body(($dependent->user?->name ?? 'Member').' is now sponsored by this member.')
                    ->success()
                    ->send();
            });
    }

    /**
     * @return array<int, string>
     */
    protected function eligibleIndependentMemberOptions(): array
    {
        $owner = $this->cycleMember();

        return Member::query()
            ->whereNull('parent_id')
            ->whereDoesntHave('dependents')
            ->whereKeyNot($owner->id)
            ->with('user')
            ->orderBy('member_number')
            ->get()
            ->mapWithKeys(
                fn (Member $m) => [$m->id => "{$m->member_number} — {$m->user?->name}"]
            )
            ->all();
    }
}
