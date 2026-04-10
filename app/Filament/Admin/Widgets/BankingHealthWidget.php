<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Account;
use App\Models\BankTransaction;
use App\Models\SmsTransaction;
use Carbon\Carbon;
use Filament\Widgets\Widget;

class BankingHealthWidget extends Widget
{
    protected static bool $isDiscovered = false;

    protected string $view = 'filament.admin.widgets.banking-health';

    protected int|string|array $columnSpan = 'full';

    public function getData(): array
    {
        $masterCash = (float) (Account::query()->where('slug', 'master_cash')->value('balance') ?? 0);
        $masterFund = (float) (Account::query()->where('slug', 'master_fund')->value('balance') ?? 0);

        $bankTotal = BankTransaction::count();
        $smsTotal = SmsTransaction::count();
        $total = $bankTotal + $smsTotal;

        $bankPosted = BankTransaction::query()->whereNotNull('posted_at')->count();
        $smsPosted = SmsTransaction::query()->whereNotNull('posted_at')->count();
        $postedTotal = $bankPosted + $smsPosted;

        $bankPending = max(0, $bankTotal - $bankPosted);
        $smsPending = max(0, $smsTotal - $smsPosted);

        $bankPendingAmount = (float) BankTransaction::query()
            ->whereNull('posted_at')
            ->sum('amount');

        $smsPendingAmount = (float) SmsTransaction::query()
            ->whereNull('posted_at')
            ->sum('amount');

        $postedRate = $total > 0 ? round(($postedTotal / $total) * 100, 1) : 0.0;
        $bankPostedRate = $bankTotal > 0 ? round(($bankPosted / $bankTotal) * 100, 1) : 0.0;
        $smsPostedRate = $smsTotal > 0 ? round(($smsPosted / $smsTotal) * 100, 1) : 0.0;

        $loanLinkedDebits = BankTransaction::query()
            ->where('transaction_type', 'debit')
            ->whereNotNull('posted_at')
            ->whereNotNull('loan_id')
            ->whereNotNull('loan_disbursement_id')
            ->count();

        $unlinkedDebits = BankTransaction::query()
            ->where('transaction_type', 'debit')
            ->whereNotNull('posted_at')
            ->where(function ($q) {
                $q->whereNull('loan_id')
                    ->orWhereNull('loan_disbursement_id');
            })
            ->count();

        $dailyPosted = collect(range(6, 0))->map(function (int $d) {
            $day = Carbon::now()->subDays($d);
            $bank = BankTransaction::query()
                ->whereDate('posted_at', $day->toDateString())
                ->count();
            $sms = SmsTransaction::query()
                ->whereDate('posted_at', $day->toDateString())
                ->count();

            return [
                'label' => $day->format('D'),
                'count' => $bank + $sms,
            ];
        })->toArray();

        return [
            'master_cash' => $masterCash,
            'master_fund' => $masterFund,
            'bank_total' => $bankTotal,
            'sms_total' => $smsTotal,
            'bank_pending' => $bankPending,
            'sms_pending' => $smsPending,
            'bank_pending_amount' => $bankPendingAmount,
            'sms_pending_amount' => $smsPendingAmount,
            'posted_rate' => $postedRate,
            'bank_posted_rate' => $bankPostedRate,
            'sms_posted_rate' => $smsPostedRate,
            'loan_linked_debits' => $loanLinkedDebits,
            'unlinked_debits' => $unlinkedDebits,
            'daily_posted' => $dailyPosted,
        ];
    }
}

