<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Member;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class MembershipCertificateController extends Controller
{
    public function download(): Response
    {
        $member = Member::where('user_id', auth()->id())
            ->with(['user', 'accounts', 'contributions'])
            ->withSum(['accounts as cash_balance' => fn($q) => $q->where('type', Account::TYPE_MEMBER_CASH)], 'balance')
            ->withSum(['accounts as fund_balance' => fn($q) => $q->where('type', Account::TYPE_MEMBER_FUND)], 'balance')
            ->first();

        abort_if(!$member, 404, 'Member record not found.');

        $totalContributions = (float) $member->contributions()->sum('amount');
        $joinedMonths = $member->joined_at
            ? (int) $member->joined_at->diffInMonths(now())
            : 0;

        $pdf = Pdf::loadView('pdf.membership-certificate', compact('member', 'totalContributions', 'joinedMonths'));
        $pdf->setPaper('A4', 'portrait');

        return $pdf->download("membership-certificate-{$member->member_number}.pdf");
    }
}
