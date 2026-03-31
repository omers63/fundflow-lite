<?php

namespace App\Filament\Admin\Resources\BankTransactionResource\Pages;

use App\Filament\Admin\Resources\BankTransactionResource;
use Filament\Resources\Pages\ListRecords;

class ListBankTransactions extends ListRecords
{
    protected static string $resource = BankTransactionResource::class;
}
