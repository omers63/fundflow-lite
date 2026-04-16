<?php

namespace App\Services;

use App\Models\Contribution;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\Member;
use App\Models\Setting;
use Carbon\Carbon;

/**
 * Computes missed contribution / loan repayment obligations per closed cycle month
 * and derives trailing consecutive miss streak and rolling total miss count.
 */
class MemberDelinquencyEvaluator
{
    /** @var array<string, bool> */
    protected array $exemptFromContributionCache = [];

    public function __construct(
        protected ContributionCycleService $cycles,
    ) {}

    /**
     * @return array{
     *   trailing_consecutive: int,
     *   rolling_total: int,
     *   last_closed_month: int|null,
     *   last_closed_year: int|null,
     * }
     */
    public function evaluate(Member $member): array
    {
        $this->exemptFromContributionCache = [];

        $now = now();
        [$lastM, $lastY] = $this->lastClosedPeriodMonthYear($now);

        $joined = $member->joined_at instanceof Carbon
            ? $member->joined_at->copy()->startOfMonth()
            : Carbon::parse($member->joined_at)->startOfMonth();

        if ($this->periodKey($lastY, $lastM) < $this->periodKey((int) $joined->year, (int) $joined->month)) {
            return [
                'trailing_consecutive' => 0,
                'rolling_total' => 0,
                'last_closed_month' => null,
                'last_closed_year' => null,
            ];
        }

        $lookback = Setting::delinquencyTotalMissLookbackMonths();

        $rollingTotal = 0;
        $cursor = Carbon::create($lastY, $lastM, 1)->startOfMonth();
        for ($i = 0; $i < $lookback; $i++) {
            $m = (int) $cursor->month;
            $y = (int) $cursor->year;
            if ($cursor->lt($joined)) {
                break;
            }
            if ($this->periodHasMiss($member, $m, $y)) {
                $rollingTotal++;
            }
            $cursor->subMonthNoOverflow();
        }

        $trailing = 0;
        $cursor = Carbon::create($lastY, $lastM, 1)->startOfMonth();
        for ($i = 0; $i < 240; $i++) {
            $m = (int) $cursor->month;
            $y = (int) $cursor->year;
            if ($cursor->lt($joined)) {
                break;
            }
            if (!$this->periodHasMiss($member, $m, $y)) {
                break;
            }
            $trailing++;
            $cursor->subMonthNoOverflow();
        }

        return [
            'trailing_consecutive' => $trailing,
            'rolling_total' => $rollingTotal,
            'last_closed_month' => $lastM,
            'last_closed_year' => $lastY,
        ];
    }

    public function shouldSuspend(int $trailingConsecutive, int $rollingTotal): bool
    {
        $c = Setting::delinquencyConsecutiveMissThreshold();
        $t = Setting::delinquencyTotalMissThreshold();

        return $trailingConsecutive >= $c || $rollingTotal >= $t;
    }

    /**
     * True when a contribution was expected for this month/year and no contribution row exists.
     * Call {@see clearCaches()} when switching members.
     */
    public function hasContributionGap(Member $member, int $month, int $year): bool
    {
        return $this->contributionMiss($member, $month, $year);
    }

    public function clearCaches(): void
    {
        $this->exemptFromContributionCache = [];
    }

    /**
     * Most recent calendar month whose cycle deadline has passed (obligation can be judged as missed).
     *
     * @return array{0: int, 1: int} month, year
     */
    public function lastClosedPeriodMonthYear(Carbon $now): array
    {
        $cursor = $now->copy()->startOfMonth();
        for ($i = 0; $i < 240; $i++) {
            $m = (int) $cursor->month;
            $y = (int) $cursor->year;
            if ($now->greaterThan($this->cycles->deadline($m, $y))) {
                return [$m, $y];
            }
            $cursor->subMonthNoOverflow();
        }

        $fallback = $now->copy()->subMonthNoOverflow();

        return [(int) $fallback->month, (int) $fallback->year];
    }

    protected function periodHasMiss(Member $member, int $month, int $year): bool
    {
        return $this->contributionMiss($member, $month, $year)
            || $this->repaymentMiss($member, $month, $year);
    }

    protected function contributionMiss(Member $member, int $month, int $year): bool
    {
        $periodStart = Carbon::create($year, $month, 1)->startOfMonth();
        $joined = $member->joined_at instanceof Carbon
            ? $member->joined_at->copy()->startOfMonth()
            : Carbon::parse($member->joined_at)->startOfMonth();

        if ($periodStart->lt($joined)) {
            return false;
        }

        if ((int) $member->monthly_contribution_amount <= 0) {
            return false;
        }

        if ($this->isExemptFromContributionsInMonth($member, $month, $year)) {
            return false;
        }

        if (!Contribution::query()
            ->where('member_id', $member->id)
            ->where('month', $month)
            ->where('year', $year)
            ->exists()) {
            return true;
        }

        return false;
    }

    protected function repaymentMiss(Member $member, int $month, int $year): bool
    {
        $deadline = $this->cycles->deadline($month, $year);
        if (now()->lessThanOrEqualTo($deadline)) {
            return false;
        }

        $hasUnpaid = LoanInstallment::query()
            ->whereHas('loan', fn($q) => $q->where('member_id', $member->id)->where('status', 'active'))
            ->whereYear('due_date', $year)
            ->whereMonth('due_date', $month)
            ->whereIn('status', ['pending', 'overdue'])
            ->exists();

        return $hasUnpaid;
    }

    protected function isExemptFromContributionsInMonth(Member $member, int $month, int $year): bool
    {
        $k = "{$year}-{$month}";
        if (array_key_exists($k, $this->exemptFromContributionCache)) {
            return $this->exemptFromContributionCache[$k];
        }

        $end = Carbon::create($year, $month, 1)->endOfMonth();

        $v = Loan::query()
            ->where('member_id', $member->id)
            ->whereIn('status', ['approved', 'active'])
            ->where('approved_at', '<=', $end)
            ->exists();

        return $this->exemptFromContributionCache[$k] = $v;
    }

    protected function periodKey(int $year, int $month): int
    {
        return $year * 12 + $month;
    }
}
