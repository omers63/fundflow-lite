<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Account;
use App\Models\AccountTransaction;
use App\Models\Member;
use Carbon\Carbon;
use Filament\Widgets\Widget;

class AccountSummaryWidget extends Widget
{
    protected static ?int $sort = 1;

    protected string $view = 'filament.admin.widgets.account-summary';

    protected int|string|array $columnSpan = 'full';

    public function getData(): array
    {
        $masterCash = (float) (Account::where('slug', 'master_cash')->value('balance') ?? 0);
        $masterFund = (float) (Account::where('slug', 'master_fund')->value('balance') ?? 0);

        $memberCashStats = Account::where('type', Account::TYPE_MEMBER_CASH)
            ->selectRaw('COUNT(*) as cnt, SUM(balance) as total')
            ->first();

        $memberFundStats = Account::where('type', Account::TYPE_MEMBER_FUND)
            ->selectRaw('COUNT(*) as cnt, SUM(balance) as total')
            ->first();

        $loanStats = Account::where('type', Account::TYPE_LOAN)
            ->where('balance', '<', 0)
            ->selectRaw('COUNT(*) as cnt, SUM(ABS(balance)) as total')
            ->first();

        $loanExposure = (float) ($loanStats->total ?? 0);
        $loanCount = (int) ($loanStats->cnt ?? 0);

        $coverage = $loanExposure > 0 ? round($masterFund / $loanExposure, 2) : null;

        // Activity: credits & debits in last 30 days across all accounts
        $since = Carbon::now()->subDays(30);
        $activity = AccountTransaction::where('transacted_at', '>=', $since)
            ->selectRaw("
                SUM(CASE WHEN entry_type = 'credit' THEN amount ELSE 0 END) as credits,
                SUM(CASE WHEN entry_type = 'debit'  THEN amount ELSE 0 END) as debits,
                COUNT(*) as tx_count
            ")
            ->first();

        // Members with no cash (at risk)
        $zeroBalanceCount = Member::active()
            ->whereHas(
                'accounts',
                fn($q) => $q
                    ->where('type', Account::TYPE_MEMBER_CASH)
                    ->where('balance', '<=', 0)
            )
            ->count();

        return [
            'master_cash' => $masterCash,
            'master_fund' => $masterFund,
            'member_cash_total' => (float) ($memberCashStats->total ?? 0),
            'member_cash_count' => (int) ($memberCashStats->cnt ?? 0),
            'member_fund_total' => (float) ($memberFundStats->total ?? 0),
            'member_fund_count' => (int) ($memberFundStats->cnt ?? 0),
            'loan_outstanding' => $loanExposure,
            'loan_count' => $loanCount,
            'coverage' => $coverage,
            'activity_credits' => (float) ($activity->credits ?? 0),
            'activity_debits' => (float) ($activity->debits ?? 0),
            'activity_tx_count' => (int) ($activity->tx_count ?? 0),
            'zero_balance_count' => $zeroBalanceCount,
        ];
    }
}
