<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Widgets\DatabaseBackupOverviewWidget;
use App\Filament\Admin\Widgets\DatabaseBackupsTableWidget;
use App\Services\DatabaseMaintenanceService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class BackupDatabasePage extends Page
{
    protected string $view = 'filament.admin.pages.backup-database';

    protected static ?string $navigationLabel = 'Backup Database';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-down-tray';

    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): ?string
    {
        return __('app.nav.group.system');
    }

    public function getTitle(): string
    {
        return 'Backup Database';
    }

    protected function getHeaderWidgets(): array
    {
        return [
            DatabaseBackupOverviewWidget::class,
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            DatabaseBackupsTableWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }

    public function getFooterWidgetsColumns(): int|array
    {
        return 1;
    }

    public function getHeaderActions(): array
    {
        return [
            Action::make('saveToServer')
                ->label('Save backup to server')
                ->icon('heroicon-o-server-stack')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Save backup to server?')
                ->modalDescription('Creates a new file under storage/app/backups/ and adds a row to the backup history. Use Download backup for a one-off copy without storing on the server.')
                ->action(function (): void {
                    try {
                        app(DatabaseMaintenanceService::class)->createStoredBackup();
                    } catch (\Throwable $e) {
                        report($e);
                        Notification::make()
                            ->title('Backup failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title('Backup saved')
                        ->body('The file was written to storage/app/backups/ and listed below.')
                        ->success()
                        ->send();

                    $this->redirect(static::getUrl());
                }),
            Action::make('download')
                ->label('Download backup')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('primary')
                ->url(route('admin.system.backup-download')),
        ];
    }
}
