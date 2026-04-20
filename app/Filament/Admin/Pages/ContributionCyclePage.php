<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Resources\ContributionResource;
use App\Models\Contribution;
use App\Models\Member;
use App\Services\ContributionCycleService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ContributionCyclePage extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.admin.pages.contribution-cycle';

    protected static bool $shouldRegisterNavigation = false;

    public string $contributionPeriodTab = 'pending';

    public function setContributionTab(string $tab): void
    {
        $this->contributionPeriodTab = $tab;
        $this->resetTable();
    }

    protected static ?string $navigationLabel = 'Contribution Cycles';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-path-rounded-square';

    protected static ?int $navigationSort = 5;

    public static function getNavigationLabel(): string
    {
        return __('Contribution Cycles');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('app.nav.group.finance');
    }

    // =========================================================================
    // Header actions
    // =========================================================================

    public function getHeaderActions(): array
    {
        return [
            Action::make('allContributions')
                ->label(__('All contributions'))
                ->icon('heroicon-o-banknotes')
                ->url(ContributionResource::getUrl('index'))
                ->color('info'),
            Action::make('send_notifications')
                ->label(__('Send Due Notifications'))
                ->icon('heroicon-o-bell')
                ->color('warning')
                ->schema($this->periodFormSchema())
                ->fillForm($this->defaultPeriod())
                ->action(function (array $data) {
                    $count = app(ContributionCycleService::class)
                        ->sendDueNotifications((int) $data['month'], (int) $data['year']);

                    Notification::make()
                        ->title(__('Notifications Sent'))
                        ->body(__(':count member(s) notified for :period', ['count' => $count, 'period' => $this->periodLbl($data['month'], $data['year'])]))
                        ->success()
                        ->send();
                }),

            Action::make('run_cycle')
                ->label(__('Run Contribution Cycle'))
                ->icon('heroicon-o-play')
                ->color('primary')
                ->schema($this->periodFormSchema())
                ->fillForm($this->defaultPeriod())
                ->action(function (array $data) {
                    $service = app(ContributionCycleService::class);
                    $month = (int) $data['month'];
                    $year = (int) $data['year'];
                    $isLate = $service->isLate($month, $year);
                    $results = $service->applyContributions($month, $year);

                    $applied = count($results['applied']);
                    $insufficient = count($results['insufficient']);
                    $skipped = count($results['skipped']);
                    $period = $this->periodLbl($month, $year);

                    $body = __('Applied: :applied | Insufficient: :insufficient | Already processed: :skipped', [
                        'applied' => $applied,
                        'insufficient' => $insufficient,
                        'skipped' => $skipped,
                    ]);
                    if ($isLate) {
                        $body .= ' — '.__('⚠️ Contributions marked as LATE (past deadline).');
                    }

                    Notification::make()
                        ->title(__('Cycle Complete – :period', ['period' => $period]))
                        ->body($body)
                        ->color($insufficient > 0 ? 'warning' : 'success')
                        ->send();
                }),
        ];
    }

    // =========================================================================
    // Table: pending members (active, no contribution for current open period)
    // =========================================================================

    public function table(Table $table): Table
    {
        [$month, $year] = $this->currentOpenPeriod();

        if ($this->contributionPeriodTab === 'paid') {
            return $this->paidTable($table, $month, $year);
        }

        return $this->pendingTable($table, $month, $year);
    }

    private function pendingTable(Table $table, int $month, int $year): Table
    {
        $applied = Contribution::where('month', $month)->where('year', $year)->pluck('member_id');

        return $table
            ->query(
                fn (): Builder => Member::query()
                    ->where('status', 'active')
                    ->with(['user', 'accounts'])
                    ->whereNotIn('id', $applied)
            )
            ->heading(__('Pending Members – :period', ['period' => $this->periodLbl($month, $year)]))
            ->description(__('Active members who have not yet contributed for this period.'))
            ->emptyStateHeading(__('All members have contributed'))
            ->emptyStateDescription(__('No pending contributions for :period', ['period' => $this->periodLbl($month, $year)]))
            ->emptyStateIcon('heroicon-o-check-circle')
            ->columns([
                TextColumn::make('member_number')->label(__('Member #'))->sortable(),
                TextColumn::make('user.name')->label(__('Name'))->searchable(),
                TextColumn::make('monthly_contribution_amount')
                    ->label(__('Required (SAR)'))
                    ->money('SAR'),
                TextColumn::make('cash_balance')
                    ->label(__('Cash Balance'))
                    ->money('SAR')
                    ->getStateUsing(fn (Member $r) => $r->cash_balance)
                    ->color(fn (Member $r) => $r->cash_balance >= $r->monthly_contribution_amount ? 'success' : 'danger'),
                TextColumn::make('shortfall')
                    ->label(__('Shortfall'))
                    ->money('SAR')
                    ->getStateUsing(fn (Member $r) => max(0, $r->monthly_contribution_amount - $r->cash_balance))
                    ->color('danger'),
                TextColumn::make('parent.user.name')->label(__('Parent'))->placeholder('—'),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('apply_single')
                        ->label(__('Apply Now'))
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading(fn (Member $r) => __('Apply Contribution for :name?', ['name' => $r->user->name]))
                        ->modalDescription(
                            fn (Member $r) => __('This will debit SAR :required from their cash account (balance: SAR :balance).', [
                                'required' => number_format($r->monthly_contribution_amount),
                                'balance' => number_format($r->cash_balance, 2),
                            ])
                        )
                        ->disabled(function (Member $r) use ($month, $year) {
                            $late = app(ContributionCycleService::class)->lateFeeForContributionPeriod($month, $year);

                            return (float) $r->cash_balance < (float) $r->monthly_contribution_amount + $late;
                        })
                        ->action(function (Member $record) use ($month, $year) {
                            $service = app(ContributionCycleService::class);
                            $dummy = [];
                            $outcome = $service->applyOne($record, $month, $year, $dummy);

                            if ($outcome === 'applied') {
                                Notification::make()
                                    ->title(__('Contribution Applied'))
                                    ->body(__('SAR :amount applied for :name.', [
                                        'amount' => number_format($record->monthly_contribution_amount),
                                        'name' => $record->user->name,
                                    ]))
                                    ->success()
                                    ->send();
                            } elseif ($outcome === 'already_contributed') {
                                Notification::make()
                                    ->title(__('Already recorded'))
                                    ->body(Contribution::duplicateCycleMessage($month, $year))
                                    ->warning()
                                    ->send();
                            } elseif ($outcome === 'exempt') {
                                Notification::make()
                                    ->title(__('Member exempt'))
                                    ->body(__('This member is exempt from contributions while they have an approved or active loan.'))
                                    ->warning()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title(__('Could Not Apply'))
                                    ->body(match ($outcome) {
                                        'insufficient' => __('Cash balance is below the required monthly amount.'),
                                        default => __('Status: :status', ['status' => $outcome]),
                                    })
                                    ->warning()
                                    ->send();
                            }
                        }),
                ]),
            ]);
    }

    private function paidTable(Table $table, int $month, int $year): Table
    {
        return $table
            ->query(
                fn (): Builder => Member::query()
                    ->join('contributions', function ($join) use ($month, $year) {
                        $join->on('contributions.member_id', '=', 'members.id')
                            ->where('contributions.month', $month)
                            ->where('contributions.year', $year)
                            ->whereNull('contributions.deleted_at');
                    })
                    ->select('members.*', 'contributions.amount as contribution_amount', 'contributions.is_late as contribution_is_late', 'contributions.created_at as contribution_date')
                    ->with('user')
            )
            ->heading(__('Paid Members – :period', ['period' => $this->periodLbl($month, $year)]))
            ->description(__('Members who have already contributed for this period.'))
            ->emptyStateHeading(__('No contributions recorded'))
            ->emptyStateDescription(__('No members have contributed for :period yet.', ['period' => $this->periodLbl($month, $year)]))
            ->emptyStateIcon('heroicon-o-banknotes')
            ->columns([
                TextColumn::make('member_number')->label(__('Member #'))->sortable(),
                TextColumn::make('user.name')->label(__('Name'))->searchable(),
                TextColumn::make('contribution_amount')
                    ->label(__('Amount (SAR)'))
                    ->money('SAR'),
                TextColumn::make('contribution_is_late')
                    ->label(__('On Time?'))
                    ->badge()
                    ->getStateUsing(fn (Member $r) => $r->contribution_is_late ? __('Late') : __('On Time'))
                    ->color(fn (string $state) => $state === __('Late') ? 'warning' : 'success'),
                TextColumn::make('contribution_date')
                    ->label(__('Recorded At'))
                    ->dateTime('d M Y, H:i')
                    ->sortable(),
            ]);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function periodFormSchema(): array
    {
        return [
            Forms\Components\Select::make('month')
                ->label(__('Month'))
                ->options(array_combine(
                    range(1, 12),
                    array_map(fn ($m) => date('F', mktime(0, 0, 0, $m, 1)), range(1, 12))
                ))
                ->required(),
            Forms\Components\TextInput::make('year')
                ->label(__('Year'))
                ->numeric()
                ->required()
                ->minValue(2020),
        ];
    }

    private function defaultPeriod(): array
    {
        [$month, $year] = $this->currentOpenPeriod();

        return ['month' => $month, 'year' => $year];
    }

    /** The "current open" period is the previous calendar month (contributions are collected in arrears). */
    private function currentOpenPeriod(): array
    {
        return app(ContributionCycleService::class)->currentOpenPeriod();
    }

    private function periodLbl(int $month, int $year): string
    {
        return date('F', mktime(0, 0, 0, $month, 1)).' '.$year;
    }
}
