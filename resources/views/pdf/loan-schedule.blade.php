<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Loan Repayment Schedule — Loan #{{ $loan->id }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1e293b; margin: 0; padding: 0; }
        .header { background: #0f172a; color: white; padding: 24px 32px; }
        .header h1 { margin: 0; font-size: 20px; font-weight: bold; }
        .header p { margin: 4px 0 0; font-size: 11px; opacity: 0.75; }
        .content { padding: 24px 32px; }
        .section-title { font-size: 10px; text-transform: uppercase; letter-spacing: 0.06em; color: #64748b; font-weight: bold; margin: 20px 0 8px; }
        .summary-grid { display: table; width: 100%; margin-bottom: 20px; }
        .summary-cell { display: table-cell; width: 25%; padding: 12px 16px; background: #f8fafc; border: 1px solid #e2e8f0; vertical-align: top; }
        .summary-label { font-size: 9px; text-transform: uppercase; letter-spacing: 0.05em; color: #94a3b8; }
        .summary-value { font-size: 14px; font-weight: bold; color: #1e293b; margin-top: 3px; }
        .progress-bar-bg { width: 100%; height: 8px; background: #e2e8f0; border-radius: 4px; margin: 12px 0 4px; overflow: hidden; }
        .progress-bar-fill { height: 8px; background: #059669; border-radius: 4px; }
        .progress-label { font-size: 10px; color: #64748b; }
        table.schedule { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.schedule th { background: #0f172a; color: white; padding: 8px 10px; text-align: left; font-size: 10px; letter-spacing: 0.03em; }
        table.schedule td { padding: 8px 10px; border-bottom: 1px solid #f1f5f9; font-size: 11px; }
        table.schedule tr:nth-child(even) td { background: #f8fafc; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 9px; font-weight: bold; }
        .badge-paid { background: #dcfce7; color: #14532d; }
        .badge-pending { background: #fef9c3; color: #713f12; }
        .badge-overdue { background: #fee2e2; color: #7f1d1d; }
        .amount-right { text-align: right; }
        .total-row td { background: #f0fdf4 !important; font-weight: bold; border-top: 2px solid #059669; }
        .footer { text-align: center; font-size: 9px; color: #94a3b8; padding: 16px 32px; border-top: 1px solid #e2e8f0; margin-top: 16px; }
        .info-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .info-table td { padding: 7px 12px; border-bottom: 1px solid #f1f5f9; font-size: 11px; }
        .info-table .lbl { color: #64748b; font-size: 10px; text-transform: uppercase; width: 30%; }
        .info-table .val { font-weight: 600; }
    </style>
</head>
<body>

<div class="header">
    <h1>FundFlow — Loan Repayment Schedule</h1>
    <p>Loan #{{ $loan->id }} &nbsp;|&nbsp; Member: {{ $loan->member->user->name }} ({{ $loan->member->member_number }})</p>
</div>

<div class="content">

    {{-- Loan details --}}
    <div class="section-title">Loan Details</div>
    <table class="info-table">
        <tr>
            <td class="lbl">Loan Amount</td>
            <td class="val">SAR {{ number_format((float) $loan->amount_approved ?: $loan->amount_requested, 2) }}</td>
            <td class="lbl">Status</td>
            <td class="val">{{ ucfirst(str_replace('_', ' ', $loan->status)) }}</td>
        </tr>
        <tr>
            <td class="lbl">Tier</td>
            <td class="val">{{ $loan->loanTier?->label ?? '—' }}</td>
            <td class="lbl">Applied</td>
            <td class="val">{{ $loan->applied_at?->format('d M Y') ?? '—' }}</td>
        </tr>
        <tr>
            <td class="lbl">Total Installments</td>
            <td class="val">{{ $loan->installments->count() }}</td>
            <td class="lbl">Disbursed</td>
            <td class="val">{{ $loan->disbursed_at?->format('d M Y') ?? '—' }}</td>
        </tr>
        <tr>
            <td class="lbl">Monthly Installment</td>
            <td class="val">SAR {{ number_format((float) ($loan->loanTier?->min_monthly_installment ?? 0), 2) }}</td>
            <td class="lbl">Purpose</td>
            <td class="val">{{ $loan->purpose ?? '—' }}</td>
        </tr>
    </table>

    {{-- Progress summary --}}
    <div class="section-title">Repayment Progress</div>
    <div class="summary-grid">
        <div class="summary-cell">
            <div class="summary-label">Total Installments</div>
            <div class="summary-value">{{ $loan->installments->count() }}</div>
        </div>
        <div class="summary-cell">
            <div class="summary-label">Paid</div>
            <div class="summary-value" style="color: #059669;">{{ $paidCount }}</div>
        </div>
        <div class="summary-cell">
            <div class="summary-label">Remaining</div>
            <div class="summary-value" style="color: #b45309;">{{ $pendingCount }}</div>
        </div>
        <div class="summary-cell">
            <div class="summary-label">Amount Remaining</div>
            <div class="summary-value">SAR {{ number_format($remaining, 2) }}</div>
        </div>
    </div>

    @php
        $totalCount = $loan->installments->count();
        $pct = $totalCount > 0 ? round($paidCount / $totalCount * 100) : 0;
    @endphp
    <div class="progress-bar-bg">
        <div class="progress-bar-fill" style="width: {{ $pct }}%"></div>
    </div>
    <div class="progress-label">{{ $pct }}% repaid ({{ $paidCount }} of {{ $totalCount }} installments)</div>

    {{-- Installment schedule --}}
    <div class="section-title" style="margin-top: 24px;">Installment Schedule</div>
    <table class="schedule">
        <thead>
            <tr>
                <th>#</th>
                <th>Due Date</th>
                <th class="amount-right">Amount (SAR)</th>
                <th>Status</th>
                <th>Paid Date</th>
                <th class="amount-right">Late Fee (SAR)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($loan->installments as $inst)
            <tr>
                <td>{{ $inst->installment_number }}</td>
                <td>{{ $inst->due_date?->format('d M Y') ?? '—' }}</td>
                <td class="amount-right">{{ number_format((float) $inst->amount, 2) }}</td>
                <td>
                    <span class="badge badge-{{ $inst->status }}">{{ strtoupper($inst->status) }}</span>
                </td>
                <td>{{ $inst->paid_at?->format('d M Y') ?? '—' }}</td>
                <td class="amount-right">
                    @if((float) $inst->late_fee_amount > 0)
                        {{ number_format((float) $inst->late_fee_amount, 2) }}
                    @else
                        —
                    @endif
                </td>
            </tr>
            @endforeach
            <tr class="total-row">
                <td colspan="2">Total</td>
                <td class="amount-right">{{ number_format((float) $loan->installments->sum('amount'), 2) }}</td>
                <td></td>
                <td>Paid: SAR {{ number_format($totalPaid, 2) }}</td>
                <td class="amount-right">{{ number_format((float) $loan->installments->sum('late_fee_amount'), 2) }}</td>
            </tr>
        </tbody>
    </table>

</div>

<div class="footer">
    Generated on {{ now()->format('d F Y \a\t H:i') }} &nbsp;|&nbsp; FundFlow Fund Management System &nbsp;|&nbsp; This is a computer-generated document.
</div>

</body>
</html>
