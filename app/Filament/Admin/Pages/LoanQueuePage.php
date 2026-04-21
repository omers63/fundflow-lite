<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Resources\LoanResource;
use App\Models\FundTier;
use App\Models\Loan;
use App\Services\LoanQueueOrderingService;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

class LoanQueuePage extends Page
{
    protected string $view = 'filament.admin.pages.loan-queue';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationLabel = 'Loan Queue';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-queue-list';

    protected static ?int $navigationSort = 4;

    public static function getNavigationLabel(): string
    {
        return __('Loan Queue');
    }

    public static function getNavigationGroup(): ?string
    {
        return 'finance';
    }

    public function getTitle(): string
    {
        return __('Loan queue');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('Incoming requests stay visible until full disbursement, and are ordered by emergency status, fund tier, loan tier (1 before higher tiers), time received, and available capacity. Tier queues use the same rules; positions refresh when loans are approved, rejected, cancelled, disbursed, or removed.');
    }

    public function getHeaderActions(): array
    {
        return [
            Action::make('allLoans')
                ->label(__('All loans'))
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
     * Incoming loan requests: pending plus approved loans that are not yet fully disbursed.
     *
     * @return SupportCollection<int, Loan>
     */
    public function getPendingApplications()
    {
        $loans = Loan::query()
            ->where(function ($query) {
                $query->where('status', 'pending')
                    ->orWhere(function ($approvedQuery) {
                        $approvedQuery->where('status', 'approved')
                            ->where(function ($disbursementQuery) {
                                $disbursementQuery->whereNull('amount_approved')
                                    ->orWhereColumn('amount_disbursed', '<', 'amount_approved');
                            });
                    });
            })
            ->with(['member.user', 'loanTier', 'fundTier'])
            ->get();

        return LoanQueueOrderingService::orderIncomingPending($loans);
    }

    /**
     * Loans in a specific fund tier: approved (awaiting disbursement) and active (being repaid).
     *
     * @return SupportCollection<int, Loan>
     */
    public function getQueueForTier(int $fundTierId)
    {
        $fundTier = FundTier::query()->find($fundTierId);
        if ($fundTier === null) {
            return collect();
        }

        $loans = Loan::query()
            ->where('fund_tier_id', $fundTierId)
            ->whereIn('status', ['approved', 'active'])
            ->with(['member.user', 'loanTier'])
            ->get();

        return LoanQueueOrderingService::orderTierQueue($loans, $fundTier);
    }

    public function loanViewUrl(Loan $loan): ?string
    {
        if (!LoanResource::canView($loan)) {
            return null;
        }

        return LoanResource::getUrl('view', ['record' => $loan]);
    }
}
