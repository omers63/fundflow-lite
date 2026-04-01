<?php

namespace App\Filament\Member\Resources\MyDependentsResource\Pages;

use App\Filament\Member\Resources\MyDependentsResource;
use Filament\Resources\Pages\ListRecords;

class ListMyDependents extends ListRecords
{
    protected static string $resource = MyDependentsResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
