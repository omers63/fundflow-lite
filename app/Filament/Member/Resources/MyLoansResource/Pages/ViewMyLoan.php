<?php

namespace App\Filament\Member\Resources\MyLoansResource\Pages;

use App\Filament\Member\Resources\MyLoansResource;
use App\Models\Loan;
use App\Models\Member;
use App\Services\LoanEarlySettlementService;
use App\Services\LoanRepaymentService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewMyLoan extends ViewRecord
{
    protected static string $resource = MyLoansResource::class;

    protected string $view = 'filament.member.pages.view-my-loan';

    public function getTitle(): string
    {
        /** @var Loan $record */
        $record = $this->getRecord();

        return 'Loan #' . $record->id . ' — ' . ucfirst(str_replace('_', ' ', $record->status));
    }

    protected function getHeaderActions(): array
    {
        /** @var Loan $record */
        $record = $this->getRecord();

        $downloadSchedule = Action::make('download_schedule')
            ->label('Download Schedule')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('gray')
            ->url(fn() => route('member.loan.schedule', $record))
            ->openUrlInNewTab();

        if ($record->status !== 'active') {
            return [$downloadSchedule];
        }

        $me = Member::where('user_id', auth()->id())->with('accounts')->first();
        $svc = app(LoanRepaymentService::class);
        $canPay = $me && $svc->shouldOfferOpenPeriodRepayment($me);
        $insufficientCash = !$me || $svc->hasInsufficientCashForOpenPeriodRepayment($me);

        return [
            Action::make('pay_installment')
                ->label('Pay Installment')
                ->icon('heroicon-o-credit-card')
                ->color('primary')
                ->visible($canPay)
                ->disabled($insufficientCash)
                ->requiresConfirmation()
                ->modalHeading('Pay Your Loan Installment')
                ->modalDescription($me ? $svc->openPeriodRepaymentModalDescription($me) : '')
                ->modalSubmitActionLabel('Pay Now')
                ->action(function () use ($record) {
                    $member = Member::where('user_id', auth()->id())->with(['user', 'accounts'])->first();
                    if (!$member || (int) $record->member_id !== (int) $member->id) {
                        Notification::make()->title('Unauthorized')->danger()->send();
                        return;
                    }
                    $outcome = app(LoanRepaymentService::class)->applyOpenPeriodRepaymentForMember($member);
                    match ($outcome) {
                        'applied' => Notification::make()->title('Installment Paid')->body('Your installment for this period has been paid.')->success()->send(),
                        'insufficient' => Notification::make()->title('Insufficient Balance')->body('Not enough cash balance to cover this installment.')->danger()->send(),
                        default => Notification::make()->title('Nothing to Pay')->body('No installment is due for the current period.')->warning()->send(),
                    };
                }),

            Action::make('early_settle')
                ->label('Pay Off Early')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Pay off your loan early')
                ->modalDescription(function () use ($record) {
                    $svc = app(LoanEarlySettlementService::class);
                    $me = Member::where('user_id', auth()->id())->first();
                    $required = $svc->requiredCash($record);
                    $balance  = (float) ($me?->cash_balance ?? 0);
                    $principal = $record->remaining_amount;

                    return 'Installments remaining (principal): SAR ' . number_format($principal, 2)
                        . '. Total cash required (including late fees): SAR ' . number_format($required, 2)
                        . '. Your cash balance: SAR ' . number_format($balance, 2)
                        . '. The full amount will be debited from your cash account and the loan will close.';
                })
                ->action(function () use ($record) {
                    $me = Member::where('user_id', auth()->id())->first();
                    if (!$me || (int) $record->member_id !== (int) $me->id) {
                        Notification::make()->title('Unauthorized')->danger()->send();

                        return;
                    }

                    try {
                        app(LoanEarlySettlementService::class)->earlySettle($record);
                    } catch (\InvalidArgumentException | \RuntimeException $e) {
                        Notification::make()
                            ->title('Could not complete early payoff')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title('Loan paid off')
                        ->body('Your loan is now closed.')
                        ->success()
                        ->send();

                    $this->redirect(MyLoansResource::getUrl('index'));
                }),

            $downloadSchedule,
        ];
    }
}
