<?php

namespace App\Http\Controllers;

use App\Models\MonthlyStatement;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class StatementPdfController extends Controller
{
    public function download(MonthlyStatement $statement): Response
    {
        $member = auth()->user()?->member;

        abort_if(
            ! $member || $statement->member_id !== $member->id,
            403,
            'You do not have access to this statement.'
        );

        $pdf = Pdf::loadView('pdf.monthly-statement', compact('statement'));

        return $pdf->download("statement-{$statement->period}.pdf");
    }
}
