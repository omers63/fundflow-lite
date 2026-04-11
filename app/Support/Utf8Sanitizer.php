<?php

namespace App\Support;

/**
 * Ensures strings and nested array data are valid UTF-8 for JSON, Livewire, and PDF output.
 */
final class Utf8Sanitizer
{
    public static function scrub(mixed $value): mixed
    {
        if (is_string($value)) {
            return self::scrubString($value);
        }

        if (is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) {
                $key = is_string($k) ? self::scrubString($k) : $k;
                $out[$key] = self::scrub($v);
            }

            return $out;
        }

        return $value;
    }

    public static function scrubString(string $value): string
    {
        if (mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        return mb_scrub($value, 'UTF-8');
    }
}
