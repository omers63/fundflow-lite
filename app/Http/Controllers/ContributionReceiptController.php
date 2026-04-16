<?php

namespace App\Http\Controllers;

use App\Models\Contribution;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class ContributionReceiptController extends Controller
{
    public function download(Contribution $contribution): Response
    {
        $member = auth()->user()?->member;

        abort_if(
            ! $member || $contribution->member_id !== $member->id,
            403,
            'You do not have access to this receipt.'
        );

        $contribution->load('member.user');

        $pdf = Pdf::loadView('pdf.contribution-receipt', compact('contribution'));

        $period = sprintf('%04d-%02d', $contribution->year, $contribution->month);

        return $pdf->download("contribution-receipt-{$period}.pdf");
    }
}
