<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Contribution Receipt — {{ date('F', mktime(0,0,0,$contribution->month,1)) }} {{ $contribution->year }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1e293b; margin: 0; padding: 0; }
        .header { background: #059669; color: white; padding: 28px 32px; }
        .header h1 { margin: 0; font-size: 22px; font-weight: bold; letter-spacing: -0.02em; }
        .header p { margin: 6px 0 0; font-size: 12px; opacity: 0.85; }
        .content { padding: 32px; }
        .receipt-box { border: 2px solid #059669; border-radius: 8px; padding: 20px 24px; margin-bottom: 24px; }
        .receipt-title { font-size: 14px; font-weight: bold; text-transform: uppercase; color: #059669; letter-spacing: 0.05em; margin-bottom: 16px; }
        .row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #e2e8f0; }
        .row:last-child { border-bottom: none; }
        .label { color: #64748b; font-size: 11px; text-transform: uppercase; letter-spacing: 0.04em; }
        .value { font-weight: 600; color: #1e293b; }
        .amount-row { background: #f0fdf4; margin: 12px -24px -20px; padding: 16px 24px; border-top: 2px solid #059669; border-bottom-left-radius: 6px; border-bottom-right-radius: 6px; }
        .amount-label { font-size: 11px; text-transform: uppercase; letter-spacing: 0.04em; color: #059669; }
        .amount-value { font-size: 22px; font-weight: bold; color: #059669; }
        .badge-late { display: inline-block; background: #fef3c7; color: #92400e; padding: 2px 8px; border-radius: 12px; font-size: 10px; font-weight: bold; }
        .badge-ontime { display: inline-block; background: #dcfce7; color: #14532d; padding: 2px 8px; border-radius: 12px; font-size: 10px; font-weight: bold; }
        .footer { text-align: center; font-size: 10px; color: #94a3b8; padding: 20px 32px; border-top: 1px solid #e2e8f0; margin-top: 24px; }
        .watermark { text-align: center; color: #dcfce7; font-size: 48px; font-weight: bold; opacity: 0.4; margin: 8px 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th { background: #f8fafc; padding: 8px 12px; text-align: left; font-size: 10px; color: #64748b; text-transform: uppercase; letter-spacing: 0.04em; border-bottom: 1px solid #e2e8f0; }
        td { padding: 8px 12px; font-size: 11px; border-bottom: 1px solid #f1f5f9; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ app()->getLocale() === 'ar' ? 'فندفلو' : 'FundFlow' }} — Contribution Receipt</h1>
        <p>Official receipt for monthly fund contribution</p>
    </div>

    <div class="content">

        <div class="receipt-box">
            <div class="receipt-title">Receipt Details</div>

            <div class="row">
                <span class="label">Receipt Number</span>
                <span class="value">#{{ str_pad($contribution->id, 6, '0', STR_PAD_LEFT) }}</span>
            </div>
            <div class="row">
                <span class="label">Member</span>
                <span class="value">{{ $contribution->member->user->name }}</span>
            </div>
            <div class="row">
                <span class="label">Member Number</span>
                <span class="value">{{ $contribution->member->member_number }}</span>
            </div>
            <div class="row">
                <span class="label">Contribution Period</span>
                <span class="value">{{ date('F', mktime(0,0,0,$contribution->month,1)) }} {{ $contribution->year }}</span>
            </div>
            <div class="row">
                <span class="label">Payment Status</span>
                <span class="value">
                    @if($contribution->is_late)
                        <span class="badge-late">LATE</span>
                    @else
                        <span class="badge-ontime">ON TIME</span>
                    @endif
                </span>
            </div>
            @if($contribution->payment_method)
            <div class="row">
                <span class="label">Payment Method</span>
                <span class="value">{{ \App\Models\Contribution::paymentMethodLabel($contribution->payment_method) }}</span>
            </div>
            @endif
            @if($contribution->reference_number)
            <div class="row">
                <span class="label">Reference Number</span>
                <span class="value">{{ $contribution->reference_number }}</span>
            </div>
            @endif
            @if($contribution->late_fee_amount && (float) $contribution->late_fee_amount > 0)
            <div class="row">
                <span class="label">Late Fee</span>
                <span class="value" style="color: #b45309;">{{ __('SAR') }} {{ number_format((float) $contribution->late_fee_amount, 2) }}</span>
            </div>
            @endif
            <div class="row">
                <span class="label">Recorded At</span>
                <span class="value">{{ $contribution->created_at->format('d F Y, H:i') }}</span>
            </div>

            {{-- Amount highlight --}}
            <div class="amount-row">
                <div class="amount-label">Contribution Amount</div>
                <div class="amount-value">{{ __('SAR') }} {{ number_format((float) $contribution->amount, 2) }}</div>
            </div>
        </div>

        <div class="watermark">✓ PAID</div>

        <p style="font-size: 11px; color: #64748b; text-align: center; margin-top: 8px;">
            This receipt confirms that the above contribution has been recorded in the {{ app()->getLocale() === 'ar' ? 'فندفلو' : 'FundFlow' }} system.
            Please retain this document for your records.
        </p>

    </div>

    <div class="footer">
        <p>Generated on {{ now()->format('d F Y \a\t H:i') }} &nbsp;|&nbsp; {{ app()->getLocale() === 'ar' ? 'فندفلو' : 'FundFlow' }} Fund Management System</p>
        <p>This is a computer-generated document. No signature is required.</p>
    </div>
</body>
</html>
