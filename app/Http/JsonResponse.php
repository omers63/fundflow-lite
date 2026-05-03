<?php

namespace App\Http;

use Illuminate\Http\JsonResponse as BaseJsonResponse;
use Symfony\Component\HttpFoundation\JsonResponse as SymfonyJsonResponse;

/**
 * Ensures json_encode() accepts payloads that contain invalid UTF-8 (e.g. legacy DB text).
 * Livewire and Router::toResponse use {@see BaseJsonResponse} directly with encoding options 0,
 * which triggers {@see \InvalidArgumentException} without JSON_INVALID_UTF8_SUBSTITUTE.
 */
class JsonResponse extends BaseJsonResponse
{
    #[\Override]
    public function setData($data = []): static
    {
        if ($this->encodingOptions === 0) {
            $this->encodingOptions = SymfonyJsonResponse::DEFAULT_ENCODING_OPTIONS | \JSON_INVALID_UTF8_SUBSTITUTE;
        } else {
            $this->encodingOptions |= \JSON_INVALID_UTF8_SUBSTITUTE;
        }

        return parent::setData($data);
    }
}
