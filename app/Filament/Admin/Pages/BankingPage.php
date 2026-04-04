<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Resources\BankImportTemplateResource;
use App\Filament\Admin\Resources\SmsImportTemplateResource;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Livewire\Attributes\Url;

class BankingPage extends Page
{
    protected string $view = 'filament.admin.pages.banking';

    protected static ?string $navigationLabel = 'Banking';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-library';

    protected static ?int $navigationSort = 1;

    /** @var 'overview'|'bank-templates'|'sms-templates' */
    #[Url]
    public string $activeTab = 'overview';

    public static function getNavigationGroup(): ?string
    {
        return 'Banking';
    }

    /**
     * Keep this item active when the user navigates to the bank/sms template create/edit routes.
     *
     * @return array<int, string>|string
     */
    public static function getNavigationItemActiveRoutePattern(): string|array
    {
        return [
            static::getRouteName(),
            'filament.admin.resources.bank-import-templates.*',
            'filament.admin.resources.sms-import-templates.*',
        ];
    }

    public function mount(): void
    {
        if (! in_array($this->activeTab, ['overview', 'bank-templates', 'sms-templates'], true)) {
            $this->activeTab = 'overview';
        }
    }

    public function getHeaderActions(): array
    {
        return [
            Action::make('new_bank_template')
                ->label('New bank template')
                ->icon('heroicon-o-plus-circle')
                ->color('primary')
                ->visible(fn (): bool => $this->activeTab === 'bank-templates')
                ->url(BankImportTemplateResource::getUrl('create')),

            Action::make('new_sms_template')
                ->label('New SMS template')
                ->icon('heroicon-o-plus-circle')
                ->color('primary')
                ->visible(fn (): bool => $this->activeTab === 'sms-templates')
                ->url(SmsImportTemplateResource::getUrl('create')),
        ];
    }

    public function getTitle(): string
    {
        return 'Banking';
    }

    public function getSubheading(): ?string
    {
        return 'Manage banks, import templates, and transaction import activity across bank CSV and SMS channels.';
    }
}
