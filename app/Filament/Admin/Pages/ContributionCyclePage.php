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
                ->label('All contributions')
                ->icon('heroicon-o-banknotes')
                ->url(ContributionResource::getUrl('index'))
                ->color('info'),
            Action::make('send_notifications')
                ->label('Send Due Notifications')
                ->icon('heroicon-o-bell')
                ->color('warning')
                ->schema($this->periodFormSchema())
                ->fillForm($this->defaultPeriod())
                ->action(function (array $data) {
                    $count = app(ContributionCycleService::class)
                        ->sendDueNotifications((int) $data['month'], (int) $data['year']);

                    Notification::make()
                        ->title('Notifications Sent')
                        ->body("{$count} member(s) notified for ".$this->periodLbl($data['month'], $data['year']))
                        ->success()
                        ->send();
                }),

            Action::make('run_cycle')
                ->label('Run Contribution Cycle')
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

                    $body = "Applied: {$applied} | Insufficient: {$insufficient} | Already processed: {$skipped}";
                    if ($isLate) {
                        $body .= ' — ⚠️ Contributions marked as LATE (past deadline).';
                    }

                    Notification::make()
                        ->title("Cycle Complete – {$period}")
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
            ->heading('Pending Members – '.$this->periodLbl($month, $year))
            ->description('Active members who have not yet contributed for this period.')
            ->emptyStateHeading('All members have contributed')
            ->emptyStateDescription('No pending contributions for '.$this->periodLbl($month, $year))
            ->emptyStateIcon('heroicon-o-check-circle')
            ->columns([
                TextColumn::make('member_number')->label('Member #')->sortable(),
                TextColumn::make('user.name')->label('Name')->searchable(),
                TextColumn::make('monthly_contribution_amount')
                    ->label('Required (SAR)')
                    ->money('SAR'),
                TextColumn::make('cash_balance')
                    ->label('Cash Balance')
                    ->money('SAR')
                    ->getStateUsing(fn (Member $r) => $r->cash_balance)
                    ->color(fn (Member $r) => $r->cash_balance >= $r->monthly_contribution_amount ? 'success' : 'danger'),
                TextColumn::make('shortfall')
                    ->label('Shortfall')
                    ->money('SAR')
                    ->getStateUsing(fn (Member $r) => max(0, $r->monthly_contribution_amount - $r->cash_balance))
                    ->color('danger'),
                TextColumn::make('parent.user.name')->label('Parent')->placeholder('—'),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('apply_single')
                        ->label('Apply Now')
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading(fn (Member $r) => "Apply Contribution for {$r->user->name}?")
                        ->modalDescription(
                            fn (Member $r) => 'This will debit SAR '.number_format($r->monthly_contribution_amount).
                            ' from their cash account (balance: SAR '.number_format($r->cash_balance, 2).').'
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
                                    ->title('Contribution Applied')
                                    ->body('SAR '.number_format($record->monthly_contribution_amount)." applied for {$record->user->name}.")
                                    ->success()
                                    ->send();
                            } elseif ($outcome === 'already_contributed') {
                                Notification::make()
                                    ->title('Already recorded')
                                    ->body(Contribution::duplicateCycleMessage($month, $year))
                                    ->warning()
                                    ->send();
                            } elseif ($outcome === 'exempt') {
                                Notification::make()
                                    ->title('Member exempt')
                                    ->body('This member is exempt from contributions while they have an approved or active loan.')
                                    ->warning()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title('Could Not Apply')
                                    ->body(match ($outcome) {
                                        'insufficient' => 'Cash balance is below the required monthly amount.',
                                        default => 'Status: '.$outcome,
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
            ->heading('Paid Members – '.$this->periodLbl($month, $year))
            ->description('Members who have already contributed for this period.')
            ->emptyStateHeading('No contributions recorded')
            ->emptyStateDescription('No members have contributed for '.$this->periodLbl($month, $year).' yet.')
            ->emptyStateIcon('heroicon-o-banknotes')
            ->columns([
                TextColumn::make('member_number')->label('Member #')->sortable(),
                TextColumn::make('user.name')->label('Name')->searchable(),
                TextColumn::make('contribution_amount')
                    ->label('Amount (SAR)')
                    ->money('SAR'),
                TextColumn::make('contribution_is_late')
                    ->label('On Time?')
                    ->badge()
                    ->getStateUsing(fn (Member $r) => $r->contribution_is_late ? 'Late' : 'On Time')
                    ->color(fn (string $state) => $state === 'Late' ? 'warning' : 'success'),
                TextColumn::make('contribution_date')
                    ->label('Recorded At')
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
                ->label('Month')
                ->options(array_combine(
                    range(1, 12),
                    array_map(fn ($m) => date('F', mktime(0, 0, 0, $m, 1)), range(1, 12))
                ))
                ->required(),
            Forms\Components\TextInput::make('year')
                ->label('Year')
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
