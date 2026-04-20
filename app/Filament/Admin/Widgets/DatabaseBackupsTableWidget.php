<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Models\DatabaseBackup;
use App\Services\DatabaseMaintenanceService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Filament\Tables;
use Filament\Tables\Enums\PaginationMode;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;

class DatabaseBackupsTableWidget extends TableWidget
{
    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    protected function getTableQuery(): Builder
    {
        return DatabaseBackup::query()->with('user')->latest();
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('Existing Backups'))
            ->description(__('Backup files stored in storage/app/backups/. You can download or delete individual backups.'))
            ->columns([
                Tables\Columns\TextColumn::make('filename')
                    ->searchable()
                    ->copyable()
                    ->weight('medium'),
                Tables\Columns\TextColumn::make('size_bytes')
                    ->label(__('Size'))
                    ->formatStateUsing(fn (?int $state): string => $state !== null
                        ? Number::fileSize($state, precision: 2)
                        : '—')
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('driver')
                    ->colors([
                        'info' => 'sqlite',
                        'warning' => 'mysql',
                        'primary' => 'mariadb',
                    ]),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Created'))
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label(__('Created by'))
                    ->placeholder('—'),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('download')
                        ->label(__('Download'))
                        ->icon('heroicon-o-arrow-down-tray')
                        ->url(fn (DatabaseBackup $record): string => route('admin.system.backup-stored-download', $record))
                        ->authorize(fn (): bool => auth()->user()?->canAccessPanel(Filament::getPanel('admin')) ?? false),
                    DeleteAction::make()
                        ->label(__('Delete'))
                        ->modalHeading(__('Delete backup?'))
                        ->modalDescription(__('Removes the database row and deletes the file from storage/app/backups/.'))
                        ->authorize(fn (): bool => auth()->user()?->canAccessPanel(Filament::getPanel('admin')) ?? false)
                        ->using(function (DatabaseBackup $record): bool {
                            app(DatabaseMaintenanceService::class)->deleteStoredBackup($record);

                            return true;
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    protected function makeTable(): Table
    {
        return $this->makeBaseTable()
            ->paginationMode(PaginationMode::Default);
    }
}
