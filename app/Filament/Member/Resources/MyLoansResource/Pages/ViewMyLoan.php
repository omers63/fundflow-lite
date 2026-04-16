<?php

namespace App\Filament\Member\Resources\MyLoansResource\Pages;

use App\Filament\Member\Resources\MyLoansResource;
use App\Models\Loan;
use App\Models\Member;
use App\Services\LoanEarlySettlementService;
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

        if ($record->status !== 'active') {
            return [];
        }

        return [
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
        ];
    }
}
