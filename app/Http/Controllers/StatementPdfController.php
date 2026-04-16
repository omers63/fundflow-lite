<?php

namespace App\Http\Controllers;

use App\Models\MonthlyStatement;
use App\Models\Setting; // kept for statementPdfConfig()
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class StatementPdfController extends Controller
{
    public function download(MonthlyStatement $statement): Response
    {
        $member = auth()->user()?->member;

        abort_if(
            !$member || (int) $statement->member_id !== (int) $member->id,
            403,
            'You do not have access to this statement.'
        );

        $statement->load('member.user');

        $cfg = Setting::statementPdfConfig();

        $pdf = Pdf::loadView('pdf.monthly-statement', compact('statement', 'cfg'));

        return $pdf->download("statement-{$statement->period}.pdf");
    }
}
