<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\StreamedResponse;

class ContributionImportSampleController extends Controller
{
    public function __invoke(): StreamedResponse
    {
        $filename = 'contributions-import-sample-15.csv';

        $headers = [
            'member_id',
            'member_number',
            'national_id',
            'member_name',
            'month',
            'year',
            'amount',
            'paid_at',
            'reference_number',
            'notes',
            'is_late',
            'late_fee_amount',
            'payment_method',
        ];

        $rows = [
            ['101', '', '', '', '1', '2026', '500', '2026-01-05 09:15:00', 'DEP-1001', 'Cycle contribution', '0', '', 'cash_account'],
            ['', 'M-000102', '', '', 'February', '2026', '1000', '2026-02-06', 'DEP-1002', 'Paid by transfer', '0', '', 'bank_transfer'],
            ['', '', '', 'John Kamau', 'Mar', '2026', '1500', '2026-03-08 12:30', 'DEP-1003', 'Matched by member name', '0', '', 'cash'],
            ['', 'M-000104', '', '', '4', '2026', '2000', '2026-04-07', 'DEP-1004', 'Branch cash receipt', '0', '', 'cash'],
            ['105', '', '', '', 'May', '2026', '2500', '2026-05-10', 'DEP-1005', 'Late by policy', '1', '25', 'online'],
            ['', '', '', 'Amina Yusuf', '6', '2026', '3000', '2026-06-12', 'DEP-1006', 'Manual admin posting', '1', '', 'admin'],
            ['107', '', '', '', 'July', '2025', '500', '2025-07-05', 'DEP-0901', '', '0', '', 'cash_account'],
            ['', 'M-000108', '', '', '8', '2025', '1000', '2025-08-06', 'DEP-0902', '', '0', '', 'bank_transfer'],
            ['', '', '1100223344', '', 'September', '2025', '1500', '2025-09-07 10:00', 'DEP-0903', 'Matched by national ID', '0', '', 'online'],
            ['', 'M-000110', '', '', '10', '2025', '2000', '2025-10-09', 'DEP-0904', '', '1', '10', 'cash'],
            ['111', '', '', '', 'Nov', '2025', '2500', '2025-11-11', 'DEP-0905', 'Late fee explicitly set', '1', '30', 'admin'],
            ['', 'M-000112', '', '', '12', '2025', '3000', '2025-12-05', 'DEP-0906', '', '0', '', 'cash_account'],
            ['113', '', '', '', 'January', '2024', '500', '2024-01-05', 'DEP-0701', '', '0', '', 'bank_transfer'],
            ['', 'M-000114', '', '', '2', '2024', '1000', '2024-02-05', 'DEP-0702', 'Year backfill row', '0', '', 'admin'],
            ['', '', '', 'Peter Mwangi', 'March', '2024', '1500', '', 'DEP-0703', 'paid_at omitted -> defaults to now', '0', '', ''],
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
