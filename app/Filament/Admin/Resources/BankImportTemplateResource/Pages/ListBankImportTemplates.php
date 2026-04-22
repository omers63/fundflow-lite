<?php

namespace App\Filament\Admin\Resources\BankImportTemplateResource\Pages;

use App\Filament\Admin\Resources\BankImportTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBankImportTemplates extends ListRecords
{
    protected static string $resource = BankImportTemplateResource::class;

    public function getTitle(): string
    {
        return __('Bank Import Templates');
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('Create Bank Import Template')),
        ];
    }
}
