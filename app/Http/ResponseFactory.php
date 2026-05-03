<?php

namespace App\Http;

use Illuminate\Routing\ResponseFactory as BaseResponseFactory;
use Symfony\Component\HttpFoundation\JsonResponse as SymfonyJsonResponse;

/**
 * Default JsonResponse uses encoding options 0, which makes json_encode() fail on invalid UTF-8
 * (e.g. legacy DB text). Match Symfony’s safe default flags and substitute invalid bytes.
 */
class ResponseFactory extends BaseResponseFactory
{
    private const JSON_ENCODE = SymfonyJsonResponse::DEFAULT_ENCODING_OPTIONS | \JSON_INVALID_UTF8_SUBSTITUTE;

    /**
     * @param  array<string, mixed>  $headers
     */
    public function json($data = [], $status = 200, array $headers = [], $options = 0): \Illuminate\Http\JsonResponse
    {
        if ($options === 0) {
            $options = self::JSON_ENCODE;
        } else {
            $options |= \JSON_INVALID_UTF8_SUBSTITUTE;
        }

        return parent::json($data, $status, $headers, $options);
    }

    /**
     * @param  array<string, mixed>  $headers
     */
    public function jsonp($callback, $data = [], $status = 200, array $headers = [], $options = 0): \Illuminate\Http\JsonResponse
    {
        if ($options === 0) {
            $options = self::JSON_ENCODE;
        } else {
            $options |= \JSON_INVALID_UTF8_SUBSTITUTE;
        }

        return parent::jsonp($callback, $data, $status, $headers, $options);
    }
}
