<?php

namespace App\Filament\Admin\Resources\LoanTiersResource\Pages;

use App\Filament\Admin\Resources\LoanTiersResource;
use Filament\Resources\Pages\EditRecord;

class EditLoanTier extends EditRecord
{
    protected static string $resource = LoanTiersResource::class;

    public function getTitle(): string
    {
        return __('Edit Loan Tier');
    }
}
