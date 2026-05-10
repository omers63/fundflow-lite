<?php

namespace App\Filament\Admin\Resources\BankResource\Pages;

use App\Filament\Admin\Resources\BankResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions\CreateAction;

class ListBanks extends ListRecords
{
    protected static string $resource = BankResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
