<?php

namespace App\Filament\Member\Resources\MyGuaranteedLoansResource\Pages;

use App\Filament\Member\Resources\MyGuaranteedLoansResource;
use Filament\Resources\Pages\ListRecords;

class ListMyGuaranteedLoans extends ListRecords
{
    protected static string $resource = MyGuaranteedLoansResource::class;

    public function getTitle(): string
    {
        return 'Loans I Guarantee';
    }
}
