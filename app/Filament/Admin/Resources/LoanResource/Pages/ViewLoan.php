<?php

namespace App\Filament\Admin\Resources\LoanResource\Pages;

use App\Filament\Admin\Resources\LoanResource;
use App\Models\Loan;
use App\Services\AccountingService;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewLoan extends ViewRecord
{
    protected static string $resource = LoanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            LoanResource::approveLoanAction(),
            LoanResource::disburseLoanAction(),
            LoanResource::rejectLoanAction(),
            LoanResource::earlySettleLoanAction(),
            EditAction::make(),
            DeleteAction::make()
                ->modalDescription(
                    __('Reverses all ledger postings for this loan (disbursement, repayments, and any cash or guarantor lines tied to its installments), deletes installments and the loan account, then removes the loan. This cannot be undone.')
                )
                ->using(function (Loan $record) {
                    app(AccountingService::class)->safeDeleteLoan($record);

                    return true;
                }),
        ];
    }
}
