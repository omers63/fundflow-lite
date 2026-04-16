<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Widgets\DatabaseBackupOverviewWidget;
use App\Filament\Admin\Widgets\DatabaseBackupsTableWidget;
use App\Services\DatabaseMaintenanceService;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class SystemMaintenancePage extends Page
{
    protected string $view = 'filament.admin.pages.system-maintenance';

    protected static ?string $navigationLabel = 'System Maintenance';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static ?int $navigationSort = 2;

    /** @var list<string> */
    public array $purgeableTables = [];

    /** @var list<string> */
    public array $alwaysExcludedTables = [];

    /** @var list<string> */
    public array $softDeleteSkippedTables = [];

    public static function getNavigationGroup(): ?string
    {
        return __('app.nav.group.system');
    }

    public function mount(DatabaseMaintenanceService $service): void
    {
        $this->refreshTableLists($service);
    }

    public function getTitle(): string
    {
        return 'System Maintenance';
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
            Action::make('purge')
                ->label('Purge now')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Purge tables without soft deletes?')
                ->modalDescription(
                    'All rows in each listed table will be permanently removed. ' .
                    'Tables with a deleted_at column are skipped. ' .
                    'Users, permissions, sessions, queues, cache, and migrations are always preserved.'
                )
                ->schema([
                    TextInput::make('confirm')
                        ->label('Type PURGE to confirm')
                        ->required()
                        ->rule('in:PURGE')
                        ->helperText('This action cannot be undone.'),
                ])
                ->action(function (array $data): void {
                    $service = app(DatabaseMaintenanceService::class);
                    $count = $service->purgePurgeableTables();

                    Notification::make()
                        ->title('Database purged')
                        ->body($count > 0
                            ? "Truncated {$count} table(s)."
                            : 'No tables matched the purge rules.')
                        ->success()
                        ->send();

                    $this->refreshTableLists($service);
                }),
        ];
    }

    private function refreshTableLists(DatabaseMaintenanceService $service): void
    {
        $this->purgeableTables = $service->getPurgeableTables();
        $this->alwaysExcludedTables = $service->alwaysExcludedTableNames();
        $this->softDeleteSkippedTables = $service->getTablesSkippedForSoftDeletes();
    }
}
