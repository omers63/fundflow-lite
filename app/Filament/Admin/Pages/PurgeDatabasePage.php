<?php

namespace App\Filament\Admin\Pages;

use Filament\Pages\Page;

/**
 * Legacy route; redirects to {@see SystemMaintenancePage}.
 */
class PurgeDatabasePage extends Page
{
    protected string $view = 'filament.admin.pages.purge-database';

    protected static bool $shouldRegisterNavigation = false;

    public static function getNavigationGroup(): ?string
    {
        return 'system';
    }

    public function mount(): void
    {
        $this->redirect(SystemMaintenancePage::getUrl());
    }
}
