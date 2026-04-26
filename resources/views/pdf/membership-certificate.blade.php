<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <title>Membership Certificate — {{ $member->user->name }}</title>
    <style>
        @page { margin: 0; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1e293b; margin: 0; padding: 0; background: #ffffff; }
        .border-outer { border: 12px solid #059669; margin: 24px; min-height: 760px; position: relative; }
        .border-inner { border: 3px solid #a7f3d0; margin: 6px; padding: 40px 48px; }
        .header { text-align: center; margin-bottom: 32px; }
        .logo-line { font-size: 28px; font-weight: bold; color: #059669; letter-spacing: -0.03em; }
        .subtitle { font-size: 13px; color: #64748b; margin-top: 4px; text-transform: uppercase; letter-spacing: 0.1em; }
        .title-block { text-align: center; margin: 24px 0 32px; }
        .cert-title { font-size: 26px; font-weight: bold; color: #1e293b; letter-spacing: 0.02em; }
        .cert-subtitle { font-size: 13px; color: #64748b; margin-top: 6px; }
        .divider { border: none; border-top: 2px solid #059669; margin: 20px auto; width: 60px; }
        .member-name { text-align: center; font-size: 24px; font-weight: bold; color: #059669; margin: 24px 0 8px; }
        .member-number { text-align: center; font-size: 13px; color: #64748b; margin-bottom: 32px; }
        .details-table { width: 100%; border-collapse: collapse; margin: 24px 0; }
        .details-table td { padding: 10px 16px; border-bottom: 1px solid #e2e8f0; font-size: 12px; }
        .details-table .label { color: #64748b; font-size: 10px; text-transform: uppercase; letter-spacing: 0.05em; width: 40%; }
        .details-table .value { color: #1e293b; font-weight: 600; }
        .status-badge { display: inline-block; padding: 3px 12px; border-radius: 12px; font-size: 11px; font-weight: bold; }
        .status-active { background: #dcfce7; color: #14532d; }
        .status-other { background: #fee2e2; color: #7f1d1d; }
        .body-text { text-align: center; color: #475569; font-size: 12px; line-height: 1.7; margin: 24px 0; }
        .watermark { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%) rotate(-30deg); font-size: 80px; font-weight: bold; color: rgba(5, 150, 105, 0.06); white-space: nowrap; pointer-events: none; }
        .footer { text-align: center; margin-top: 40px; padding-top: 20px; border-top: 1px solid #e2e8f0; }
        .footer-issued { font-size: 10px; color: #94a3b8; }
        .signature-row { display: table; width: 100%; margin-top: 40px; }
        .sig-cell { display: table-cell; text-align: center; width: 50%; }
        .sig-line { border-top: 1px solid #cbd5e1; width: 160px; margin: 0 auto 6px; }
        .sig-label { font-size: 10px; color: #94a3b8; }
        .stats-row { display: table; width: 100%; margin: 20px 0; }
        .stat-cell { display: table-cell; text-align: center; padding: 12px; }
        .stat-val { font-size: 20px; font-weight: bold; color: #059669; }
        .stat-lbl { font-size: 10px; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; margin-top: 2px; }
    </style>
</head>
<body>
<div class="border-outer">
    <div class="border-inner">
        <div class="watermark">MEMBER</div>

        <div class="header">
            <div class="logo-line">{{ app()->getLocale() === 'ar' ? 'فندفلو' : 'FundFlow' }}</div>
            <div class="subtitle">{{ __('Member Fund Management') }}</div>
        </div>

        <div class="title-block">
            <div class="cert-title">{{ __('Certificate of Membership') }}</div>
            <div class="cert-subtitle">{{ __('This is to certify that the following individual is a registered member') }}</div>
        </div>

        <hr class="divider">

        <div class="member-name">{{ $member->user->name }}</div>
        <div class="member-number">{{ __('Member No.') }} {{ $member->member_number }}</div>

        <table class="details-table">
            <tr>
                <td class="label">{{ __('Membership Status') }}</td>
                <td class="value">
                    <span class="status-badge {{ $member->status === 'active' ? 'status-active' : 'status-other' }}">
                        {{ strtoupper($member->status) }}
                    </span>
                </td>
                <td class="label">{{ __('Email') }}</td>
                <td class="value">{{ $member->user->email }}</td>
            </tr>
            <tr>
                <td class="label">{{ __('Member Since') }}</td>
                <td class="value">{{ $member->joined_at?->format('d F Y') ?? '—' }}</td>
                <td class="label">{{ __('Phone') }}</td>
                <td class="value" dir="ltr" style="unicode-bidi:isolate;">{{ \App\Support\PhoneDisplay::plain($member->user->phone ?? null) }}</td>
            </tr>
            <tr>
                <td class="label">{{ __('Monthly Contribution') }}</td>
                <td class="value">{{ __('SAR') }} {{ number_format($member->monthly_contribution_amount) }}</td>
                <td class="label">{{ __('Tenure') }}</td>
                <td class="value">{{ $joinedMonths }} {{ $joinedMonths === 1 ? __('month') : __('months') }}</td>
            </tr>
            @if($member->parent)
            <tr>
                <td class="label">{{ __('Sponsored by') }}</td>
                <td class="value" colspan="3">{{ $member->parent->user->name }} ({{ $member->parent->member_number }})</td>
            </tr>
            @endif
        </table>

        <div class="stats-row">
            <div class="stat-cell">
                <div class="stat-val">{{ __('SAR') }} {{ number_format($member->fund_balance ?? 0) }}</div>
                <div class="stat-lbl">{{ __('Fund Balance') }}</div>
            </div>
            <div class="stat-cell">
                <div class="stat-val">{{ __('SAR') }} {{ number_format($totalContributions) }}</div>
                <div class="stat-lbl">{{ __('Total Contributed') }}</div>
            </div>
            <div class="stat-cell">
                <div class="stat-val">{{ __('SAR') }} {{ number_format($member->cash_balance ?? 0) }}</div>
                <div class="stat-lbl">{{ __('Cash Balance') }}</div>
            </div>
        </div>

        <p class="body-text">
            {{ __('This certificate confirms the membership of the above-named individual in the :brand Fund.', ['brand' => app()->getLocale() === 'ar' ? 'فندفلو' : 'FundFlow']) }}<br>
            {{ __('This document is valid as of the date of issue shown below.') }}
        </p>

        <div class="signature-row">
            <div class="sig-cell">
                <div class="sig-line"></div>
                <div class="sig-label">{{ __('Fund Administrator') }}</div>
            </div>
            <div class="sig-cell">
                <div class="sig-line"></div>
                <div class="sig-label">{{ __('Member Signature') }}</div>
            </div>
        </div>

        <div class="footer">
            <p class="footer-issued">
                {{ __('Issued') }}: {{ now()->format('d F Y') }} &nbsp;|&nbsp;
                Certificate ID: CERT-{{ strtoupper($member->member_number) }}-{{ now()->format('Ymd') }} &nbsp;|&nbsp;
                {{ __('This is a computer-generated document.') }}
            </p>
        </div>
    </div>
</div>
</body>
</html>
