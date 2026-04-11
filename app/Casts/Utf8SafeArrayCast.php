<?php

namespace App\Casts;

use App\Support\Utf8Sanitizer;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use JsonException;

/**
 * JSON array cast that scrubs invalid UTF-8 on read/write so Livewire and JsonResponse stay valid.
 *
 * @implements CastsAttributes<array<string, mixed>, array<string, mixed>|string|null>
 */
class Utf8SafeArrayCast implements CastsAttributes
{
    /**
     * @param  array<string, mixed>|string|null  $value
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_array($value)) {
            return Utf8Sanitizer::scrub($value);
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);

            return Utf8Sanitizer::scrub(is_array($decoded) ? $decoded : []);
        }

        return [];
    }

    /**
     * @param  array<string, mixed>|null  $value
     * @param  array<string, mixed>  $attributes
     * @throws JsonException
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        $arr = is_array($value) ? $value : [];
        $scrubbed = Utf8Sanitizer::scrub($arr);

        return json_encode($scrubbed, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
