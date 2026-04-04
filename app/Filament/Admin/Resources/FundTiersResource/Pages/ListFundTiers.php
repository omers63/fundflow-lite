<?php

namespace App\Filament\Admin\Resources\FundTiersResource\Pages;

use App\Filament\Admin\Resources\FundTiersResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFundTiers extends ListRecords
{
    protected static string $resource = FundTiersResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
