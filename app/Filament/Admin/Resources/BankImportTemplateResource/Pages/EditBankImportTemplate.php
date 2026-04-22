<?php

namespace App\Filament\Admin\Resources\BankImportTemplateResource\Pages;

use App\Filament\Admin\Resources\BankImportTemplateResource;
use App\Models\BankImportTemplate;
use Filament\Resources\Pages\EditRecord;

class EditBankImportTemplate extends EditRecord
{
    protected static string $resource = BankImportTemplateResource::class;

    public function getTitle(): string
    {
        return __('Edit Bank Import Template');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['duplicate_match_fields'] = BankImportTemplate::sanitizeDuplicateMatchFields(
            isset($data['duplicate_match_fields']) && is_array($data['duplicate_match_fields'])
                ? $data['duplicate_match_fields']
                : null,
            isset($data['optional_columns']) && is_array($data['optional_columns'])
                ? $data['optional_columns']
                : null
        );

        return $data;
    }
}
