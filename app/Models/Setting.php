<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['key', 'value', 'label', 'group'];

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
        static::updateOrCreate(['key' => $key], ['value' => $value]);
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
}
