<?php

namespace App\Filament\Admin\Resources\MemberResource\Concerns;

use App\Filament\Admin\Resources\MemberResource;
use App\Models\Contribution;
use App\Models\Member;
use App\Services\ContributionCycleService;
use App\Services\LoanRepaymentService;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\HtmlString;
use Livewire\Component;

/**
 * Shared header actions for member relation managers (open-period allocate / contribute / repayment).
 *
 * @mixin \Filament\Resources\RelationManagers\RelationManager
 */
trait InteractsWithMemberCycleHeaderActions
{
    protected function cycleMember(): Member
    {
        $member = $this->getOwnerRecord();

        if (!$member instanceof Member) {
            throw new \RuntimeException('Expected member owner record.');
        }

        return $member;
    }

    protected function allocateCycleHeaderAction(): Action
    {
        return Action::make('allocate_dependent_cash')
            ->label('Allocate')
            ->icon('heroicon-o-arrow-right-circle')
            ->color('warning')
            ->authorize(
                fn(): bool => auth()->user()?->can('update', $this->cycleMember()) ?? false
            )
            ->visible(fn(): bool => $this->shouldShowAllocateHeaderAction())
            ->modalHeading(fn(): string => 'Allocate to dependents')
            ->modalDescription(
                'Choose the calendar month you are funding dependent cash for (arrears). Preview updates when you change the cycle.'
            )
            ->modalWidth('lg')
            ->schema(fn(): array => $this->allocateCycleFormSchema())
            ->fillForm(fn(): array => $this->allocateCycleFormDefaults())
            ->action(fn(array $data) => $this->runAllocateForSelectedCycle($data));
    }

    protected function allocateCycleFormSchema(): array
    {
        $member = $this->cycleMember();
        $svc = app(ContributionCycleService::class);

        return [
            Forms\Components\Select::make('cycle')
                ->label('Allocation cycle')
                ->options(fn(): array => $svc->allocationCycleSelectOptionsForParent($member))
                ->required()
                ->live()
                ->native(false)
                ->columnSpanFull(),
            Forms\Components\Placeholder::make('breakdown')
                ->label('')
                ->content(function (Get $get) use ($member) {
                    $key = $get('cycle');
                    if ($key === null || $key === '') {
                        return new HtmlString('<p class="text-sm text-gray-500 dark:text-gray-400">Select a cycle to preview.</p>');
                    }

                    try {
                        [$m, $y] = app(ContributionCycleService::class)->parseContributionCycleKey($key);
                    } catch (\InvalidArgumentException) {
                        return new HtmlString('');
                    }

                    return app(ContributionCycleService::class)->dependentAllocationModalDescriptionForPeriod($member, $m, $y);
                })
                ->columnSpanFull(),
        ];
    }

    protected function allocateCycleFormDefaults(): array
    {
        $svc = app(ContributionCycleService::class);
        $default = $svc->defaultAllocationCycleKeyForParent($this->cycleMember());

        return ['cycle' => $default ?? ''];
    }

    protected function contributeCycleHeaderAction(): Action
    {
        return Action::make('contribute_open_period')
            ->label('Contribute')
            ->icon('heroicon-o-banknotes')
            ->color('success')
            ->authorize(
                fn(): bool => auth()->user()?->can('update', $this->cycleMember()) ?? false
            )
            ->visible(fn(): bool => $this->shouldShowContributeHeaderAction())
            ->disabled(fn(): bool => $this->isContributeDisabledForInsufficientCash())
            ->modalHeading(fn(): string => 'Apply contribution')
            ->modalDescription(
                'Select the calendar month this contribution is for (arrears). The member\'s cash account is debited and fund accounts are credited the same amount.'
            )
            ->modalWidth('md')
            ->schema(fn(): array => $this->contributeCycleFormSchema())
            ->fillForm(fn(): array => $this->contributeCycleFormDefaults())
            ->action(fn(array $data) => $this->runContributeForSelectedCycle($data));
    }

    protected function contributeCycleFormSchema(): array
    {
        $member = $this->cycleMember();
        $svc = app(ContributionCycleService::class);

        return [
            Forms\Components\Select::make('cycle')
                ->label('Contribution cycle')
                ->options(fn(): array => $svc->contributionCycleSelectOptionsForMember($member))
                ->required()
                ->live()
                ->native(false)
                ->helperText(fn(Get $get): string => $svc->contributionModalDescriptionForMemberAndCycleKey(
                    $member,
                    $get('cycle'),
                ))
                ->columnSpanFull(),
        ];
    }

    protected function contributeCycleFormDefaults(): array
    {
        $svc = app(ContributionCycleService::class);
        $default = $svc->defaultContributionCycleKeyForMember($this->cycleMember());

        return ['cycle' => $default ?? ''];
    }

    protected function repaymentCycleHeaderAction(): Action
    {
        return Action::make('repayment_open_period')
            ->label('Repayment')
            ->icon('heroicon-o-receipt-percent')
            ->color('primary')
            ->authorize(
                fn(): bool => auth()->user()?->can('update', $this->cycleMember()) ?? false
            )
            ->visible(fn(): bool => $this->shouldShowRepaymentHeaderAction())
            ->disabled(fn(): bool => app(LoanRepaymentService::class)->hasInsufficientCashForOpenPeriodRepayment(
                $this->cycleMember()
            ))
            ->requiresConfirmation()
            ->modalHeading(
                fn(): string => 'Apply loan repayment – ' . app(ContributionCycleService::class)->currentOpenPeriodLabel()
            )
            ->modalDescription(
                fn(): string => app(LoanRepaymentService::class)->openPeriodRepaymentModalDescription(
                    $this->cycleMember()
                )
            )
            ->action(fn() => $this->runRepaymentForOpenPeriod());
    }

    protected function shouldShowAllocateHeaderAction(): bool
    {
        $member = $this->cycleMember();

        return !$member->trashed()
            && $member->status === 'active'
            && app(ContributionCycleService::class)->shouldShowDependentAllocationAction($member);
    }

    protected function shouldShowContributeHeaderAction(): bool
    {
        $member = $this->cycleMember();
        $cycle = app(ContributionCycleService::class);
        $repay = app(LoanRepaymentService::class);

        return !$member->trashed()
            && $member->status === 'active'
            && $cycle->memberHasPayableContributionCycle($member)
            && !$repay->shouldOfferOpenPeriodRepayment($member);
    }

    protected function shouldShowRepaymentHeaderAction(): bool
    {
        $member = $this->cycleMember();

        return !$member->trashed()
            && $member->status === 'active'
            && app(LoanRepaymentService::class)->shouldOfferOpenPeriodRepayment($member);
    }

    protected function isContributeDisabledForInsufficientCash(): bool
    {
        return app(ContributionCycleService::class)->hasInsufficientCashForOpenPeriodContribution(
            $this->cycleMember()
        );
    }

    protected function runAllocateForSelectedCycle(array $data): void
    {
        $member = $this->cycleMember();
        $svc = app(ContributionCycleService::class);
        $key = $data['cycle'] ?? null;

        if (!is_string($key) || $key === '') {
            Notification::make()
                ->title('Select an allocation cycle')
                ->danger()
                ->send();

            return;
        }

        try {
            [$month, $year] = $svc->parseContributionCycleKey($key);
        } catch (\InvalidArgumentException) {
            Notification::make()
                ->title('Invalid allocation cycle')
                ->danger()
                ->send();

            return;
        }

        $result = $svc->applyDependentAllocationForParentForPeriod($member, $month, $year);

        $body = $svc->formatAllocationResultDetailTableHtml($result['details'])->toHtml();

        if ($result['transfers'] > 0) {
            Notification::make()
                ->title('Allocation completed')
                ->body($body)
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('Allocation')
                ->body($body)
                ->warning()
                ->send();
        }

        $this->afterMemberCycleAction();
    }

    protected function runContributeForSelectedCycle(array $data): void
    {
        $member = $this->cycleMember();
        $svc = app(ContributionCycleService::class);
        $key = $data['cycle'] ?? null;

        if (!is_string($key) || $key === '') {
            Notification::make()
                ->title('Select a contribution cycle')
                ->danger()
                ->send();

            return;
        }

        try {
            [$month, $year] = $svc->parseContributionCycleKey($key);
        } catch (\InvalidArgumentException) {
            Notification::make()
                ->title('Invalid contribution cycle')
                ->danger()
                ->send();

            return;
        }

        $outcome = $svc->applyContributionForMemberForPeriod($member, $month, $year);
        $period = $svc->periodLabel($month, $year);

        if ($outcome === 'applied') {
            Notification::make()
                ->title('Contribution applied')
                ->body('SAR ' . number_format((float) $member->monthly_contribution_amount, 2) . " posted for {$period}.")
                ->success()
                ->send();

            $this->afterMemberCycleAction();

            return;
        }

        if ($outcome === 'insufficient') {
            Notification::make()
                ->title('Insufficient cash balance')
                ->body('Cash balance is below the required monthly amount. Fund the cash account first.')
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title('Could not apply contribution')
            ->body(match ($outcome) {
                'already_contributed' => Contribution::duplicateCycleMessage($month, $year),
                'exempt' => 'This member is exempt from contributions while they have an approved or active loan.',
                'skipped' => 'This contribution could not be applied.',
                default => "Status: {$outcome}",
            })
            ->warning()
            ->send();
    }

    protected function runRepaymentForOpenPeriod(): void
    {
        $member = $this->cycleMember();
        $svc = app(LoanRepaymentService::class);
        $outcome = $svc->applyOpenPeriodRepaymentForMember($member);
        $period = app(ContributionCycleService::class)->currentOpenPeriodLabel();

        if ($outcome === 'applied') {
            Notification::make()
                ->title('Repayment applied')
                ->body("Loan installment posted for {$period}.")
                ->success()
                ->send();

            $this->afterMemberCycleAction();

            return;
        }

        if ($outcome === 'insufficient') {
            Notification::make()
                ->title('Insufficient cash balance')
                ->body('Cash balance is below the installment amount.')
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title('Could not apply repayment')
            ->body(match ($outcome) {
                'skipped' => 'No unpaid installment for this period or no active loan.',
                default => "Status: {$outcome}",
            })
            ->warning()
            ->send();
    }

    protected function afterMemberCycleAction(): void
    {
        if ($this instanceof Component) {
            MemberResource::dispatchMemberRecordHeaderWidgetsRefresh($this);
        }

        if (method_exists($this, 'resetTable')) {
            $this->resetTable();
        }
    }
}
