<?php

namespace App\Filament\Member\Resources\MyAccountLedgerResource\Pages;

use App\Filament\Member\Resources\MyAccountLedgerResource;
use Filament\Resources\Pages\ListRecords;

class ListMyAccountLedger extends ListRecords
{
    protected static string $resource = MyAccountLedgerResource::class;

    public function getTitle(): string
    {
        return __('My Ledger');
    }

    public function getSubheading(): ?string
    {
        return __('Full history of all credits and debits on your cash and fund accounts.');
    }
}
