<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\StreamedResponse;

class MemberImportSampleController extends Controller
{
    public function __invoke(): StreamedResponse
    {
        $filename = 'members-import-sample-20.csv';

        $headers = [
            'name',
            'email',
            'password',
            'phone',
            'joined_at',
            'status',
            'monthly_contribution_amount',
            'parent_member_number',
            'cash_balance',
            'fund_balance',
        ];

        $rows = [
            ['Ali Hassan', 'sample.member01@example.test', 'TempPass@01', '0501001001', '2025-01-10', 'active', '500', '', '1000.00', '0.00'],
            ['Noura Salem', 'sample.member02@example.test', '', '0501001002', '2025-02-15', 'active', '1000', '', '0.00', '250.00'],
            ['Fahad Omar', 'sample.member03@example.test', '', '', '2025-03-20', 'suspended', '1500', '', '250.00', '-100.00'],
            ['Huda Ibrahim', 'sample.member04@example.test', 'Secure#Pass4', '0501001004', '', 'active', '2000', '', '0.00', '0.00'],
            ['Yousef Majed', 'sample.member05@example.test', '', '0501001005', '2024-11-05', 'delinquent', '2500', '', '500.00', '120.75'],
            ['Maha Khalid', 'sample.member06@example.test', '', '', '2024-08-01', 'active', '3000', '', '0.00', '-50.00'],
            ['Sami Adnan', 'sample.member07@example.test', 'StrongPass#7', '0501001007', '2025-04-18', 'active', '500', '', '75.00', '0.00'],
            ['Layan Saeed', 'sample.member08@example.test', '', '0501001008', '2025-05-01', 'terminated', '1000', '', '0.00', '0.00'],
            ['Rami Nasser', 'sample.member09@example.test', '', '', '2024-12-12', 'active', '1500', '', '200.00', '315.25'],
            ['Dina Tariq', 'sample.member10@example.test', 'TempPass@10', '0501001010', '', 'active', '2000', '', '0.00', '0.00'],
            ['Kareem Adel', 'sample.member11@example.test', '', '0501001011', '2023-10-07', 'suspended', '2500', '', '900.00', '-300.00'],
            ['Rana Bilal', 'sample.member12@example.test', '', '', '2022-06-30', 'active', '3000', '', '0.00', '85.00'],
            ['Bassam Jamal', 'sample.member13@example.test', 'SafePass#13', '0501001013', '2025-01-25', 'delinquent', '500', '', '120.00', '0.00'],
            ['Reem Fawzi', 'sample.member14@example.test', '', '0501001014', '', 'active', '1000', '', '0.00', '-20.00'],
            ['Turki Yaser', 'sample.member15@example.test', '', '', '2025-03-03', 'active', '1500', '', '330.50', '0.00'],
            ['Mona Samir', 'sample.member16@example.test', 'TempPass@16', '0501001016', '2024-09-19', 'active', '2000', '', '0.00', '440.00'],
            ['Saif Rashed', 'sample.member17@example.test', '', '0501001017', '2023-02-11', 'terminated', '2500', '', '60.00', '-10.00'],
            ['Lina Wael', 'sample.member18@example.test', '', '', '', 'active', '3000', '', '0.00', '0.00'],
            ['Omar Hani', 'sample.member19@example.test', 'TempPass@19', '0501001019', '2025-06-08', 'suspended', '500', '', '15.00', '5.00'],
            ['Shahad Rami', 'sample.member20@example.test', '', '0501001020', '2024-04-22', 'active', '1000', '', '0.00', '-75.50'],
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
