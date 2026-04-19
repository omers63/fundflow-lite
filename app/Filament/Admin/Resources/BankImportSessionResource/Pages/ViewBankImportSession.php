<?php

namespace App\Filament\Admin\Resources\BankImportSessionResource\Pages;

use App\Filament\Admin\Resources\BankImportSessionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\ViewRecord;

class ViewBankImportSession extends ViewRecord
{
    protected static string $resource = BankImportSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->label('Delete import')
                ->modalHeading('Delete import history')
                ->modalDescription('Removes this import run and deletes every transaction from it. Posted rows are reversed in the ledger first, then soft-deleted.')
                ->visible(fn (): bool => ! $this->getRecord()->trashed())
                ->using(function (): bool {
                    BankImportSessionResource::deleteSessionAndTransactions($this->getRecord());

                    return true;
                })
                ->successRedirectUrl(fn (): string => BankImportSessionResource::getUrl('index')),
        ];
    }
}
