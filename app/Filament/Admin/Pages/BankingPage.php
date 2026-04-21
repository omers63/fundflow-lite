<?php

namespace App\Filament\Admin\Pages;

use Filament\Pages\Page;
use Livewire\Attributes\Url;

class BankingPage extends Page
{
    protected string $view = 'filament.admin.pages.banking';

    protected static ?string $navigationLabel = 'Banking';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-library';

    protected static ?int $navigationSort = 0;

    /** @var 'banks'|'sms' */
    #[Url]
    public string $activeTab = 'banks';

    /** @var 'banks'|'templates'|'transactions'|'history' */
    #[Url]
    public string $banksSubTab = 'banks';

    /** @var 'templates'|'transactions'|'history' */
    #[Url]
    public string $smsSubTab = 'transactions';

    public static function getNavigationGroup(): ?string
    {
        return 'finance';
    }

    /**
     * @return array<int, string>|string
     */
    public static function getNavigationItemActiveRoutePattern(): string|array
    {
        return [
            static::getRouteName(),
            'filament.admin.resources.banks.*',
            'filament.admin.resources.bank-import-templates.*',
            'filament.admin.resources.bank-transactions.*',
            'filament.admin.resources.bank-import-sessions.*',
            'filament.admin.resources.sms-import-templates.*',
            'filament.admin.resources.sms-transactions.*',
            'filament.admin.resources.sms-import-sessions.*',
        ];
    }

    public function mount(): void
    {
        if ($this->activeTab === 'overview') {
            $this->activeTab = 'banks';
        }

        if (! in_array($this->activeTab, ['banks', 'sms'], true)) {
            $this->activeTab = 'banks';
        }

        if (! in_array($this->banksSubTab, ['banks', 'templates', 'transactions', 'history'], true)) {
            $this->banksSubTab = 'banks';
        }

        if (! in_array($this->smsSubTab, ['templates', 'transactions', 'history'], true)) {
            $this->smsSubTab = 'transactions';
        }
    }

    public function getTitle(): string
    {
        return 'Banking';
    }

    public function getSubheading(): ?string
    {
        return 'Manage banks, CSV and SMS imports, transactions, and import history.';
    }
}
