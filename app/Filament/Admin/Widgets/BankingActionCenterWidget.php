<?php

namespace App\Filament\Admin\Widgets;

use App\Models\BankImportSession;
use App\Models\BankTransaction;
use App\Models\SmsImportSession;
use Filament\Widgets\Widget;

class BankingActionCenterWidget extends Widget
{
    protected static bool $isDiscovered = false;

    protected string $view = 'filament.admin.widgets.banking-action-center';

    protected int|string|array $columnSpan = 'full';

    public function getData(): array
    {
        $failedBankSessions = BankImportSession::query()->where('status', 'failed')->count();
        $failedSmsSessions = SmsImportSession::query()->where('status', 'failed')->count();

        $bankDuplicateQueue = BankTransaction::query()
            ->where('is_duplicate', true)
            ->whereNull('posted_at')
            ->count();

        $debitNeedsLink = BankTransaction::query()
            ->where('transaction_type', 'debit')
            ->whereNotNull('posted_at')
            ->where(function ($q) {
                $q->whereNull('loan_id')
                    ->orWhereNull('loan_disbursement_id');
            })
            ->count();

        $highValueUnposted = BankTransaction::query()
            ->with(['bank', 'member.user'])
            ->whereNull('posted_at')
            ->where('amount', '>=', 5000)
            ->orderByDesc('amount')
            ->limit(6)
            ->get();

        $latestLinkedDebits = BankTransaction::query()
            ->with(['member.user', 'loanDisbursement'])
            ->where('transaction_type', 'debit')
            ->whereNotNull('posted_at')
            ->whereNotNull('loan_disbursement_id')
            ->latest('posted_at')
            ->limit(6)
            ->get();

        return [
            'failed_bank_sessions' => $failedBankSessions,
            'failed_sms_sessions' => $failedSmsSessions,
            'bank_duplicate_queue' => $bankDuplicateQueue,
            'debit_needs_link' => $debitNeedsLink,
            'high_value_unposted' => $highValueUnposted,
            'latest_linked_debits' => $latestLinkedDebits,
        ];
    }
}

