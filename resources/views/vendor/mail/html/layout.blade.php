<!DOCTYPE html
    PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
@php
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
    $dir = $isRtl ? 'rtl' : 'ltr';
    $textAlign = $isRtl ? 'right' : 'left';
    /*
     * Blade often indents <table> lines from mail components. CommonMark treats 4+ spaces as
     * an indented code block, so raw HTML appears as visible text. Trim lines that start with HTML.
     */
    $normalizedSlot = collect(preg_split('/\r\n|\r|\n/', (string) $slot))->map(function (string $line): string {
        $trimmed = ltrim($line);

        return ($trimmed !== '' && str_starts_with($trimmed, '<')) ? $trimmed : $line;
    })->implode("\n");

    $renderedSlot = Illuminate\Mail\Markdown::parse($normalizedSlot);
    $renderedSubcopy = $subcopy ?? '';

    if ($isRtl) {
        // Laravel inlines `text-align: left;` from default mail theme styles.
        // Force right alignment for Arabic-rendered fragments.
        $renderedSlot = str_replace('text-align: left;', 'text-align: right;', $renderedSlot);
        $renderedSubcopy = str_replace('text-align: left;', 'text-align: right;', $renderedSubcopy);
    }
@endphp
<html xmlns="http://www.w3.org/1999/xhtml" lang="{{ str_replace('_', '-', $locale) }}" dir="{{ $dir }}">

<head>
    <title>{{ config('app.name') }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <style>
        @media only screen and (max-width: 600px) {
            .inner-body {
                width: 100% !important;
            }

            .footer {
                width: 100% !important;
            }
        }

        @media only screen and (max-width: 500px) {
            .button {
                width: 100% !important;
            }
        }

        /* Enforce RTL alignment for Arabic, even after Laravel inlines theme CSS. */
        html[dir="rtl"] h1,
        html[dir="rtl"] h2,
        html[dir="rtl"] h3,
        html[dir="rtl"] p,
        html[dir="rtl"] td,
        html[dir="rtl"] th,
        html[dir="rtl"] li,
        html[dir="rtl"] .subcopy p,
        html[dir="rtl"] .footer p,
        html[dir="rtl"] .content-cell {
            text-align: right !important;
            direction: rtl !important;
        }
    </style>
    {!! $head ?? '' !!}
</head>

<body dir="{{ $dir }}" style="direction: {{ $dir }}; text-align: {{ $textAlign }};">

    <table class="wrapper" width="100%" cellpadding="0" cellspacing="0" role="presentation">
        <tr>
            <td align="center">
                <table class="content" width="100%" cellpadding="0" cellspacing="0" role="presentation">
                    {!! $header ?? '' !!}

                    <!-- Email Body -->
                    <tr>
                        <td class="body" width="100%" cellpadding="0" cellspacing="0"
                            style="border: hidden !important;">
                            <table class="inner-body" align="center" width="570" cellpadding="0" cellspacing="0"
                                role="presentation">
                                <!-- Body content -->
                                <tr>
                                    <td class="content-cell" dir="{{ $dir }}"
                                        style="direction: {{ $dir }}; text-align: {{ $textAlign }};">
                                        {!! $renderedSlot !!}

                                        {!! $renderedSubcopy !!}
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {!! $footer ?? '' !!}
                </table>
            </td>
        </tr>
    </table>
</body>

</html>