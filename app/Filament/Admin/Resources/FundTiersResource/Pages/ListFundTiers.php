<?php

namespace App\Filament\Admin\Resources\FundTiersResource\Pages;

use App\Filament\Admin\Resources\FundTiersResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFundTiers extends ListRecords
{
    protected static string $resource = FundTiersResource::class;

    public function getTitle(): string
    {
        return __('Fund Tiers');
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
