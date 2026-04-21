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

    protected static ?string $title = null;

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('Dependents');
    }

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
            ->emptyStateHeading(__('No dependent members'))
            ->emptyStateDescription(__('This member has no dependents assigned.'))
            ->striped()
            ->headerActions([
                $this->addDependentHeaderAction(),
                $this->allocateCycleHeaderAction(),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('member_number')->label(__('Member #'))->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('user.name')->label(__('Name'))->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('monthly_contribution_amount')
                    ->label(__('Monthly Allocation'))
                    ->money('SAR')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->label(__('Status'))
                    ->formatStateUsing(fn (?string $state): string => $state ? __(ucfirst(str_replace('_', ' ', $state))) : __('—'))
                    ->color(fn (string $state) => match ($state) {
                        'active' => 'success',
                        'suspended' => 'warning',
                        'delinquent', 'terminated' => 'danger',
                        default => 'gray',
                    })
                    ->toggleable(),
                Tables\Columns\TextColumn::make('cash_balance')
                    ->label(__('Cash Balance'))
                    ->money('SAR')
                    ->getStateUsing(fn (Member $r) => $r->cash_balance)
                    ->color(fn (Member $r) => $r->cash_balance >= 0 ? 'success' : 'danger')
                    ->toggleable(),
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
                Tables\Filters\Filter::make('cash_balance')
                    ->label(__('Cash balance (SAR)'))
                    ->schema([
                        Forms\Components\TextInput::make('min')->label(__('Min'))->numeric(),
                        Forms\Components\TextInput::make('max')->label(__('Max'))->numeric(),
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
                        ->label(__('Set Allocation'))
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
                                ->helperText(fn (Member $record) => __('Current: SAR :amount', ['amount' => number_format($record->monthly_contribution_amount)])),
                            Forms\Components\TextInput::make('note')
                                ->label(__('Admin Note (optional)'))
                                ->maxLength(200)
                                ->placeholder(__('Reason for change (sent to member)')),
                        ])
                        ->action(function (Member $record, array $data) {
                            $parent = $record->parent;
                            if (! $parent) {
                                // No parent: direct update without parent context
                                $old = $record->monthly_contribution_amount;
                                $new = (int) $data['monthly_contribution_amount'];
                                $record->update(['monthly_contribution_amount' => $new]);
                                Notification::make()
                                    ->title(__('Allocation Updated'))
                                    ->body(__('SAR :old → SAR :new (no parent; no allocation change record).', ['old' => number_format($old), 'new' => number_format($new)]))
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
                                Notification::make()->title(__('No change — same amount selected.'))->info()->send();

                                return;
                            }

                            Notification::make()
                                ->title(__('Allocation Updated'))
                                ->body(__(':member: SAR :old → SAR :new. Member notified.', [
                                    'member' => $record->user->name,
                                    'old' => number_format($change->old_amount),
                                    'new' => number_format($change->new_amount),
                                ]))
                                ->success()
                                ->send();
                        }),

                    // View allocation change history for this dependent
                    Action::make('view_allocation_history')
                        ->label(__('History'))
                        ->icon('heroicon-o-clock')
                        ->color('gray')
                        ->modalHeading(fn (Member $record) => __('Allocation History — :name', ['name' => $record->user->name]))
                        ->modalContent(function (Member $record): HtmlString {
                            $changes = DependentAllocationChange::where('dependent_member_id', $record->id)
                                ->with('changedBy', 'parent.user')
                                ->latest()
                                ->limit(50)
                                ->get();

                            if ($changes->isEmpty()) {
                                return new HtmlString('<p class="text-sm text-gray-500 p-4">'.e(__('No allocation changes recorded.')).'</p>');
                            }

                            $rows = '';
                            foreach ($changes as $c) {
                                $dir = $c->isIncrease()
                                    ? '<span class="text-emerald-600 font-bold">↑</span>'
                                    : '<span class="text-amber-600 font-bold">↓</span>';
                                $delta = $c->isIncrease()
                                    ? '<span class="text-emerald-600">+SAR '.number_format(abs($c->delta())).'</span>'
                                    : '<span class="text-amber-600">−SAR '.number_format(abs($c->delta())).'</span>';
                                $parent = e($c->parent?->user?->name ?? __('—'));
                                $by = e($c->changedBy?->name ?? __('System'));
                                $note = $c->note ? '<br><span class="text-gray-400 text-xs">'.e($c->note).'</span>' : '';
                                $date = $c->created_at->locale(app()->getLocale())->translatedFormat('d M Y H:i');

                                $rows .= "
                                <tr class=\"border-b border-gray-100 dark:border-gray-700\">
                                    <td class=\"py-2 px-3 text-xs text-gray-500\">{$date}</td>
                                    <td class=\"py-2 px-3\">{$dir} SAR {$c->old_amount} → SAR {$c->new_amount}</td>
                                    <td class=\"py-2 px-3\">{$delta}</td>
                                    <td class=\"py-2 px-3\">{$parent}</td>
                                    <td class=\"py-2 px-3\">{$by}{$note}</td>
                                </tr>";
                            }

                            $dateHeading = e(__('Date'));
                            $changeHeading = e(__('Change'));
                            $deltaHeading = e(__('Delta'));
                            $parentHeading = e(__('Parent'));
                            $changedByHeading = e(__('Changed By'));

                            return new HtmlString("
                            <div class=\"overflow-x-auto\">
                                <table class=\"w-full text-sm\">
                                    <thead>
                                        <tr class=\"bg-gray-50 dark:bg-gray-800 text-xs uppercase text-gray-500\">
                                            <th class=\"py-2 px-3 text-left\">{$dateHeading}</th>
                                            <th class=\"py-2 px-3 text-left\">{$changeHeading}</th>
                                            <th class=\"py-2 px-3 text-left\">{$deltaHeading}</th>
                                            <th class=\"py-2 px-3 text-left\">{$parentHeading}</th>
                                            <th class=\"py-2 px-3 text-left\">{$changedByHeading}</th>
                                        </tr>
                                    </thead>
                                    <tbody>{$rows}</tbody>
                                </table>
                            </div>");
                        })
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel(__('Close')),

                    // Fund dependent's cash account from this parent's cash account
                    Action::make('fund_cash')
                        ->label(__('Fund Cash Account'))
                        ->icon('heroicon-o-banknotes')
                        ->color('success')
                        ->schema([
                            Forms\Components\TextInput::make('amount')
                                ->label(__('Amount (SAR)'))
                                ->numeric()
                                ->minValue(1)
                                ->required()
                                ->prefix('SAR')
                                ->helperText(
                                    fn (Member $record) => __('Dependent\'s cash balance: SAR :dependent | Your cash balance: SAR :owner', [
                                        'dependent' => number_format($record->cash_balance, 2),
                                        'owner' => number_format($this->getOwnerRecord()->cash_balance, 2),
                                    ])
                                ),
                            Forms\Components\TextInput::make('note')
                                ->label(__('Note (optional)'))
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
                                    ->title(__('Cash Account Funded'))
                                    ->body(__('SAR :amount transferred to :name\'s cash account.', [
                                        'amount' => number_format($data['amount'], 2),
                                        'name' => $record->user->name,
                                    ]))
                                    ->success()
                                    ->send();
                            } catch (\Throwable $e) {
                                Notification::make()
                                    ->title(__('Transfer Failed'))
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
            ->label(__('Add Dependent'))
            ->icon('heroicon-o-user-plus')
            ->color('primary')
            ->authorize(
                fn (): bool => auth()->user()?->can('update', $this->cycleMember()) ?? false
            )
            ->visible(fn (): bool => $this->cycleMember()->parent_id === null)
            ->modalHeading(__('Add dependent'))
            ->modalDescription(
                __('Link another independent member as a dependent of this member. Only members with no sponsor and no dependents of their own are eligible.')
            )
            ->schema([
                Forms\Components\Select::make('member_id')
                    ->label(__('Member'))
                    ->options(fn (): array => $this->eligibleIndependentMemberOptions())
                    ->searchable()
                    ->required()
                    ->helperText(__('Must be independent: not sponsored (no parent) and not already sponsoring others.')),
            ])
            ->action(function (array $data): void {
                $owner = $this->cycleMember();
                $dependent = Member::query()->findOrFail((int) $data['member_id']);

                if ($dependent->id === $owner->id) {
                    return;
                }

                if ($dependent->parent_id !== null) {
                    Notification::make()->title(__('This member is already sponsored.'))->danger()->send();

                    return;
                }

                if ($dependent->dependents()->exists()) {
                    Notification::make()
                        ->title(__('This member cannot become a dependent'))
                        ->body(__('Members who already sponsor dependents cannot be assigned a parent.'))
                        ->danger()
                        ->send();

                    return;
                }

                $dependent->update(['parent_id' => $owner->id]);

                Notification::make()
                    ->title(__('Dependent linked'))
                    ->body((($dependent->user?->name) ?? __('Member')).' '.__('is now sponsored by this member.'))
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
