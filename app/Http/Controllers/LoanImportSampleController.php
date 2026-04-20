<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\StreamedResponse;

class LoanImportSampleController extends Controller
{
    public function __invoke(): StreamedResponse
    {
        $filename = 'loans-import-sample-10.csv';

        $headers = [
            'member_number',
            'member_email',
            'national_id',
            'loan_status',
            'amount_requested',
            'amount_approved',
            'member_portion',
            'master_portion',
            'installments_count',
            'paid_installments_count',
            'total_amount_repaid',
            'loan_tier_number',
            'fund_tier_number',
            'is_emergency',
            'settlement_threshold',
            'purpose',
            'applied_at',
            'approved_at',
            'disbursed_at',
            'settled_at',
        ];

        $rows = [
            ['M-000101', '', '', 'pending', '12000', '', '', '', '12', '', '', '2', '', '0', '', 'Pending queue import', '2026-01-05', '', '', ''],
            ['', 'member102@example.test', '', 'pending', '', '15000', '', '', '', '', '', '3', '', '0', '', 'Pending using approved amount as requested', '2026-01-10', '', '', ''],
            ['M-000103', '', '', 'approved', '18000', '18000', '', '', '18', '', '', '3', '2', '0', '0.6', 'Approved not yet disbursed', '2026-01-12', '2026-01-14', '', ''],
            ['M-000104', '', '', 'active', '10000', '10000', '2500', '7500', '10', '0', '', '2', '1', '0', '', 'Active disbursed with no repayments yet', '2025-10-01', '2025-10-03', '2025-10-05', ''],
            ['', 'member105@example.test', '', 'active', '', '16000', '4000', '12000', '16', '5', '', '3', '2', '0', '', 'Active with paid installments count', '2025-08-02', '2025-08-04', '2025-08-06', ''],
            ['M-000106', '', '', 'active', '', '22000', '7000', '15000', '22', '6', '7200', '4', '3', '0', '', 'Active with explicit total repaid', '2025-06-03', '2025-06-06', '2025-06-08', ''],
            ['', '', '1002334455', 'completed', '14000', '14000', '5000', '9000', '14', '', '', '3', '2', '0', '0.6', 'Historical completed loan identified by national ID', '2024-05-01', '2024-05-03', '2024-05-05', '2025-07-05'],
            ['', 'member108@example.test', '', 'completed', '', '9000', '2000', '7000', '9', '', '9000', '2', '1', '0', '', 'Completed with explicit total repaid', '2024-08-01', '2024-08-03', '2024-08-05', '2025-04-05'],
            ['M-000109', '', '', 'early_settled', '', '13000', '3000', '10000', '13', '', '7800', '3', '2', '0', '0.6', 'Early-settled historical loan', '2024-01-15', '2024-01-17', '2024-01-20', '2024-09-20'],
            ['M-000110', '', '', 'approved', '', '6000', '', '', '', '', '', '', '', '1', '', 'Emergency approved loan', '2026-02-01', '2026-02-02', '', ''],
        ];

        return response()->streamDownload(function () use ($headers, $rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }
}
