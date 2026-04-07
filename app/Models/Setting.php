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
}
