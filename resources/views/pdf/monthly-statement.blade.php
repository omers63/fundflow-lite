@php
    $d     = $statement->details ?? [];
    $m     = $d['member_snapshot'] ?? [];
    $loan  = $d['active_loan'] ?? null;
    $txns  = $d['period_transactions'] ?? [];
    $insts = $d['period_installments'] ?? [];
    $contribs = $d['contributions'] ?? [];
    $overdue  = $d['overdue_installments'] ?? [];
    $lateContribCount = $m['late_contrib_count'] ?? ($statement->member->late_contributions_count ?? 0);
    $lateRepayCount   = $m['late_repay_count']   ?? ($statement->member->late_repayment_count ?? 0);
    $periodLateFees   = $d['period_late_fees']   ?? 0;

    $brandName = app()->getLocale() === 'ar' ? 'فندفلو' : 'FundFlow';

    $cfg ??= [
        'brand'             => $brandName,
        'tagline'           => 'Member Fund Management',
        'accent_color'      => '#059669',
        'footer_disclaimer' => 'This is a computer-generated statement. Confidential.',
        'signature_line'    => app()->getLocale() === 'ar' ? 'إدارة فندفلو' : 'FundFlow Administration',
        'include_txns'      => true,
        'include_loan'      => true,
        'include_compliance'=> true,
    ];

    $displayBrand = app()->getLocale() === 'ar'
        ? str_replace('FundFlow', 'فندفلو', (string) ($cfg['brand'] ?? $brandName))
        : (string) ($cfg['brand'] ?? $brandName);
    $displaySignatureLine = app()->getLocale() === 'ar'
        ? str_replace('FundFlow', 'فندفلو', (string) ($cfg['signature_line'] ?? 'إدارة فندفلو'))
        : (string) ($cfg['signature_line'] ?? 'FundFlow Administration');

    $accent     = $cfg['accent_color'];
    $accentDark = '#047857';   // slightly darker shade for borders
    $accentLite = '#ecfdf5';   // very light tint

    $cashOpen  = number_format((float)($d['cash_opening'] ?? $statement->opening_balance), 2);
    $cashClose = number_format((float)($d['cash_closing'] ?? $statement->closing_balance), 2);
    $fundOpen  = number_format((float)($d['fund_opening'] ?? 0), 2);
    $fundClose = number_format((float)($d['fund_closing'] ?? 0), 2);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>{{ $displayBrand }} — Statement {{ $statement->period_formatted }}</title>
<style>
    /* ── Reset ── */
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; color: #1e293b; background: #fff; }

    /* ── Page ── */
    @page { margin: 0mm; }

    /* ── Header ── */
    .page-header {
        background: {{ $accent }};
        color: #fff;
        padding: 22px 28px 18px;
    }
    .header-row { display: table; width: 100%; }
    .header-left { display: table-cell; vertical-align: middle; }
    .header-right { display: table-cell; vertical-align: middle; text-align: right; white-space: nowrap; }
    .brand { font-size: 22px; font-weight: bold; letter-spacing: -0.02em; }
    .tagline { font-size: 10px; opacity: 0.85; margin-top: 2px; text-transform: uppercase; letter-spacing: 0.06em; }
    .statement-label { font-size: 13px; font-weight: bold; opacity: 0.95; }
    .statement-period { font-size: 11px; opacity: 0.8; margin-top: 3px; }

    /* ── Sub-header bar ── */
    .subheader {
        background: {{ $accentDark }};
        color: #fff;
        padding: 8px 28px;
        font-size: 10px;
        display: table;
        width: 100%;
    }
    .sh-cell { display: table-cell; }
    .sh-right { text-align: right; }

    /* ── Body ── */
    .body { padding: 18px 28px 10px; }

    /* ── Section ── */
    .section { margin-bottom: 18px; }
    .section-title {
        font-size: 9px;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        font-weight: bold;
        color: {{ $accent }};
        border-bottom: 2px solid {{ $accent }};
        padding-bottom: 4px;
        margin-bottom: 10px;
    }

    /* ── KPI boxes ── */
    .kpi-row { display: table; width: 100%; border-collapse: separate; border-spacing: 6px 0; }
    .kpi-box {
        display: table-cell;
        background: {{ $accentLite }};
        border: 1px solid #a7f3d0;
        border-radius: 6px;
        padding: 10px 12px;
        text-align: center;
        vertical-align: top;
    }
    .kpi-label { font-size: 8px; text-transform: uppercase; letter-spacing: 0.06em; color: #64748b; }
    .kpi-value { font-size: 16px; font-weight: bold; color: {{ $accent }}; margin-top: 3px; }
    .kpi-sub   { font-size: 8px; color: #94a3b8; margin-top: 2px; }

    /* ── Two-column layout ── */
    .two-col { display: table; width: 100%; border-collapse: separate; border-spacing: 10px 0; }
    .col { display: table-cell; vertical-align: top; width: 50%; }

    /* ── Info table ── */
    .info-table { width: 100%; border-collapse: collapse; }
    .info-table td { padding: 5px 8px; font-size: 10px; border-bottom: 1px solid #f1f5f9; }
    .info-table .lbl { color: #64748b; font-size: 9px; text-transform: uppercase; width: 38%; }
    .info-table .val { font-weight: 600; color: #1e293b; }

    /* ── Data table ── */
    table.data { width: 100%; border-collapse: collapse; font-size: 10px; }
    table.data thead tr { background: {{ $accent }}; color: #fff; }
    table.data thead th { padding: 6px 10px; text-align: left; font-size: 9px; font-weight: bold; letter-spacing: 0.03em; }
    table.data tbody tr:nth-child(even) { background: #f8fafc; }
    table.data tbody td { padding: 6px 10px; border-bottom: 1px solid #f1f5f9; vertical-align: top; }
    .amount { text-align: right; font-variant-numeric: tabular-nums; }
    .badge-paid    { background: #dcfce7; color: #14532d; padding: 1px 6px; border-radius: 8px; font-size: 9px; font-weight: bold; }
    .badge-pending { background: #fef9c3; color: #713f12; padding: 1px 6px; border-radius: 8px; font-size: 9px; font-weight: bold; }
    .badge-overdue { background: #fee2e2; color: #7f1d1d; padding: 1px 6px; border-radius: 8px; font-size: 9px; font-weight: bold; }
    .badge-credit  { background: #dcfce7; color: #14532d; padding: 1px 5px; border-radius: 6px; font-size: 8px; font-weight: bold; }
    .badge-debit   { background: #fee2e2; color: #7f1d1d; padding: 1px 5px; border-radius: 6px; font-size: 8px; font-weight: bold; }

    /* ── Summary table ── */
    table.summary { width: 100%; border-collapse: collapse; font-size: 10px; }
    table.summary tr td { padding: 7px 12px; border-bottom: 1px solid #f1f5f9; }
    table.summary tr.highlight td { background: {{ $accentLite }}; }
    table.summary tr.total td { background: {{ $accentLite }}; font-weight: bold; border-top: 2px solid {{ $accent }}; font-size: 11px; }
    table.summary .amt { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }

    /* ── Loan progress bar ── */
    .progress-bg  { background: #e2e8f0; height: 8px; border-radius: 4px; overflow: hidden; margin-top: 4px; }
    .progress-fill { background: {{ $accent }}; height: 8px; border-radius: 4px; }

    /* ── Alert box ── */
    .alert { border-left: 4px solid #ef4444; background: #fef2f2; padding: 8px 12px; border-radius: 4px; margin-bottom: 10px; font-size: 10px; color: #7f1d1d; }
    .alert-ok { border-left: 4px solid {{ $accent }}; background: {{ $accentLite }}; padding: 8px 12px; border-radius: 4px; font-size: 10px; color: #14532d; }

    /* ── Compliance score ── */
    .score-row { display: table; width: 100%; }
    .score-cell { display: table-cell; vertical-align: middle; padding: 6px; }
    .score-num { font-size: 28px; font-weight: bold; }
    .score-good  { color: {{ $accent }}; }
    .score-warn  { color: #d97706; }
    .score-bad   { color: #dc2626; }

    /* ── Watermark ── */
    .watermark {
        position: fixed;
        top: 45%;
        left: 50%;
        transform: translate(-50%, -50%) rotate(-35deg);
        font-size: 90px;
        font-weight: bold;
        color: rgba(5,150,105,0.05);
        white-space: nowrap;
        pointer-events: none;
        z-index: -1;
    }

    /* ── Footer ── */
    .page-footer {
        border-top: 1px solid #e2e8f0;
        padding: 10px 28px;
        font-size: 8px;
        color: #94a3b8;
        display: table;
        width: 100%;
    }
    .pf-left  { display: table-cell; }
    .pf-right { display: table-cell; text-align: right; }
</style>
</head>
<body>

<div class="watermark">STATEMENT</div>

{{-- ── PAGE HEADER ─────────────────────────────────────────────────────── --}}
<div class="page-header">
    <div class="header-row">
        <div class="header-left">
            <div class="brand">{{ $displayBrand }}</div>
            <div class="tagline">{{ $cfg['tagline'] }}</div>
        </div>
        <div class="header-right">
            <div class="statement-label">Monthly Account Statement</div>
            <div class="statement-period">Period: {{ $statement->period_formatted }}</div>
        </div>
    </div>
</div>

{{-- ── SUB-HEADER ───────────────────────────────────────────────────────── --}}
<div class="subheader">
    <span class="sh-cell">
        Member: <strong>{{ $m['name'] ?? $statement->member->user->name }}</strong>
        &nbsp;|&nbsp;
        No. <strong>{{ $m['member_number'] ?? $statement->member->member_number }}</strong>
        &nbsp;|&nbsp;
        Status: <strong>{{ ucfirst($m['status'] ?? $statement->member->status) }}</strong>
    </span>
    <span class="sh-cell sh-right">
        Generated: {{ $statement->generated_at?->format('d F Y') ?? now()->format('d F Y') }}
    </span>
</div>

{{-- ── BODY ─────────────────────────────────────────────────────────────── --}}
<div class="body">

    {{-- ── KPI SUMMARY BOXES ─────────────────────────────────────────────── --}}
    <div class="section">
        <div class="section-title">Financial Overview</div>
        <div class="kpi-row">
            <div class="kpi-box">
                <div class="kpi-label">Opening Balance</div>
                <div class="kpi-value">{{ __('SAR') }} {{ number_format((float)$statement->opening_balance, 2) }}</div>
                <div class="kpi-sub">Start of {{ $statement->period_formatted }}</div>
            </div>
            <div class="kpi-box">
                <div class="kpi-label">Contributions</div>
                <div class="kpi-value" style="color:#0284c7;">{{ __('SAR') }} {{ number_format((float)$statement->total_contributions, 2) }}</div>
                <div class="kpi-sub">Credits this period</div>
            </div>
            <div class="kpi-box">
                <div class="kpi-label">Repayments</div>
                <div class="kpi-value" style="color:#dc2626;">{{ __('SAR') }} {{ number_format((float)$statement->total_repayments, 2) }}</div>
                <div class="kpi-sub">Debits this period</div>
            </div>
            <div class="kpi-box">
                <div class="kpi-label">Closing Balance</div>
                <div class="kpi-value">{{ __('SAR') }} {{ number_format((float)$statement->closing_balance, 2) }}</div>
                <div class="kpi-sub">End of {{ $statement->period_formatted }}</div>
            </div>
        </div>
    </div>

    {{-- ── MEMBER + ACCOUNT DETAILS ─────────────────────────────────────── --}}
    <div class="section">
        <div class="section-title">Member &amp; Account Details</div>
        <div class="two-col">
            <div class="col">
                <table class="info-table">
                    <tr><td class="lbl">Full Name</td><td class="val">{{ $m['name'] ?? $statement->member->user->name }}</td></tr>
                    <tr><td class="lbl">Member No.</td><td class="val">{{ $m['member_number'] ?? $statement->member->member_number }}</td></tr>
                    <tr><td class="lbl">Email</td><td class="val">{{ $m['email'] ?? $statement->member->user->email }}</td></tr>
                    <tr><td class="lbl">Phone</td><td class="val" dir="ltr" style="unicode-bidi:isolate;">{{ \App\Support\PhoneDisplay::plain($m['phone'] ?? $statement->member->user->phone ?? null) }}</td></tr>
                    <tr><td class="lbl">Member Since</td><td class="val">{{ isset($m['joined_at']) ? \Carbon\Carbon::parse($m['joined_at'])->format('d M Y') : ($statement->member->joined_at?->format('d M Y') ?? '—') }}</td></tr>
                </table>
            </div>
            <div class="col">
                <table class="info-table">
                    <tr><td class="lbl">Cash Balance</td><td class="val" style="color:{{ $accent }};">{{ __('SAR') }} {{ $cashClose }}</td></tr>
                    <tr><td class="lbl">Fund Balance</td><td class="val" style="color:#6d28d9;">{{ __('SAR') }} {{ $fundClose }}</td></tr>
                    <tr><td class="lbl">Monthly Contribution</td><td class="val">{{ __('SAR') }} {{ number_format($m['monthly_contrib'] ?? $statement->member->monthly_contribution_amount) }}</td></tr>
                    <tr><td class="lbl">Cash (Opening)</td><td class="val">{{ __('SAR') }} {{ $cashOpen }}</td></tr>
                    <tr><td class="lbl">Fund (Opening)</td><td class="val">{{ __('SAR') }} {{ $fundOpen }}</td></tr>
                </table>
            </div>
        </div>
    </div>

    {{-- ── PERIOD SUMMARY TABLE ─────────────────────────────────────────── --}}
    <div class="section">
        <div class="section-title">Period Summary</div>
        <table class="summary">
            <tr>
                <td style="width:60%;">Opening Balance (brought forward)</td>
                <td class="amt">{{ __('SAR') }} {{ number_format((float)$statement->opening_balance, 2) }}</td>
            </tr>
            @foreach($contribs as $c)
            <tr class="highlight">
                <td>+ Contribution ({{ $c['paid_at'] ?? 'this period' }}) {{ $c['is_late'] ? '<span class="badge-overdue">LATE</span>' : '' }}</td>
                <td class="amt">{{ __('SAR') }} {{ number_format($c['amount'], 2) }}</td>
            </tr>
            @endforeach
            @if(empty($contribs) && (float)$statement->total_contributions > 0)
            <tr class="highlight">
                <td>+ Contributions this period</td>
                <td class="amt">{{ __('SAR') }} {{ number_format((float)$statement->total_contributions, 2) }}</td>
            </tr>
            @endif
            @foreach($insts as $i)
            <tr>
                <td>− Loan Repayment #{{ $i['installment_number'] }} (due {{ $i['due_date'] }}, paid {{ $i['paid_at'] }}){{ (float)$i['late_fee'] > 0 ? ' + late fee SAR '.number_format($i['late_fee'], 2) : '' }}</td>
                <td class="amt">{{ __('SAR') }} {{ number_format($i['amount'], 2) }}</td>
            </tr>
            @endforeach
            @if(empty($insts) && (float)$statement->total_repayments > 0)
            <tr>
                <td>− Loan Repayments this period</td>
                <td class="amt">{{ __('SAR') }} {{ number_format((float)$statement->total_repayments, 2) }}</td>
            </tr>
            @endif
            @if($periodLateFees > 0)
            <tr>
                <td style="color:#dc2626;">− Late Fees (period)</td>
                <td class="amt" style="color:#dc2626;">{{ __('SAR') }} {{ number_format($periodLateFees, 2) }}</td>
            </tr>
            @endif
            <tr class="total">
                <td><strong>Closing Balance</strong></td>
                <td class="amt"><strong>{{ __('SAR') }} {{ number_format((float)$statement->closing_balance, 2) }}</strong></td>
            </tr>
        </table>
    </div>

    {{-- ── ACCOUNT TRANSACTIONS ─────────────────────────────────────────── --}}
    @if($cfg['include_txns'])
    <div class="section">
        <div class="section-title">Account Transactions — {{ $statement->period_formatted }}</div>
        @if(count($txns) > 0)
        <table class="data">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Description</th>
                    <th>Account</th>
                    <th>Type</th>
                    <th class="amount">Amount (SAR)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($txns as $tx)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($tx['date'])->format('d M Y') }}</td>
                    <td>{{ $tx['description'] }}</td>
                    <td>
                        @if($tx['account_type'] === 'member_cash')
                            <span style="color:#0284c7;">Cash</span>
                        @elseif($tx['account_type'] === 'member_fund')
                            <span style="color:#6d28d9;">Fund</span>
                        @else
                            {{ ucfirst(str_replace('_', ' ', $tx['account_type'])) }}
                        @endif
                    </td>
                    <td>
                        <span class="badge-{{ $tx['type'] }}">{{ strtoupper($tx['type']) }}</span>
                    </td>
                    <td class="amount">{{ number_format($tx['amount'], 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @else
        <div class="alert-ok">No account transactions recorded for this period.</div>
        @endif
    </div>
    @endif

    {{-- ── LOAN STANDING ────────────────────────────────────────────────── --}}
    @if($cfg['include_loan'])
    <div class="section">
        <div class="section-title">Loan Standing</div>
        @if($loan)
        @php
            $loanTotal = $loan['installments_total'] ?? 1;
            $loanPaid  = $loan['installments_paid'] ?? 0;
            $loanPct   = $loanTotal > 0 ? (int)round($loanPaid / $loanTotal * 100) : 0;
        @endphp
        <div class="two-col">
            <div class="col">
                <table class="info-table">
                    <tr><td class="lbl">Loan ID</td><td class="val">#{{ $loan['id'] }}</td></tr>
                    <tr><td class="lbl">Status</td><td class="val">{{ ucfirst($loan['status']) }}</td></tr>
                    <tr><td class="lbl">Approved Amount</td><td class="val">{{ __('SAR') }} {{ number_format($loan['amount_approved'], 2) }}</td></tr>
                    <tr><td class="lbl">Remaining</td><td class="val" style="color:#dc2626;">{{ __('SAR') }} {{ number_format($loan['remaining_amount'], 2) }}</td></tr>
                    <tr><td class="lbl">Tier</td><td class="val">{{ $loan['tier'] ?? '—' }}</td></tr>
                </table>
            </div>
            <div class="col">
                <table class="info-table">
                    <tr><td class="lbl">Disbursed</td><td class="val">{{ $loan['disbursed_at'] ?? '—' }}</td></tr>
                    <tr><td class="lbl">Installments Paid</td><td class="val">{{ $loanPaid }} / {{ $loanTotal }}</td></tr>
                    <tr><td class="lbl">Remaining</td><td class="val">{{ $loan['installments_pending'] ?? 0 }}</td></tr>
                    <tr><td class="lbl">Next Due</td><td class="val">{{ $loan['next_due'] ?? '—' }}</td></tr>
                </table>
                <div style="margin-top: 8px;">
                    <div style="font-size:9px; color:#64748b; margin-bottom:2px;">Repayment progress: {{ $loanPct }}%</div>
                    <div class="progress-bg">
                        <div class="progress-fill" style="width:{{ $loanPct }}%;"></div>
                    </div>
                </div>
            </div>
        </div>

        @if(count($overdue) > 0)
        <div class="alert" style="margin-top:10px;">
            <strong>⚠ Overdue Installments ({{ count($overdue) }}):</strong>
            @foreach($overdue as $ov)
                Installment #{{ $ov['installment_number'] }} due {{ $ov['due_date'] }}: {{ __('SAR') }} {{ number_format($ov['amount'], 2) }}
                @if($ov['late_fee'] > 0) + {{ __('SAR') }} {{ number_format($ov['late_fee'], 2) }} late fee @endif.
            @endforeach
        </div>
        @endif
        @else
        <div class="alert-ok">No active loan at this time.</div>
        @endif
    </div>
    @endif

    {{-- ── COMPLIANCE SNAPSHOT ──────────────────────────────────────────── --}}
    @if($cfg['include_compliance'])
    <div class="section">
        <div class="section-title">Compliance Snapshot</div>
        @php
            $totalCnt = $statement->member->contributions()->count();
            $onTime   = max(0, $totalCnt - $lateContribCount);
            $pct      = $totalCnt > 0 ? (int)round($onTime / $totalCnt * 100) : 100;
            $scoreClass = $pct >= 90 ? 'score-good' : ($pct >= 70 ? 'score-warn' : 'score-bad');
            $scoreLabel = $pct >= 90 ? 'Excellent' : ($pct >= 70 ? 'Fair' : 'Needs Attention');
        @endphp
        <div class="two-col">
            <div class="col">
                <div class="score-row">
                    <div class="score-cell" style="width:80px; text-align:center;">
                        <div class="score-num {{ $scoreClass }}">{{ $pct }}%</div>
                        <div style="font-size:9px; color:#64748b;">{{ $scoreLabel }}</div>
                    </div>
                    <div class="score-cell">
                        <div style="font-size:9px; color:#64748b;">Compliance Score</div>
                        <div style="font-size:10px; margin-top:4px;">Based on {{ $totalCnt }} contribution records</div>
                    </div>
                </div>
            </div>
            <div class="col">
                <table class="info-table">
                    <tr>
                        <td class="lbl">Late Contributions</td>
                        <td class="val" style="{{ $lateContribCount > 0 ? 'color:#d97706;' : 'color:'.e($accent).';' }}">{{ $lateContribCount }}</td>
                    </tr>
                    <tr>
                        <td class="lbl">Late Repayments</td>
                        <td class="val" style="{{ $lateRepayCount > 0 ? 'color:#dc2626;' : 'color:'.e($accent).';' }}">{{ $lateRepayCount }}</td>
                    </tr>
                    <tr>
                        <td class="lbl">Current Status</td>
                        <td class="val">{{ ucfirst($m['status'] ?? $statement->member->status) }}</td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    @endif

    {{-- ── SIGNATURE BLOCK ──────────────────────────────────────────────── --}}
    <div style="margin-top: 24px; display: table; width: 100%;">
        <div style="display: table-cell; width: 50%; padding-right: 20px;">
            <div style="border-top: 1px solid #cbd5e1; padding-top: 6px; font-size: 9px; color: #94a3b8;">
                Member Acknowledgment
            </div>
        </div>
        <div style="display: table-cell; width: 50%; padding-left: 20px;">
            <div style="border-top: 1px solid #cbd5e1; padding-top: 6px; font-size: 9px; color: #94a3b8; text-align: right;">
                {{ $displaySignatureLine }}
            </div>
        </div>
    </div>

</div>

{{-- ── PAGE FOOTER ──────────────────────────────────────────────────────── --}}
<div class="page-footer">
    <div class="pf-left">
        Statement ID: STMT-{{ strtoupper($statement->member->member_number) }}-{{ str_replace('-', '', $statement->period) }}
        &nbsp;|&nbsp;
        Generated: {{ $statement->generated_at?->format('d F Y H:i') ?? now()->format('d F Y H:i') }}
    </div>
    <div class="pf-right">{{ $cfg['footer_disclaimer'] }}</div>
</div>

</body>
</html>
