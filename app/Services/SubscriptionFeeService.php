<?php

namespace App\Services;

use App\Models\Member;
use App\Models\MemberSubscriptionFee;
use App\Models\Setting;
use Illuminate\Support\Carbon;

class SubscriptionFeeService
{
    public function __construct(private readonly AccountingService $accounting) {}

    // =========================================================================
    // Charge a single member
    // =========================================================================

    /**
     * Post an annual subscription fee for a member for the given calendar year.
     *
     * @throws \InvalidArgumentException  When amount ≤ 0.
     * @throws \Illuminate\Database\UniqueConstraintViolationException  When already charged for year.
     */
    public function chargeMember(Member $member, int $year, float $amount, string $notes = ''): MemberSubscriptionFee
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Subscription fee amount must be greater than zero.');
        }

        // Guard against duplicate (member_id, year) — the DB unique constraint will catch it
        // but we throw a friendlier message first.
        $existing = MemberSubscriptionFee::withTrashed()
            ->where('member_id', $member->id)
            ->where('year', $year)
            ->first();

        if ($existing !== null) {
            if ($existing->trashed()) {
                // Allow re-use of the trashed slot by restoring it.
                $existing->restore();
                $existing->update([
                    'amount'  => $amount,
                    'paid_at' => now(),
                    'notes'   => $notes ?: null,
                    'posted_by' => auth()->id() ?? 1,
                ]);
                $this->accounting->postSubscriptionFeeToMasterCash($existing->fresh());

                return $existing->fresh();
            }

            throw new \InvalidArgumentException(
                __('Subscription already recorded for :year', ['year' => $year])
            );
        }

        $fee = MemberSubscriptionFee::create([
            'member_id'  => $member->id,
            'year'       => $year,
            'amount'     => $amount,
            'paid_at'    => now(),
            'notes'      => $notes ?: null,
            'posted_by'  => auth()->id() ?? 1,
        ]);

        $this->accounting->postSubscriptionFeeToMasterCash($fee->fresh());

        return $fee->fresh();
    }

    // =========================================================================
    // Anniversary auto-charge (called from scheduler)
    // =========================================================================

    /**
     * Charge the configured annual subscription fee to every active member whose
     * join-date anniversary falls on $today, and who has not been charged yet this year.
     *
     * Returns the number of members charged.
     */
    public function chargeAnniversaryFees(Carbon $today): int
    {
        $amount = Setting::annualSubscriptionFee();
        if ($amount <= 0) {
            return 0; // Feature disabled — no fee configured.
        }

        $year  = $today->year;
        $month = $today->month;
        $day   = $today->day;

        // All active members whose join anniversary falls on today.
        $members = Member::active()
            ->whereNotNull('joined_at')
            ->whereMonth('joined_at', $month)
            ->whereDay('joined_at', $day)
            ->get();

        $charged = 0;

        foreach ($members as $member) {
            // Skip if already charged for this year.
            $exists = MemberSubscriptionFee::where('member_id', $member->id)
                ->where('year', $year)
                ->exists();

            if ($exists) {
                continue;
            }

            try {
                $this->chargeMember($member, $year, $amount);
                $charged++;
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $charged;
    }
}
