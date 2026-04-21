<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use Filament\Pages\Page;

/**
 * Legacy route; redirects to {@see SystemMaintenancePage}.
 */
class BackupDatabasePage extends Page
{
    protected string $view = 'filament.admin.pages.backup-database';

    protected static bool $shouldRegisterNavigation = false;

    public static function getNavigationGroup(): ?string
    {
        return 'system';
    }

    public static function getNavigationLabel(): string
    {
        return __('Database backups');
    }

    public function getTitle(): string
    {
        return __('Database backups');
    }

    public function mount(): void
    {
        $this->redirect(SystemMaintenancePage::getUrl());
    }
}
