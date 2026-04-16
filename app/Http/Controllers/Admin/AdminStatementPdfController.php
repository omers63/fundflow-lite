<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MonthlyStatement;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class AdminStatementPdfController extends Controller
{
    public function __invoke(MonthlyStatement $statement): Response
    {
        $statement->load('member.user');
        $cfg = Setting::statementPdfConfig();
        $pdf = Pdf::loadView('pdf.monthly-statement', compact('statement', 'cfg'));

        return $pdf->download("statement-{$statement->member->member_number}-{$statement->period}.pdf");
    }
}
