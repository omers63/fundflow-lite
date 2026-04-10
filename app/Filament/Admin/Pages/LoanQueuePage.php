<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Resources\LoanResource;
use App\Models\FundTier;
use App\Models\Loan;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Collection;

class LoanQueuePage extends Page
{
    protected string $view = 'filament.admin.pages.loan-queue';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationLabel = 'Loan Queue';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-queue-list';

    protected static ?int $navigationSort = 4;

    public static function getNavigationGroup(): ?string
    {
        return __('app.nav.group.finance');
    }

    public function getTitle(): string
    {
        return 'Loan queue';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'All incoming loan requests and disbursed loans, grouped by fund tier. Pending applications appear at the top before they are reviewed and assigned a tier.';
    }

    public function getHeaderActions(): array
    {
        return [
            Action::make('allLoans')
                ->label('All loans')
                ->icon('heroicon-o-banknotes')
                ->url(LoanResource::getUrl('index'))
                ->color('gray'),
        ];
    }

    /**
     * @return Collection<int, FundTier>
     */
    public function getFundTiers()
    {
        return FundTier::query()
            ->where('is_active', true)
            ->with('loanTier')
            ->orderBy('tier_number')
            ->get();
    }

    /**
     * Pending loan requests not yet assigned to any fund tier.
     * These are incoming applications awaiting admin review.
     *
     * @return Collection<int, Loan>
     */
    public function getPendingApplications()
    {
        return Loan::query()
            ->where('status', 'pending')
            ->whereNull('fund_tier_id')
            ->with(['member.user', 'loanTier'])
            ->orderBy('applied_at')
            ->get();
    }

    /**
     * Loans in a specific fund tier: approved (awaiting disbursement) and active (being repaid).
     *
     * @return Collection<int, Loan>
     */
    public function getQueueForTier(int $fundTierId)
    {
        return Loan::query()
            ->where('fund_tier_id', $fundTierId)
            ->whereIn('status', ['approved', 'active'])
            ->with(['member.user', 'loanTier'])
            ->orderBy('queue_position')
            ->orderBy('applied_at')
            ->get();
    }

    public function loanViewUrl(Loan $loan): ?string
    {
        if (!LoanResource::canView($loan)) {
            return null;
        }

        return LoanResource::getUrl('view', ['record' => $loan]);
    }
}
