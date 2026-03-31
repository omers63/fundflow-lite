<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Monthly Statement — {{ $statement->period }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1e293b; margin: 0; padding: 0; }
        .header { background: #059669; color: white; padding: 24px; }
        .header h1 { margin: 0; font-size: 22px; font-weight: bold; }
        .header p { margin: 4px 0 0; font-size: 12px; opacity: 0.85; }
        .content { padding: 24px; }
        .section-title { font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; font-weight: bold; margin-bottom: 8px; margin-top: 20px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f1f5f9; text-align: left; padding: 8px 12px; font-size: 11px; color: #475569; }
        td { padding: 8px 12px; border-bottom: 1px solid #e2e8f0; font-size: 12px; }
        .amount { text-align: right; }
        .highlight { background: #ecfdf5; }
        .footer { text-align: center; font-size: 10px; color: #94a3b8; padding: 16px; border-top: 1px solid #e2e8f0; }
    </style>
</head>
<body>
    <div class="header">
        <h1>FundFlow — Monthly Statement</h1>
        <p>Period: {{ $statement->period_formatted }} &nbsp;|&nbsp; Member: {{ $statement->member->user->name }} ({{ $statement->member->member_number }})</p>
    </div>

    <div class="content">
        <div class="section-title">Statement Summary</div>
        <table>
            <thead>
                <tr>
                    <th>Description</th>
                    <th class="amount">Amount (SAR)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Opening Balance</td>
                    <td class="amount">﷼{{ number_format($statement->opening_balance, 2) }}</td>
                </tr>
                <tr class="highlight">
                    <td>+ Monthly Contributions</td>
                    <td class="amount">﷼{{ number_format($statement->total_contributions, 2) }}</td>
                </tr>
                <tr>
                    <td>− Loan Repayments</td>
                    <td class="amount">﷼{{ number_format($statement->total_repayments, 2) }}</td>
                </tr>
                <tr style="font-weight: bold; background: #e0f2fe;">
                    <td>Closing Balance</td>
                    <td class="amount">﷼{{ number_format($statement->closing_balance, 2) }}</td>
                </tr>
            </tbody>
        </table>

        <div class="section-title">Member Information</div>
        <table>
            <tbody>
                <tr><td style="width:40%;">Member Number</td><td>{{ $statement->member->member_number }}</td></tr>
                <tr><td>Full Name</td><td>{{ $statement->member->user->name }}</td></tr>
                <tr><td>Email</td><td>{{ $statement->member->user->email }}</td></tr>
                <tr><td>Member Since</td><td>{{ $statement->member->joined_at->format('d M Y') }}</td></tr>
                <tr><td>Statement Generated</td><td>{{ $statement->generated_at->format('d M Y H:i') }}</td></tr>
            </tbody>
        </table>
    </div>

    <div class="footer">
        This is a computer-generated statement. FundFlow — Confidential.
    </div>
</body>
</html>
