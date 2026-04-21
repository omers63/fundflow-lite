<?php

namespace App\Filament\Admin\Resources\LoanTiersResource\Pages;

use App\Filament\Admin\Resources\LoanTiersResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLoanTier extends CreateRecord
{
    protected static string $resource = LoanTiersResource::class;

    public function getTitle(): string
    {
        return __('Add Loan Tier');
    }
}
