<?php

namespace App\Filament\Admin\Resources\BankImportSessionResource\Pages;

use App\Filament\Admin\Resources\BankImportSessionResource;
use Filament\Resources\Pages\ListRecords;

class ListBankImportSessions extends ListRecords
{
    protected static string $resource = BankImportSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
