<?php

namespace App\Support;

class UiNumber
{
    public static function compact(float|int|string|null $value, int $decimals = 1): string
    {
        $number = (float) ($value ?? 0);
        $abs = abs($number);

        if ($abs >= 1_000_000_000) {
            return self::trim((string) round($number / 1_000_000_000, $decimals)) . 'B';
        }

        if ($abs >= 1_000_000) {
            return self::trim((string) round($number / 1_000_000, $decimals)) . 'M';
        }

        if ($abs >= 1_000) {
            return self::trim((string) round($number / 1_000, $decimals)) . 'K';
        }

        return number_format($number, 0);
    }

    public static function sar(float|int|string|null $value, int $decimals = 1): string
    {
        return 'SAR ' . self::compact($value, $decimals);
    }

    private static function trim(string $value): string
    {
        return rtrim(rtrim($value, '0'), '.');
    }
}
