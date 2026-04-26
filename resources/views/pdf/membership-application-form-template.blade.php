<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('Membership application form') }} — {{ __('template') }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11pt; color: #111; line-height: 1.45; }
        h1 { font-size: 16pt; margin-bottom: 0.25em; }
        .muted { font-size: 9.5pt; color: #444; margin-bottom: 1.25em; }
        .label { font-weight: bold; margin-top: 0.85em; margin-bottom: 0.2em; }
        .line { border-bottom: 1px solid #333; min-height: 1.35em; margin-bottom: 0.15em; }
        .block { min-height: 4.5em; border: 1px solid #999; margin-top: 0.25em; padding: 0.35em; }
        .sign { margin-top: 2em; display: table; width: 100%; }
        .sign-col { display: table-cell; width: 48%; vertical-align: bottom; }
        .sign-line { border-top: 1px solid #333; margin-top: 2.5em; padding-top: 0.2em; font-size: 9.5pt; }
    </style>
</head>
<body>
    <h1>{{ __('Membership application form') }}</h1>
    <p class="muted">{{ __('This is a blank template. Complete each section in clear handwriting or typed text, then sign and date where indicated. Upload a scan or photo of the completed, signed form where your fund requests it.') }}</p>

    <div class="label">{{ __('Full name') }}</div>
    <div class="line"></div>

    <div class="label">{{ __('Email') }}</div>
    <div class="line"></div>

    <div class="label">{{ __('Mobile phone') }}</div>
    <div class="line"></div>

    <div class="label">{{ __('National ID / ID number') }}</div>
    <div class="line"></div>

    <div class="label">{{ __('Date of birth') }}</div>
    <div class="line"></div>

    <div class="label">{{ __('Address') }}</div>
    <div class="block"></div>

    <div class="label">{{ __('City') }}</div>
    <div class="line"></div>

    <div class="label">{{ __('Employment / occupation (optional)') }}</div>
    <div class="line"></div>

    <div class="label">{{ __('Next of kin — name and phone') }}</div>
    <div class="block"></div>

    <div class="sign">
        <div class="sign-col">
            <div class="sign-line">{{ __('Applicant signature') }}</div>
        </div>
        <div class="sign-col" style="width: 4%;"></div>
        <div class="sign-col">
            <div class="sign-line">{{ __('Date') }}</div>
        </div>
    </div>
</body>
</html>
