<?php

namespace App\Filament\Admin\Resources\BankImportTemplateResource\Pages;

use App\Filament\Admin\Resources\BankImportTemplateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBankImportTemplate extends CreateRecord
{
    protected static string $resource = BankImportTemplateResource::class;

    public function getTitle(): string
    {
        return __('Create Bank Import Template');
    }
}
