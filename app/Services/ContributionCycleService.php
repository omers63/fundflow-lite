<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Contribution;
use App\Models\Member;
use App\Notifications\ContributionAppliedNotification;
use App\Notifications\ContributionDueNotification;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ContributionCycleService
{
    public function __construct(protected AccountingService $accounting) {}

    // =========================================================================
    // Deadline helpers
    // =========================================================================

    /**
     * Returns the contribution deadline: the 5th of the month following the cycle month.
     * Example: June 2026 → 5 July 2026 23:59:59
     */
    public function deadline(int $month, int $year): Carbon
    {
        return Carbon::create($year, $month, 1)
            ->addMonthNoOverflow()
            ->day(5)
            ->endOfDay();
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

    // =========================================================================
    // Notifications (1st of following month)
    // =========================================================================

    /**
     * Send "contribution due" notifications to all active members for a given period.
     * Returns the count of members notified.
     */
    public function sendDueNotifications(int $month, int $year): int
    {
        $deadline  = $this->deadline($month, $year);
        $notified  = 0;

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
                    month:        $month,
                    year:         $year,
                    amount:       (float) $member->monthly_contribution_amount,
                    deadline:     $deadline,
                    cashBalance:  (float) ($member->cashAccount()?->balance ?? 0),
                ));
                $notified++;
            } catch (\Throwable $e) {
                logger()->error('ContributionCycleService: notification failed', [
                    'member_id' => $member->id,
                    'error'     => $e->getMessage(),
                ]);
            }
        });

        return $notified;
    }

    // =========================================================================
    // Apply contributions (on or before 5th of following month)
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
        $isLate  = $this->isLate($month, $year);
        $results = [
            'applied'       => collect(),
            'insufficient'  => collect(),
            'skipped'       => collect(),
        ];

        Member::active()->with('user')->each(
            function (Member $member) use ($month, $year, $isLate, &$results) {
                $this->applyOne($member, $month, $year, $isLate, $results);
            }
        );

        return $results;
    }

    /**
     * Apply the contribution for a single member (used by both bulk cycle and manual re-try).
     */
    public function applyOne(Member $member, int $month, int $year, ?bool $isLate = null, array &$results = []): string
    {
        if ($isLate === null) {
            $isLate = $this->isLate($month, $year);
        }

        // Already contributed this period?
        $existing = Contribution::where('member_id', $member->id)
            ->where('month', $month)
            ->where('year', $year)
            ->exists();

        if ($existing) {
            $results['skipped'][] = $member;
            return 'skipped';
        }

        // Members with an active/approved loan are exempt from contributions
        if ($member->isExemptFromContributions()) {
            $results['skipped'][] = $member;
            return 'skipped';
        }

        $amount      = (float) $member->monthly_contribution_amount;
        $cashAccount = Account::where('type', Account::TYPE_MEMBER_CASH)
            ->where('member_id', $member->id)
            ->first();

        if (! $cashAccount || (float) $cashAccount->balance < $amount) {
            $results['insufficient'][] = [
                'member'   => $member,
                'balance'  => (float) ($cashAccount?->balance ?? 0),
                'required' => $amount,
            ];
            return 'insufficient';
        }

        DB::transaction(function () use ($member, $month, $year, $amount, $isLate) {
            // 1. Debit the member's cash account
            $this->accounting->debitCashForContribution($member, $amount, $month, $year);

            // 2. Create the Contribution record (ContributionObserver will credit fund accounts)
            $contribution = Contribution::create([
                'member_id'      => $member->id,
                'amount'         => $amount,
                'month'          => $month,
                'year'           => $year,
                'paid_at'        => now(),
                'payment_method' => 'cash_account',
                'is_late'        => $isLate,
            ]);

            // 3. Maintain late statistics on the member
            if ($isLate) {
                $member->increment('late_contributions_count');
                $member->increment('late_contributions_amount', $amount);
            }

            // 4. Send account statement to the member
            try {
                $freshCash = Account::where('type', Account::TYPE_MEMBER_CASH)
                    ->where('member_id', $member->id)
                    ->first();

                $member->user->notify(new ContributionAppliedNotification(
                    contribution: $contribution,
                    cashBalance:  (float) ($freshCash?->balance ?? 0),
                ));
            } catch (\Throwable $e) {
                logger()->error('ContributionCycleService: statement notification failed', [
                    'member_id' => $member->id,
                    'error'     => $e->getMessage(),
                ]);
            }
        });

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
                    'month'        => (int) $row->month,
                    'year'         => (int) $row->year,
                    'total_count'  => (int) $row->total_count,
                    'total_amount' => (float) $row->total_amount,
                    'late_count'   => (int) $row->late_count,
                    'deadline'     => $deadline->format('d M Y'),
                ];
            });
    }
}
