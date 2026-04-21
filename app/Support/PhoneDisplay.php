<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

final class PhoneDisplay
{
    /**
     * Split a stored phone value for Saudi mobiles into a fixed +966 prefix and national digits.
     * Non-Saudi or unrecognized values are returned as a single LTR national string (prefix empty).
     *
     * @return array{prefix: string, national: string}|null null when blank
     */
    public static function splitForDisplay(?string $phone): ?array
    {
        $raw = trim((string) $phone);
        if ($raw === '' || $raw === '—') {
            return null;
        }

        $digits = preg_replace('/\D/', '', $raw) ?? '';

        if (str_starts_with($digits, '966')) {
            $after = ltrim(substr($digits, 3), '0');
            if (preg_match('/^(5\d{8})$/', $after, $m)) {
                return ['prefix' => '+966', 'national' => $m[1]];
            }
        }

        if (preg_match('/^0(5\d{8})$/', $digits, $m)) {
            return ['prefix' => '+966', 'national' => $m[1]];
        }

        if (preg_match('/^(5\d{8})$/', $digits)) {
            return ['prefix' => '+966', 'national' => $digits];
        }

        return ['prefix' => '', 'national' => $raw];
    }

    public static function plain(?string $phone, string $empty = '—'): string
    {
        $parts = self::splitForDisplay($phone);
        if ($parts === null) {
            return $empty;
        }

        if ($parts['prefix'] !== '') {
            return $parts['prefix'].$parts['national'];
        }

        return $parts['national'];
    }

    public static function toHtml(?string $phone, string $empty = '—'): HtmlString
    {
        return new HtmlString(Blade::render(
            '<x-phone-display :value="$value" :empty="$empty" />',
            ['value' => $phone, 'empty' => $empty],
        ));
    }
}
