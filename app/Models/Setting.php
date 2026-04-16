<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    use SoftDeletes;

    protected $fillable = ['key', 'value', 'label', 'group'];

    protected static function booted(): void
    {
        static::deleted(function (Setting $setting): void {
            Cache::forget("setting:{$setting->key}");
        });

        static::restored(function (Setting $setting): void {
            Cache::forget("setting:{$setting->key}");
        });
    }

    /** Get a setting value with an optional default. */
    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::remember("setting:{$key}", 3600, function () use ($key, $default) {
            $setting = static::where('key', $key)->first();

            return $setting ? $setting->value : $default;
        });
    }

    /** Set a setting value and flush its cache. */
    public static function set(string $key, mixed $value): void
    {
        $existing = static::withTrashed()->where('key', $key)->first();

        if ($existing !== null) {
            if ($existing->trashed()) {
                $existing->restore();
            }
            $existing->update(['value' => $value]);
        } else {
            static::create(['key' => $key, 'value' => $value]);
        }

        Cache::forget("setting:{$key}");
    }

    /** Typed helpers for loan settings. */
    public static function loanSettlementThreshold(): float
    {
        return (float) static::get('loan.settlement_threshold_pct', 0.16);
    }

    public static function loanMinFundBalance(): float
    {
        return (float) static::get('loan.min_fund_balance', 6000);
    }

    public static function loanEligibilityMonths(): int
    {
        return (int) static::get('loan.eligibility_months', 12);
    }

    public static function loanMaxBorrowMultiplier(): float
    {
        return (float) static::get('loan.max_borrow_multiplier', 2);
    }

    public static function loanDefaultGraceCycles(): int
    {
        return (int) static::get('loan.default_grace_cycles', 2);
    }

    /**
     * Maximum membership applications (all statuses) allowed from the public apply flow.
     * Stored under historical key `membership.max_pending_public`. 0 means no limit.
     */
    public static function maxPublicApplications(): int
    {
        return max(0, (int) static::get('membership.max_pending_public', 0));
    }

    public static function publicApplicationCapEnabled(): bool
    {
        return static::maxPublicApplications() > 0;
    }

    /** SAR; 0 means no fee on public apply. */
    public static function membershipApplicationFee(): float
    {
        return max(0.0, (float) static::get('membership.application_fee_amount', 0));
    }

    /** Shown on /apply when fee &gt; 0 (plain text, line breaks preserved). */
    public static function membershipApplicationFeeBankInstructions(): string
    {
        return (string) static::get('membership.application_fee_bank_instructions', '');
    }

    /**
     * Day of month when each contribution/repayment cycle starts (1–28).
     * The cycle for calendar month M runs from this day in M until the day before the same numbered day in M+1
     * (due date is the last day of that window, end of day). Default 6 → e.g. June cycle: 6 Jun–5 Jul.
     */
    public static function contributionCycleStartDay(): int
    {
        $d = (int) static::get('contribution.cycle_start_day', 6);

        return max(1, min(28, $d));
    }

    /** Trailing consecutive closed-cycle misses required to trigger delinquency (with total rule). */
    public static function delinquencyConsecutiveMissThreshold(): int
    {
        return max(1, (int) static::get('delinquency.consecutive_miss_threshold', 3));
    }

    /** Rolling-window total misses (see {@see delinquencyTotalMissLookbackMonths()}) to trigger delinquency. */
    public static function delinquencyTotalMissThreshold(): int
    {
        return max(1, (int) static::get('delinquency.total_miss_threshold', 15));
    }

    /** Months included in the rolling total-miss count (spread-out misses). */
    public static function delinquencyTotalMissLookbackMonths(): int
    {
        return max(1, min(240, (int) static::get('delinquency.total_miss_lookback_months', 60)));
    }

    /** @param  int  $minDays  One of 1, 10, 20, 30 */
    public static function lateFeeContributionTier(int $minDays): float
    {
        return max(0.0, (float) static::get("late_fee.contribution_day_{$minDays}", 0));
    }

    /** @param  int  $minDays  One of 1, 10, 20, 30 */
    public static function lateFeeRepaymentTier(int $minDays): float
    {
        return max(0.0, (float) static::get("late_fee.repayment_day_{$minDays}", 0));
    }
}
