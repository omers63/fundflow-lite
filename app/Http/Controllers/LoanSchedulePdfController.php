<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class LoanSchedulePdfController extends Controller
{
    public function download(Loan $loan): Response
    {
        $member = auth()->user()?->member;

        abort_if(
            !$member || (int) $loan->member_id !== (int) $member->id,
            403,
            'You do not have access to this loan.'
        );

        $loan->load(['member.user', 'loanTier', 'installments' => fn($q) => $q->orderBy('installment_number')]);

        $paidCount    = $loan->installments->where('status', 'paid')->count();
        $pendingCount = $loan->installments->whereIn('status', ['pending', 'overdue'])->count();
        $totalPaid    = (float) $loan->installments->where('status', 'paid')->sum('amount');
        $remaining    = (float) $loan->installments->whereIn('status', ['pending', 'overdue'])->sum('amount');

        $pdf = Pdf::loadView('pdf.loan-schedule', compact(
            'loan', 'paidCount', 'pendingCount', 'totalPaid', 'remaining'
        ));

        return $pdf->download("loan-schedule-{$loan->id}.pdf");
    }
}
