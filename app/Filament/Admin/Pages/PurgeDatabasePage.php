<?php

namespace App\Filament\Admin\Pages;

use App\Services\DatabaseMaintenanceService;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class PurgeDatabasePage extends Page
{
    protected string $view = 'filament.admin.pages.purge-database';

    protected static ?string $navigationLabel = 'Purge Database';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-trash';

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
        return 'Purge Database';
    }

    public function getHeaderActions(): array
    {
        return [
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
