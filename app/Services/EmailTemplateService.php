<?php

namespace App\Services;

use App\Models\Setting;

class EmailTemplateService
{
    public static function get(string $templateKey, string $field, string $locale, string $default): string
    {
        $locale = in_array($locale, ['ar', 'en'], true) ? $locale : 'en';

        return (string) Setting::get(
            "email_template.{$templateKey}.{$field}.{$locale}",
            $default
        );
    }

    public static function render(string $template, array $vars = []): string
    {
        foreach ($vars as $key => $value) {
            $template = str_replace(':' . $key, (string) $value, $template);
        }

        return trim($template);
    }

    /**
     * @return array<int, string>
     */
    public static function renderLines(string $template, array $vars = []): array
    {
        $rendered = self::render($template, $vars);

        return array_values(array_filter(array_map(
            static fn(string $line): string => trim($line),
            preg_split('/\r\n|\r|\n/', $rendered) ?: []
        )));
    }
}

