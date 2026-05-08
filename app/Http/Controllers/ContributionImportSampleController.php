<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\StreamedResponse;

class ContributionImportSampleController extends Controller
{
    public function __invoke(): StreamedResponse
    {
        $filename = 'contributions-import-mixed-sample.csv';

        $headers = [
            'member name',
            'month',
            'year',
            'amount',
            'paid_at',
            'guarantor',
            'check#',
        ];

        $rows = [
            ['John Kamau', '1', '2026', '500', '2026-01-05 09:15:00', '', 'DEP-1001'],
            ['John Kamau', '2', '2026', '-12000', '2026-02-06', 'Mary Njeri', 'CHK-2301'],
            ['John Kamau', '3', '2026', '3000', '2026-03-08 12:30', '', 'REC-2302'],
            ['John Kamau', '4', '2026', '3000', '2026-04-07', '', 'REC-2303'],
            ['Amina Yusuf', 'May', '2026', '1000', '2026-05-10', '', 'DEP-1005'],
            ['Amina Yusuf', '6', '2026', '-8000', '2026-06-12', 'Peter Mwangi', 'CHK-2304'],
            ['Amina Yusuf', '7', '2026', '2000', '2026-07-05', '', 'REC-2305'],
            ['Amina Yusuf', '8', '2026', '2000', '2026-08-06', '', 'REC-2306'],
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
