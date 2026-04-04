<?php

namespace App\Filament\Admin\Pages;

use Filament\Actions\Action;
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

    public function getHeaderActions(): array
    {
        return [
            Action::make('download')
                ->label('Download backup')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('primary')
                ->url(route('admin.system.backup-download')),
        ];
    }
}
