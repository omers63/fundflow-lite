<?php

namespace App\Filament\Member\Resources\MyLoansResource\Pages;

use App\Filament\Member\Resources\MyLoansResource;
use Filament\Resources\Pages\ListRecords;

class ListMyLoans extends ListRecords
{
    protected static string $resource = MyLoansResource::class;

    public function getTitle(): string
    {
        return __('My Loans');
    }
}
