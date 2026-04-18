<?php

namespace App\Filament\Admin\Resources\MembershipApplicationResource\Pages;

use App\Filament\Admin\Resources\MembershipApplicationResource;
use App\Filament\Admin\Widgets\ApplicationStatsWidget;
use App\Services\MembershipApplicationImportService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Livewire\Component;

class ListMembershipApplications extends ListRecords
{
    protected static string $resource = MembershipApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('importApplications')
                ->label('Import Applications')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('success')
                ->visible(fn(): bool => MembershipApplicationResource::canCreate() || (bool) auth()->user()?->can('Update:MembershipApplication'))
                ->modalHeading('Import applications from CSV')
                ->modalDescription(fn(): HtmlString => new HtmlString(
                    view('filament.admin.membership-application-import-csv-help')->render()
                ))
                ->modalWidth('2xl')
                ->schema([
                    Forms\Components\FileUpload::make('csv_file')
                        ->label('CSV file')
                        ->disk('local')
                        ->directory('membership-application-imports')
                        ->maxFiles(1)
                        // Avoid acceptedFileTypes / extension / MIME rules: Livewire temp names and odd client extensions break them; the importer validates CSV content.
                        ->helperText('Upload comma-separated data (typical .csv). If parsing fails, the importer will show detailed row errors.')
                        ->required(),
                    Forms\Components\TextInput::make('default_password')
                        ->label('Default password')
                        ->password()
                        ->revealable()
                        ->required()
                        ->minLength(8)
                        ->helperText('Used when the password column is empty or shorter than 8 characters. Applicants should change it after first login.'),
                ])
                ->action(function (array $data, Component $livewire): void {
                    // With stored files (default), Filament returns a path relative to the local disk — same as member CSV import.
                    $relative = $data['csv_file'] ?? null;
                    if (is_array($relative)) {
                        $relative = $relative[0] ?? null;
                    }

                    if (!is_string($relative) || trim($relative) === '') {
                        logger()->warning('Applications import: missing or invalid csv_file path', [
                            'payload_type' => gettype($data['csv_file'] ?? null),
                            'payload_keys' => is_array($data) ? array_keys($data) : [],
                        ]);

                        Notification::make()
                            ->title('Import failed')
                            ->body('No uploaded CSV file was received. Please re-select the file and try again.')
                            ->danger()
                            ->send();

                        return;
                    }

                    $fullPath = Storage::disk('local')->path($relative);

                    if (!is_file($fullPath)) {
                        logger()->warning('Applications import: stored file path does not exist', [
                            'relative' => $relative,
                            'full_path' => $fullPath,
                        ]);

                        Notification::make()
                            ->title('Import failed')
                            ->body('Uploaded file could not be found on server. Please upload again and try again.')
                            ->danger()
                            ->send();

                        return;
                    }

                    try {
                        $result = app(MembershipApplicationImportService::class)->import($fullPath, $data['default_password']);
                    } catch (\Throwable $e) {
                        report($e);

                        Notification::make()
                            ->title('Import failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->persistent()
                            ->send();

                        return;
                    } finally {
                        Storage::disk('local')->delete($relative);
                    }

                    $body = "Created: {$result['created']} · Skipped: {$result['skipped']} · Failed: {$result['failed']}";

                    if ($result['errors'] !== []) {
                        $preview = implode("\n", array_slice($result['errors'], 0, 8));
                        if (count($result['errors']) > 8) {
                            $preview .= "\n… and " . (count($result['errors']) - 8) . ' more';
                        }
                        $body .= "\n\n" . $preview;
                    }

                    Notification::make()
                        ->title('Application import finished')
                        ->body($body)
                        ->color($result['failed'] > 0 || $result['errors'] !== [] ? 'warning' : 'success')
                        ->persistent()
                        ->send();

                    MembershipApplicationResource::dispatchApplicationStatsRefresh($livewire);
                }),
            CreateAction::make()
                ->label('New Application')
                ->icon('heroicon-o-plus-circle')
                ->url(MembershipApplicationResource::getUrl('create'))
                ->visible(fn(): bool => MembershipApplicationResource::canCreate()),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [ApplicationStatsWidget::class];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }

    public function getSubheading(): ?string
    {
        return 'Review new membership applications, track approval rates, and manage the onboarding pipeline.';
    }
}
