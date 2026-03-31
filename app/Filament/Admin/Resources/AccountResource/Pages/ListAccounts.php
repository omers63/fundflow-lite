<?php

namespace App\Filament\Admin\Resources\AccountResource\Pages;

use App\Filament\Admin\Resources\AccountResource;
use App\Models\Account;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Placeholder;
use Filament\Support\Enums\FontWeight;

class ListAccounts extends ListRecords
{
    protected static string $resource = AccountResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getHeaderWidgets(): array
    {
        return [];
    }

    public function getSubheading(): ?string
    {
        $cash = Account::where('slug', 'master_cash')->value('balance') ?? 0;
        $fund = Account::where('slug', 'master_fund')->value('balance') ?? 0;

        return sprintf(
            'Cash Account: SAR %s   |   Fund Account: SAR %s',
            number_format((float) $cash, 2),
            number_format((float) $fund, 2)
        );
    }
}
