<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Account;
use App\Models\AccountTransaction;
use Carbon\Carbon;
use Filament\Widgets\Widget;

class AccountDetailWidget extends Widget
{
    protected string $view = 'filament.admin.widgets.account-detail';

    protected int|string|array $columnSpan = 'full';

    public ?int $accountId = null;

    public function getData(): array
    {
        if (!$this->accountId) {
            return [];
        }

        $record = Account::with('member.user')->find($this->accountId);
        if (!$record) {
            return [];
        }

        $balance = (float) $record->balance;
        $isLoan = $record->type === Account::TYPE_LOAN;
        $outstanding = $isLoan ? max(0, -$balance) : 0;

        $since = Carbon::now()->subDays(30);
        $stats30 = AccountTransaction::where('account_id', $record->id)
            ->where('transacted_at', '>=', $since)
            ->selectRaw("
                SUM(CASE WHEN entry_type = 'credit' THEN amount ELSE 0 END) as credits,
                SUM(CASE WHEN entry_type = 'debit'  THEN amount ELSE 0 END) as debits,
                COUNT(*) as tx_count
            ")
            ->first();

        $allTime = AccountTransaction::where('account_id', $record->id)
            ->selectRaw('COUNT(*) as total_tx')
            ->first();

        $recent = AccountTransaction::where('account_id', $record->id)
            ->orderByDesc('transacted_at')
            ->limit(5)
            ->get();

        return [
            'record' => $record,
            'balance' => $balance,
            'outstanding' => $outstanding,
            'isLoan' => $isLoan,
            'credits30' => (float) ($stats30->credits ?? 0),
            'debits30' => (float) ($stats30->debits ?? 0),
            'txCount30' => (int) ($stats30->tx_count ?? 0),
            'totalTx' => (int) ($allTime->total_tx ?? 0),
            'recent' => $recent,
        ];
    }
}
