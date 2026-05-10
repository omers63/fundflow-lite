<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Models\FiscalYear;
use App\Models\FiscalYearClosure;
use App\Models\User;
use App\Services\FiscalYearCloseBookExcelService;
use App\Services\FiscalYearClosingService;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Throwable;

class FiscalYearClosingPage extends Page
{
    protected string $view = 'filament.admin.pages.fiscal-year-closing';

    protected static ?string $slug = 'fiscal-year-closing';

    protected static ?string $navigationLabel = null;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-archive-box-arrow-down';

    protected static ?int $navigationSort = 100;

    public ?int $fiscal_year_id = null;

    /** @var array<string, mixed>|null */
    public ?array $dry_run_result = null;

    public static function getNavigationLabel(): string
    {
        return __('Fiscal year close');
    }

    public static function getNavigationGroup(): ?string
    {
        // Must match AdminPanelProvider key `finance` (not the translated label) or Filament
        // renders a second sidebar group with the same “Finance” title.
        return 'finance';
    }

    public static function canAccess(): bool
    {
        $u = auth()->user();

        return $u instanceof User && $u->isAdmin();
    }

    public function getTitle(): string
    {
        return __('Fiscal year closing');
    }

    public function getSubheading(): ?string
    {
        return __('Dry-run, close to archive, restore, and download a multi-sheet Excel close book (alongside your database backups).');
    }

    public function mount(): void
    {
        $initialYear = (int) config('fundflow.initial_calendar_year');
        $initialCode = 'FY'.$initialYear;

        $selected = FiscalYear::query()->where('status', 'open')->orderByDesc('start_date')->first()
            ?? FiscalYear::query()->where('code', $initialCode)->first()
            ?? FiscalYear::query()->orderBy('start_date')->first()
            ?? FiscalYear::query()->orderByDesc('start_date')->first();

        $this->fiscal_year_id = $selected?->id;
    }

    public function updatedFiscalYearId(): void
    {
        $this->dry_run_result = null;
    }

    #[Computed]
    public function fiscalYear(): ?FiscalYear
    {
        if ($this->fiscal_year_id === null) {
            return null;
        }

        return FiscalYear::query()->find($this->fiscal_year_id);
    }

    #[Computed]
    public function recentClosures(): Collection
    {
        return FiscalYearClosure::query()
            ->with(['fiscalYear', 'startedBy'])
            ->latest('started_at')
            ->limit(25)
            ->get();
    }

    protected function getHeaderActions(): array
    {
        $closing = app(FiscalYearClosingService::class);
        $excel = app(FiscalYearCloseBookExcelService::class);

        return [
            Action::make('dry_run')
                ->label(__('Dry run (counts)'))
                ->icon('heroicon-o-magnifying-glass')
                ->action(function () use ($closing): void {
                    $fy = $this->fiscalYear;
                    if (! $fy) {
                        Notification::make()->title(__('Select a fiscal year'))->warning()->send();

                        return;
                    }
                    $this->dry_run_result = $closing->dryRun($fy);
                    Notification::make()->title(__('Dry run complete'))->success()->send();
                }),
            Action::make('export_excel')
                ->label(__('Download Excel close book'))
                ->icon('heroicon-o-arrow-down-tray')
                ->action(function () use ($excel) {
                    $fy = $this->fiscalYear;
                    if (! $fy) {
                        Notification::make()->title(__('Select a fiscal year'))->warning()->send();

                        return null;
                    }

                    return $excel->downloadResponse($fy);
                }),
            Action::make('close_year')
                ->label(__('Execute close (archive)'))
                ->icon('heroicon-o-lock-closed')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading(__('Close fiscal year?'))
                ->modalDescription(__('Copies scoped rows into a SQLite file under database/archives/ (run php artisan migrate on new files automatically on first close). Optionally purge legacy fact rows from primary after verification.'))
                ->form([
                    Checkbox::make('purge_primary_after_close')
                        ->label(__('After a successful archive copy, purge that fiscal slice from the primary database'))
                        ->helperText(__('Balances are recomputed from remaining ledger postings. Snapshot rows are recorded in fiscal_year_account_snapshots.'))
                        ->default(false),
                ])
                ->visible(fn (): bool => $this->canManageCritical())
                ->action(function (array $data) use ($closing): void {
                    $fy = $this->fiscalYear;
                    $user = auth()->user();
                    if (! $fy || ! $user instanceof User) {
                        return;
                    }
                    $purge = (bool) ($data['purge_primary_after_close'] ?? false);
                    try {
                        $closing->close($fy, (int) $user->id, null, $purge);
                        $title = $purge
                            ? __('Fiscal year closed and primary slice purged')
                            : __('Fiscal year closed');
                        Notification::make()->title($title)->success()->send();
                    } catch (Throwable $e) {
                        Notification::make()
                            ->title(__('Close failed'))
                            ->body($e->getMessage())
                            ->danger()
                            ->persistent()
                            ->send();
                    }
                    $this->dry_run_result = null;
                }),
            Action::make('purge_primary')
                ->label(__('Purge primary facts (already closed)'))
                ->icon('heroicon-o-minus-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading(__('Purge archived slice from primary?'))
                ->modalDescription(__('Deletes only fact-table rows for this fiscal year (same scope as close). Keeps archive SQLite intact.'))
                ->visible(function (): bool {
                    $fy = $this->fiscalYear;

                    return $fy !== null && $fy->status === 'closed' && $fy->purged_primary_at === null
                        && filled($fy->archive_database_path) && $this->canManageCritical();
                })
                ->action(function () use ($closing): void {
                    $fy = $this->fiscalYear;
                    $user = auth()->user();
                    if (! $fy || ! $user instanceof User) {
                        return;
                    }
                    try {
                        $closing->purgePrimaryForClosedFiscalYear($fy);
                        Notification::make()->title(__('Primary purge completed'))->success()->send();
                    } catch (Throwable $e) {
                        Notification::make()
                            ->title(__('Purge failed'))
                            ->body($e->getMessage())
                            ->danger()
                            ->persistent()
                            ->send();
                    }
                }),
            Action::make('restore_year')
                ->label(__('Restore from archive'))
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading(__('Restore from archive into primary?'))
                ->modalDescription(__('Upserts archived rows back into the primary database by primary key. Use with care in maintenance windows.'))
                ->visible(fn (): bool => $this->canManageCritical())
                ->action(function () use ($closing): void {
                    $fy = $this->fiscalYear;
                    $user = auth()->user();
                    if (! $fy || ! $user instanceof User) {
                        return;
                    }
                    try {
                        $closing->restore($fy, (int) $user->id, null);
                        Notification::make()->title(__('Restored'))->success()->send();
                    } catch (Throwable $e) {
                        Notification::make()
                            ->title(__('Restore failed'))
                            ->body($e->getMessage())
                            ->danger()
                            ->persistent()
                            ->send();
                    }
                }),
            Action::make('clear_closure_history')
                ->label(__('Clear closure history'))
                ->icon('heroicon-o-trash')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading(__('Clear all closure activity log?'))
                ->modalDescription(
                    __('This permanently deletes every row in the closure log (dry runs, close attempts, restores). Fiscal years and archived data are not removed.')
                )
                ->modalSubmitActionLabel(__('Clear log'))
                ->visible(fn (): bool => $this->canManageCritical())
                ->action(function (): void {
                    $count = FiscalYearClosure::query()->count();
                    FiscalYearClosure::query()->delete();
                    unset($this->recentClosures);
                    Notification::make()
                        ->title(__('Closure history cleared'))
                        ->body(
                            __('Removed :count record(s).', ['count' => $count])
                        )
                        ->success()
                        ->send();
                }),
        ];
    }

    public function canManageCritical(): bool
    {
        $u = auth()->user();
        if (! $u instanceof User) {
            return false;
        }
        $super = (string) config('filament-shield.super_admin.name', 'super_admin');

        return $u->hasRole($super);
    }
}
