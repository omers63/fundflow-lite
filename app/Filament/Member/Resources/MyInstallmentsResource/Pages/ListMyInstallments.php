<?php

namespace App\Filament\Member\Resources\MyInstallmentsResource\Pages;

use App\Filament\Member\Resources\MyInstallmentsResource;
use Filament\Resources\Pages\ListRecords;

class ListMyInstallments extends ListRecords
{
    protected static string $resource = MyInstallmentsResource::class;

    public function getTitle(): string
    {
        return __('My Installments');
    }
}
