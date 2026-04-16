<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Contribution;
use App\Models\DependentCashAllocation;
use App\Models\Member;
use App\Models\Setting;
use App\Notifications\ContributionAppliedNotification;
use App\Notifications\ContributionDueNotification;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

class ContributionCycleService
{
    /** How far back (in months) to offer cycles when applying a member contribution. */
    private const CONTRIBUTION_CYCLE_LOOKBACK_MONTHS = 24;

    public function __construct(
        protected AccountingService $accounting,
        protected LateFeeService $lateFees,
    ) {
    }

    // =========================================================================
    // Cycle window (allocation / collection / repayment)
    // =========================================================================

    /**
     * First calendar day of the cycle for period (month/year). Configurable via Setting::contributionCycleStartDay().
     */
    public function cycleStartDay(): int
    {
        return Setting::contributionCycleStartDay();
    }

    /**
     * When the cycle for the given contribution month/year begins (start of that day, app timezone).
     */
    public function cycleStartAt(int $month, int $year): Carbon
    {
        $day = $this->cycleStartDay();
        $last = (int) Carbon::create($year, $month, 1)->endOfMonth()->day;
        $d = min($day, $last);

        return Carbon::create($year, $month, $d)->startOfDay();
    }

    /**
     * End of the due date for the cycle (last moment to pay without being late): end of the day before the next cycle starts.
     */
    public function cycleDueEndAt(int $month, int $year): Carbon
    {
        $start = $this->cycleStartAt($month, $year);
        $nextMonth = $start->copy()->addMonthNoOverflow();
        $nextStart = $this->cycleStartAt((int) $nextMonth->month, (int) $nextMonth->year);

        return $nextStart->copy()->subDay()->endOfDay();
    }

    /**
     * Human-readable window for UI, e.g. "6 Jun 2026 – 5 Jul 2026 (due end of 5 Jul 2026)".
     */
    public function cycleWindowDescription(int $month, int $year): string
    {
        $start = $this->cycleStartAt($month, $year);
        $dueEnd = $this->cycleDueEndAt($month, $year);

        return $start->format('j M Y') . ' – ' . $dueEnd->format('j M Y')
            . ' (due end of ' . $dueEnd->format('j M Y') . ')';
    }

    // =========================================================================
    // Deadline helpers
    // =========================================================================

    /**
     * Contribution/repayment deadline for the cycle month: end of the due date (same as cycleDueEndAt).
     */
    public function deadline(int $month, int $year): Carbon
    {
        return $this->cycleDueEndAt($month, $year);
    }

    /**
     * True if today is past the contribution deadline for the given period.
     */
    public function isLate(int $month, int $year): bool
    {
        return now()->greaterThan($this->deadline($month, $year));
    }

    /** Human-readable period label: "June 2026" */
    public function periodLabel(int $month, int $year): string
    {
        return date('F', mktime(0, 0, 0, $month, 1)) . ' ' . $year;
    }

    /**
     * The cycle month/year currently open for collection (the window containing "now").
     *
     * @return array{0: int, 1: int} month, year
     */
    public function currentOpenPeriod(): array
    {
        $now = now();
        $cursor = $now->copy()->startOfMonth();

        for ($i = 0; $i < 15; $i++) {
            $m = (int) $cursor->month;
            $y = (int) $cursor->year;
            $start = $this->cycleStartAt($m, $y);
            $dueEnd = $this->cycleDueEndAt($m, $y);

            if ($now->gte($start) && $now->lte($dueEnd)) {
                return [$m, $y];
            }

            $cursor->subMonthNoOverflow();
        }

        $fallback = $now->copy()->subMonthNoOverflow();

        return [(int) $fallback->month, (int) $fallback->year];
    }

    public function currentOpenPeriodLabel(): string
    {
        [$m, $y] = $this->currentOpenPeriod();

        return $this->periodLabel($m, $y);
    }

    /** Select value for HTML forms: `Y-m`, e.g. `2026-04`. */
    public function contributionCycleKey(int $month, int $year): string
    {
        return sprintf('%04d-%02d', $year, $month);
    }

    /**
     * @return array{0: int, 1: int} month, year
     */
    public function parseContributionCycleKey(string $key): array
    {
        if (!preg_match('/^(\d{4})-(\d{2})$/', $key, $m)) {
            throw new \InvalidArgumentException('Invalid contribution cycle key.');
        }

        return [(int) $m[2], (int) $m[1]];
    }

    /**
     * Whether a parent→dependent cash allocation has already been completed for this cycle.
     */
    public function dependentAllocationExistsForPeriod(Member $dependent, int $month, int $year): bool
    {
        return DependentCashAllocation::query()
            ->where('dependent_member_id', $dependent->id)
            ->where('allocation_month', $month)
            ->where('allocation_year', $year)
            ->exists();
    }

    /**
     * Active dependent with a positive monthly amount and not contribution-exempt (ignores allocation / Contribution rows).
     */
    public function memberBaseEligibleForDependentAllocation(Member $dependent): bool
    {
        if ($dependent->trashed() || $dependent->status !== 'active') {
            return false;
        }

        if ((int) $dependent->monthly_contribution_amount <= 0) {
            return false;
        }

        if ($dependent->isExemptFromContributions()) {
            return false;
        }

        return true;
    }

    /**
     * Whether this dependent may receive parent cash allocation for the cycle (independent of Contribution rows).
     * Blocked when an allocation for this cycle already exists.
     */
    public function memberEligibleForDependentAllocationFunding(Member $dependent, int $month, int $year): bool
    {
        if (!$this->memberBaseEligibleForDependentAllocation($dependent)) {
            return false;
        }

        return !$this->dependentAllocationExistsForPeriod($dependent, $month, $year);
    }

    /**
     * Cash still needed on the dependent (monthly amount − cash balance), when allocation for the cycle is allowed.
     */
    public function dependentAllocationShortfallForPeriod(Member $dependent, int $month, int $year): float
    {
        if (!$this->memberEligibleForDependentAllocationFunding($dependent, $month, $year)) {
            return 0.0;
        }

        $dependent->unsetRelation('accounts');
        $needed = (float) $dependent->monthly_contribution_amount
            + $this->lateFeeForContributionPeriod($month, $year);
        $cash = (float) ($dependent->cashAccount()?->balance ?? 0);

        return max(0, $needed - $cash);
    }

    /**
     * True when this member may still post a contribution for the given calendar month (no row yet, eligible).
     */
    public function memberCanApplyContributionForPeriod(Member $member, int $month, int $year): bool
    {
        if ($member->trashed() || $member->status !== 'active') {
            return false;
        }

        if ((int) $member->monthly_contribution_amount <= 0) {
            return false;
        }

        if ($member->isExemptFromContributions()) {
            return false;
        }

        return !Contribution::query()
            ->where('member_id', $member->id)
            ->where('month', $month)
            ->where('year', $year)
            ->exists();
    }

    /**
     * @return array<string, string> keyed by contributionCycleKey, label newest-first within the lookback window
     */
    public function contributionCycleSelectOptionsForMember(Member $member): array
    {
        $options = [];
        [$curM, $curY] = $this->currentOpenPeriod();
        $cursor = Carbon::create($curY, $curM, 1)->startOfMonth();

        for ($i = 0; $i < self::CONTRIBUTION_CYCLE_LOOKBACK_MONTHS; $i++) {
            $m = (int) $cursor->month;
            $y = (int) $cursor->year;

            if ($this->memberCanApplyContributionForPeriod($member, $m, $y)) {
                $options[$this->contributionCycleKey($m, $y)] = $this->periodLabel($m, $y);
            }

            $cursor->subMonthNoOverflow();
        }

        return $options;
    }

    /**
     * Cycles for bulk apply (same lookback window; eligibility is checked per member when applying).
     *
     * @return array<string, string>
     */
    public function contributionCycleSelectOptionsForBulk(): array
    {
        $options = [];
        [$curM, $curY] = $this->currentOpenPeriod();
        $cursor = Carbon::create($curY, $curM, 1)->startOfMonth();

        for ($i = 0; $i < self::CONTRIBUTION_CYCLE_LOOKBACK_MONTHS; $i++) {
            $m = (int) $cursor->month;
            $y = (int) $cursor->year;
            $options[$this->contributionCycleKey($m, $y)] = $this->periodLabel($m, $y);
            $cursor->subMonthNoOverflow();
        }

        return $options;
    }

    public function defaultContributionCycleKeyForMember(Member $member): ?string
    {
        $opts = $this->contributionCycleSelectOptionsForMember($member);
        if ($opts === []) {
            return null;
        }

        [$curM, $curY] = $this->currentOpenPeriod();
        $preferred = $this->contributionCycleKey($curM, $curY);

        if (isset($opts[$preferred])) {
            return $preferred;
        }

        return array_key_first($opts);
    }

    /** True when at least one payable cycle exists in the lookback window (for showing Contribute actions). */
    public function memberHasPayableContributionCycle(Member $member): bool
    {
        return $this->contributionCycleSelectOptionsForMember($member) !== [];
    }

    /** Whether to show "Contribute" for the open period (active member, amount, not exempt, no row yet). */
    public function shouldOfferOpenPeriodContribution(Member $member): bool
    {
        if ($member->status !== 'active') {
            return false;
        }

        if ((int) $member->monthly_contribution_amount <= 0) {
            return false;
        }

        if ($member->isExemptFromContributions()) {
            return false;
        }

        [$month, $year] = $this->currentOpenPeriod();

        return !Contribution::query()
            ->where('member_id', $member->id)
            ->where('month', $month)
            ->where('year', $year)
            ->exists();
    }

    public function hasInsufficientCashForOpenPeriodContribution(Member $member): bool
    {
        [$m, $y] = $this->currentOpenPeriod();
        $amount = (float) $member->monthly_contribution_amount;
        $lateFee = $this->lateFeeForContributionPeriod($m, $y);
        $required = $amount + $lateFee;

        return (float) $member->cash_balance < $required;
    }

    /**
     * Late fee (SAR) for a contribution in the given calendar month, tiered by calendar days after the cycle deadline.
     *
     * @param  \Carbon\Carbon|null  $at  Defaults to now (use payment / import timestamp when known).
     */
    public function lateFeeForContributionPeriod(int $month, int $year, ?Carbon $at = null): float
    {
        $at = $at ?? now();
        $deadline = $this->deadline($month, $year);
        $days = $this->lateFees->daysPastDue($deadline, $at);

        return $this->lateFees->contributionLateFeeForDays($days);
    }

    /**
     * True when this member is a parent with at least one active dependent who still needs
     * their contribution row for the current open period (same gate as Contribute for dependents).
     */
    public function shouldOfferOpenPeriodDependentAllocation(Member $parent): bool
    {
        if ($parent->trashed() || $parent->status !== 'active') {
            return false;
        }

        if (!$parent->dependents()->exists()) {
            return false;
        }

        [$m, $y] = $this->currentOpenPeriod();

        foreach ($parent->dependents()->where('status', 'active')->get() as $dependent) {
            if ($this->dependentAllocationShortfallForPeriod($dependent, $m, $y) > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Sum of cash shortfalls for active dependents who still owe the contribution for the given calendar month.
     */
    public function totalDependentShortfallForParentForPeriod(Member $parent, int $month, int $year): float
    {
        $parent->loadMissing(['dependents.user', 'dependents.accounts']);
        $total = 0.0;

        foreach ($parent->dependents()->where('status', 'active')->get() as $dependent) {
            $total += $this->dependentAllocationShortfallForPeriod($dependent, $month, $year);
        }

        return $total;
    }

    /**
     * @return array<string, string> keyed by contributionCycleKey, newest month first
     */
    public function allocationCycleSelectOptionsForParent(Member $parent): array
    {
        $options = [];
        [$curM, $curY] = $this->currentOpenPeriod();
        $cursor = Carbon::create($curY, $curM, 1)->startOfMonth();

        for ($i = 0; $i < self::CONTRIBUTION_CYCLE_LOOKBACK_MONTHS; $i++) {
            $m = (int) $cursor->month;
            $y = (int) $cursor->year;
            $total = $this->totalDependentShortfallForParentForPeriod($parent, $m, $y);

            if ($total <= 0) {
                $cursor->subMonthNoOverflow();

                continue;
            }

            if ((float) $parent->cash_balance < $total) {
                $cursor->subMonthNoOverflow();

                continue;
            }

            $options[$this->contributionCycleKey($m, $y)] = $this->periodLabel($m, $y);
            $cursor->subMonthNoOverflow();
        }

        return $options;
    }

    public function defaultAllocationCycleKeyForParent(Member $parent): ?string
    {
        $opts = $this->allocationCycleSelectOptionsForParent($parent);
        if ($opts === []) {
            return null;
        }

        [$curM, $curY] = $this->currentOpenPeriod();
        $preferred = $this->contributionCycleKey($curM, $curY);

        if (isset($opts[$preferred])) {
            return $preferred;
        }

        return array_key_first($opts);
    }

    /**
     * Sum of cash shortfalls (needed − cash, floored at 0) for active dependents who still owe
     * the open-period contribution.
     */
    public function totalOpenPeriodDependentShortfallForParent(Member $parent): float
    {
        [$m, $y] = $this->currentOpenPeriod();

        return $this->totalDependentShortfallForParentForPeriod($parent, $m, $y);
    }

    /**
     * Show "Allocate" when at least one cycle in the lookback window has shortfall & parent can cover it.
     */
    public function shouldShowDependentAllocationAction(Member $parent): bool
    {
        if ($parent->trashed() || $parent->status !== 'active') {
            return false;
        }

        if (!$parent->dependents()->where('status', 'active')->exists()) {
            return false;
        }

        return $this->allocationCycleSelectOptionsForParent($parent) !== [];
    }

    /**
     * Transfer parent cash to each dependent's cash to cover shortfall for the given contribution month.
     *
     * @return array{transfers: int, details: list<string>}
     */
    public function applyDependentAllocationForParentForPeriod(Member $parent, int $month, int $year): array
    {
        $details = [];
        $transfers = 0;
        $periodLabel = $this->periodLabel($month, $year);

        if (!$parent->dependents()->where('status', 'active')->exists()) {
            return [
                'transfers' => 0,
                'details' => ['This member has no active dependents.'],
            ];
        }

        $parent->unsetRelation('accounts');
        $parent->load(['user', 'accounts', 'dependents.user']);

        $totalShortfall = $this->totalDependentShortfallForParentForPeriod($parent, $month, $year);
        if ((float) $parent->cash_balance < $totalShortfall) {
            return [
                'transfers' => 0,
                'details' => [
                    'Parent cash (SAR ' . number_format((float) $parent->cash_balance, 2) . ') is insufficient to cover total shortfalls (SAR ' . number_format($totalShortfall, 2) . ').',
                ],
            ];
        }

        foreach ($parent->dependents()->where('status', 'active')->orderBy('member_number')->get() as $dependent) {
            $dependent->unsetRelation('accounts');
            $dependent->load(['accounts', 'user']);

            if ($this->dependentAllocationExistsForPeriod($dependent, $month, $year)) {
                $details[] = $this->dependentAllocationDetailLine(
                    $dependent,
                    'Allocation for ' . $periodLabel . ' was already completed.',
                );

                continue;
            }

            if (!$this->memberBaseEligibleForDependentAllocation($dependent)) {
                continue;
            }

            $needed = (float) $dependent->monthly_contribution_amount;
            $cash = (float) ($dependent->cashAccount()?->balance ?? 0);
            $shortfall = max(0, $needed - $cash);

            if ($shortfall <= 0) {
                $details[] = $this->dependentAllocationDetailLine(
                    $dependent,
                    'Cash already covers SAR ' . number_format($needed, 2) . '.',
                );

                continue;
            }

            try {
                DB::transaction(function () use ($parent, $dependent, $shortfall, $periodLabel, $month, $year): void {
                    $this->accounting->fundDependentCashAccount(
                        parent: $parent,
                        dependent: $dependent,
                        amount: $shortfall,
                        note: 'Allocation — ' . $periodLabel,
                    );

                    DependentCashAllocation::query()->create([
                        'parent_member_id' => $parent->id,
                        'dependent_member_id' => $dependent->id,
                        'allocation_month' => $month,
                        'allocation_year' => $year,
                        'amount' => $shortfall,
                    ]);
                });
                $transfers++;
                $details[] = $this->dependentAllocationDetailLine(
                    $dependent,
                    'Transferred SAR ' . number_format($shortfall, 2) . ' for ' . $periodLabel . '.',
                );
            } catch (\Throwable $e) {
                $details[] = $this->dependentAllocationDetailLine($dependent, $e->getMessage());
            }
        }

        if ($transfers === 0 && $details === []) {
            $details[] = 'No dependent shortfalls to cover for ' . $periodLabel . '.';
        }

        return ['transfers' => $transfers, 'details' => $details];
    }

    /**
     * @return array{transfers: int, details: list<string>}
     */
    public function applyOpenPeriodDependentAllocationForParent(Member $parent): array
    {
        [$m, $y] = $this->currentOpenPeriod();

        return $this->applyDependentAllocationForParentForPeriod($parent, $m, $y);
    }

    public function dependentAllocationModalDescriptionForPeriod(Member $parent, int $month, int $year): HtmlString
    {
        $parent->loadMissing(['dependents.user', 'dependents.accounts']);
        $parent->unsetRelation('accounts');

        $parentCash = (float) $parent->cash_balance;
        $periodLabel = $this->periodLabel($month, $year);
        $totalShortfall = $this->totalDependentShortfallForParentForPeriod($parent, $month, $year);

        $rowsHtml = '';
        foreach ($parent->dependents()->where('status', 'active')->orderBy('member_number')->get() as $dependent) {
            $needed = (float) $dependent->monthly_contribution_amount;
            $cash = (float) ($dependent->cashAccount()?->balance ?? 0);
            $shortfall = $this->dependentAllocationShortfallForPeriod($dependent, $month, $year);

            if ($shortfall <= 0) {
                continue;
            }

            $name = e($dependent->user?->name ?? '—');
            $rowsHtml .= '<tr class="border-b border-gray-100 last:border-0 dark:border-white/10">'
                . '<td class="py-2.5 pr-3 text-gray-950 dark:text-white">' . $name . '</td>'
                . '<td class="py-2.5 pr-3 text-right tabular-nums text-gray-700 dark:text-gray-300">SAR ' . e(number_format($cash, 2)) . '</td>'
                . '<td class="py-2.5 pr-3 text-right tabular-nums text-gray-700 dark:text-gray-300">SAR ' . e(number_format($needed, 2)) . '</td>'
                . '<td class="py-2.5 text-right tabular-nums font-medium text-gray-950 dark:text-white">SAR ' . e(number_format($shortfall, 2)) . '</td>'
                . '</tr>';
        }

        if ($rowsHtml === '') {
            $summary = '<p class="text-sm text-gray-600 dark:text-gray-400">Your cash balance: <span class="font-semibold text-gray-950 dark:text-white">SAR ' . e(number_format($parentCash, 2)) . '</span></p>'
                . '<p class="mt-2 text-sm text-gray-600 dark:text-gray-400">No dependent shortfalls for ' . e($periodLabel) . '.</p>';

            return new HtmlString('<div class="space-y-1 text-sm">' . $summary . '</div>');
        }

        $table = '<div class="overflow-x-auto rounded-lg border border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900/40">'
            . '<table class="w-full min-w-[22rem] text-sm">'
            . '<thead><tr class="border-b border-gray-200 bg-gray-50 dark:border-white/10 dark:bg-white/5">'
            . '<th scope="col" class="py-2.5 pl-3 pr-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Dependent</th>'
            . '<th scope="col" class="py-2.5 pr-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Cash</th>'
            . '<th scope="col" class="py-2.5 pr-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Needed</th>'
            . '<th scope="col" class="py-2.5 pr-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Transfer</th>'
            . '</tr></thead>'
            . '<tbody>' . $rowsHtml . '</tbody>'
            . '</table></div>';

        $html = '<div class="space-y-4 text-sm">'
            . '<p class="text-gray-600 dark:text-gray-400">Your cash balance: '
            . '<span class="font-semibold text-gray-950 dark:text-white">SAR ' . e(number_format($parentCash, 2)) . '</span>'
            . ' · Total to transfer: '
            . '<span class="font-semibold text-gray-950 dark:text-white">SAR ' . e(number_format($totalShortfall, 2)) . '</span></p>'
            . $table
            . '<p class="text-xs leading-relaxed text-gray-500 dark:text-gray-400">'
            . 'Confirming will move each <span class="font-medium text-gray-700 dark:text-gray-300">Transfer</span> amount '
            . 'from your cash to that dependent\'s cash for <span class="font-medium text-gray-700 dark:text-gray-300">' . e($periodLabel) . '</span>.'
            . '</p>'
            . '</div>';

        return new HtmlString($html);
    }

    public function openPeriodDependentAllocationModalDescription(Member $parent): HtmlString
    {
        [$m, $y] = $this->currentOpenPeriod();

        return $this->dependentAllocationModalDescriptionForPeriod($parent, $m, $y);
    }

    /**
     * @param  list<string>  $lines  "Name: message" lines from allocation (name is plain, message may contain colons).
     */
    public function formatAllocationResultDetailTableHtml(array $lines): HtmlString
    {
        if ($lines === []) {
            return new HtmlString('');
        }

        $rows = '';
        foreach ($lines as $line) {
            if (!str_contains($line, ':')) {
                $rows .= '<tr><td colspan="2" class="py-2 text-sm text-gray-600 dark:text-gray-400">' . e($line) . '</td></tr>';

                continue;
            }

            $parts = explode(':', $line, 2);
            $name = e(trim($parts[0]));
            $detail = e(trim($parts[1]));
            $rows .= '<tr class="border-b border-gray-100 last:border-0 dark:border-white/10">'
                . '<td class="py-2 pr-3 align-top font-medium text-gray-950 dark:text-white">' . $name . '</td>'
                . '<td class="py-2 align-top text-gray-600 dark:text-gray-400">' . $detail . '</td>'
                . '</tr>';
        }

        return new HtmlString(
            '<div class="overflow-x-auto rounded-lg border border-gray-200 bg-white text-left dark:border-white/10 dark:bg-gray-900/40">'
            . '<table class="w-full min-w-[16rem] text-sm">'
            . '<thead><tr class="border-b border-gray-200 bg-gray-50 dark:border-white/10 dark:bg-white/5">'
            . '<th scope="col" class="py-2 pl-3 pr-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Dependent</th>'
            . '<th scope="col" class="py-2 pr-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Result</th>'
            . '</tr></thead>'
            . '<tbody>' . $rows . '</tbody>'
            . '</table></div>'
        );
    }

    private function dependentAllocationDetailLine(Member $dependent, string $message): string
    {
        $name = $dependent->user?->name ?? 'Dependent';

        return $name . ': ' . $message;
    }

    public function contributionModalDescriptionForMemberAndPeriod(Member $member, int $month, int $year): string
    {
        $amount = (float) $member->monthly_contribution_amount;
        $balance = (float) $member->cash_balance;
        $label = $this->periodLabel($month, $year);
        $lateFee = $this->lateFeeForContributionPeriod($month, $year);
        $totalDebit = $amount + $lateFee;

        $fundLine = 'Master and member fund accounts are each credited SAR ' . number_format($amount, 2) . ' (contribution only).';
        if ($lateFee > 0.00001) {
            $fundLine .= ' A late fee of SAR ' . number_format($lateFee, 2)
                . ' is credited to the master cash account only (not the master fund).';
        }

        return sprintf(
            'Debits SAR %s from the member cash account (balance: SAR %s) for %s. %s',
            number_format($totalDebit, 2),
            number_format($balance, 2),
            $label,
            $fundLine,
        );
    }

    public function contributionModalDescriptionForMemberAndCycleKey(Member $member, ?string $cycleKey): string
    {
        if ($cycleKey === null || $cycleKey === '') {
            return '';
        }

        try {
            [$month, $year] = $this->parseContributionCycleKey($cycleKey);
        } catch (\InvalidArgumentException) {
            return '';
        }

        return $this->contributionModalDescriptionForMemberAndPeriod($member, $month, $year);
    }

    public function openPeriodContributionModalDescription(Member $member): string
    {
        [$m, $y] = $this->currentOpenPeriod();

        return $this->contributionModalDescriptionForMemberAndPeriod($member, $m, $y);
    }

    public function applyOpenPeriodContributionForMember(Member $member): string
    {
        [$m, $y] = $this->currentOpenPeriod();

        return $this->applyContributionForMemberForPeriod($member, $m, $y);
    }

    public function applyContributionForMemberForPeriod(Member $member, int $month, int $year): string
    {
        $member->unsetRelation('accounts');
        $member->load(['user', 'accounts']);
        $bucket = [];

        return $this->applyOne($member, $month, $year, $bucket);
    }

    // =========================================================================
    // Notifications (due by cycleDueEndAt for the period)
    // =========================================================================

    /**
     * Send "contribution due" notifications to all active members for a given period.
     * Returns the count of members notified.
     */
    public function sendDueNotifications(int $month, int $year): int
    {
        $deadline = $this->deadline($month, $year);
        $notified = 0;

        Member::active()->with('user')->each(function (Member $member) use ($month, $year, $deadline, &$notified) {
            // Skip if already contributed or has an active loan (exempt)
            $alreadyPaid = Contribution::where('member_id', $member->id)
                ->where('month', $month)
                ->where('year', $year)
                ->exists();

            if ($alreadyPaid || $member->isExemptFromContributions()) {
                return;
            }

            try {
                $member->user->notify(new ContributionDueNotification(
                    month: $month,
                    year: $year,
                    amount: (float) $member->monthly_contribution_amount,
                    deadline: $deadline,
                    cashBalance: (float) ($member->cashAccount()?->balance ?? 0),
                ));
                $notified++;
            } catch (\Throwable $e) {
                logger()->error('ContributionCycleService: notification failed', [
                    'member_id' => $member->id,
                    'error' => $e->getMessage(),
                ]);
            }
        });

        return $notified;
    }

    // =========================================================================
    // Apply contributions (on or before the period deadline / cycle due end)
    // =========================================================================

    /**
     * Apply contributions for all eligible active members for the given period.
     *
     * Returns an array with three keys:
     *   - applied   : Collection<Member>  — successfully processed
     *   - insufficient : Collection       — [member, balance, required]
     *   - skipped   : Collection<Member>  — already had a contribution for this period
     */
    public function applyContributions(int $month, int $year): array
    {
        $results = [
            'applied' => collect(),
            'insufficient' => collect(),
            'skipped' => collect(),
        ];

        Member::active()->with('user')->each(
            function (Member $member) use ($month, $year, &$results) {
                $this->applyOne($member, $month, $year, $results);
            }
        );

        return $results;
    }

    /**
     * Apply the contribution for a single member (used by both bulk cycle and manual re-try).
     */
    public function applyOne(Member $member, int $month, int $year, array &$results = []): string
    {
        // Already contributed this period?
        $existing = Contribution::where('member_id', $member->id)
            ->where('month', $month)
            ->where('year', $year)
            ->exists();

        if ($existing) {
            $results['skipped'][] = $member;
            return 'already_contributed';
        }

        // Members with an active/approved loan are exempt from contributions
        if ($member->isExemptFromContributions()) {
            $results['skipped'][] = $member;
            return 'exempt';
        }

        $amount = (float) $member->monthly_contribution_amount;
        $deadline = $this->deadline($month, $year);
        $days = $this->lateFees->daysPastDue($deadline, now());
        $lateFee = $this->lateFees->contributionLateFeeForDays($days);
        $required = $amount + $lateFee;
        $cashAccount = Account::where('type', Account::TYPE_MEMBER_CASH)
            ->where('member_id', $member->id)
            ->first();

        if (!$cashAccount || (float) $cashAccount->balance < $required) {
            $results['insufficient'][] = [
                'member' => $member,
                'balance' => (float) ($cashAccount?->balance ?? 0),
                'required' => $required,
            ];
            return 'insufficient';
        }

        try {
            DB::transaction(function () use ($member, $month, $year, $amount, $lateFee, $days) {
                // ContributionObserver posts cash debit (cash_account) + fund credits in one flow.
                $contribution = Contribution::create([
                    'member_id' => $member->id,
                    'amount' => $amount,
                    'month' => $month,
                    'year' => $year,
                    'paid_at' => now(),
                    'payment_method' => 'cash_account',
                    'is_late' => $days >= 1,
                    'late_fee_amount' => $lateFee > 0 ? $lateFee : null,
                ]);

                // 3. Member late stats: ContributionObserver recomputes from contributions after save.

                // 4. Send account statement to the member
                try {
                    $freshCash = Account::where('type', Account::TYPE_MEMBER_CASH)
                        ->where('member_id', $member->id)
                        ->first();

                    $member->user->notify(new ContributionAppliedNotification(
                        contribution: $contribution,
                        cashBalance: (float) ($freshCash?->balance ?? 0),
                    ));
                } catch (\Throwable $e) {
                    logger()->error('ContributionCycleService: statement notification failed', [
                        'member_id' => $member->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            });
        } catch (UniqueConstraintViolationException | ValidationException) {
            $results['skipped'][] = $member;

            return 'already_contributed';
        }

        $results['applied'][] = $member;
        return 'applied';
    }

    // =========================================================================
    // Summary helpers (for UI)
    // =========================================================================

    /**
     * Returns a collection of period summaries for the admin dashboard.
     * Each row: period_label, month, year, total_members, applied, late, total_amount, deadline
     */
    public function periodSummaries(int $limit = 12): Collection
    {
        return Contribution::selectRaw(
            'month, year, COUNT(*) as total_count,
                 SUM(amount) as total_amount,
                 SUM(is_late) as late_count'
        )
            ->groupBy('year', 'month')
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->limit($limit)
            ->get()
            ->map(function ($row) {
                $deadline = $this->deadline((int) $row->month, (int) $row->year);
                return [
                    'period_label' => $this->periodLabel((int) $row->month, (int) $row->year),
                    'month' => (int) $row->month,
                    'year' => (int) $row->year,
                    'total_count' => (int) $row->total_count,
                    'total_amount' => (float) $row->total_amount,
                    'late_count' => (int) $row->late_count,
                    'deadline' => $deadline->format('d M Y'),
                ];
            });
    }
}
