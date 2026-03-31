<?php

namespace App\Filament\Admin\Resources\BankImportTemplateResource\Pages;

use App\Filament\Admin\Resources\BankImportTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBankImportTemplates extends ListRecords
{
    protected static string $resource = BankImportTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
