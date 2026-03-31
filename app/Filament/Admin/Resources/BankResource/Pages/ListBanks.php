<?php

namespace App\Filament\Admin\Resources\BankResource\Pages;

use App\Filament\Admin\Resources\BankResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBanks extends ListRecords
{
    protected static string $resource = BankResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
