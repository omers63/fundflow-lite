<?php

namespace App\Filament\Admin\Pages;

use App\Models\FundTier;
use App\Models\Loan;
use Filament\Pages\Page;

class LoanQueuePage extends Page
{
    protected string $view = 'filament.admin.pages.loan-queue';

    protected static ?string $navigationLabel = 'Loan Queue';
    protected static string|\BackedEnum|null $navigationIcon = null;
    protected static ?int $navigationSort = 4;

    public static function getNavigationGroup(): ?string
    {
        return __('app.nav.group.finance');
    }

    public function getTitle(): string { return 'Loan Queue'; }

    public function getFundTiers()
    {
        return FundTier::where('is_active', true)->with('loanTier')->orderBy('tier_number')->get();
    }

    public function getQueueForTier(int $fundTierId)
    {
        return Loan::where('fund_tier_id', $fundTierId)
            ->whereIn('status', ['pending', 'approved'])
            ->with(['member.user', 'loanTier'])
            ->orderBy('queue_position')
            ->orderBy('applied_at')
            ->get();
    }
}
