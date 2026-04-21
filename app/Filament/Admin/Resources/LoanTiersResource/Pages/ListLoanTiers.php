<?php

namespace App\Filament\Admin\Resources\LoanTiersResource\Pages;

use App\Filament\Admin\Resources\LoanTiersResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLoanTiers extends ListRecords
{
    protected static string $resource = LoanTiersResource::class;

    public function getTitle(): string
    {
        return __('Loan Tiers');
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
