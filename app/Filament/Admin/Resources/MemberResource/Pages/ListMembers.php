<?php

namespace App\Filament\Admin\Resources\MemberResource\Pages;

use App\Filament\Admin\Resources\MemberResource;
use App\Filament\Admin\Widgets\MemberStatsWidget;
use App\Services\MemberImportService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;

class ListMembers extends ListRecords
{
    protected static string $resource = MemberResource::class;

    protected function getHeaderWidgets(): array
    {
        return [MemberStatsWidget::class];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }

    public function getSubheading(): ?string
    {
        return 'Manage all fund members — review statuses, contribution commitments, and loan activity.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('importMembers')
                ->label('Import CSV')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('success')
                ->visible(fn (): bool => MemberResource::canCreate() || (bool) auth()->user()?->can('Update:Member'))
                ->modalHeading('Import members from CSV')
                ->modalDescription(
                    'First row must be headers. Required: email; name required for new members only (balance-only rows for existing emails may leave name blank). Optional: password, phone, joined_at, status, monthly_contribution_amount, parent_member_number, '.
                    'cash_balance (≥ 0), fund_balance (may be negative — paired debit on master + member fund, e.g. master-funded loan). '.
                    'Existing email: if the user already has a member, applies cash/fund adjustments only (other columns ignored); requires Update:Member. No member record → error. '.
                    'New members require Create:Member. Parent rows before dependents. Status: active, suspended, delinquent. Contribution: 500–3000 in steps of 500.'
                )
                ->modalWidth('2xl')
                ->schema([
                    Forms\Components\FileUpload::make('csv_file')
                        ->label('CSV file')
                        ->disk('local')
                        ->directory('member-imports')
                        ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'])
                        ->required(),
                    Forms\Components\TextInput::make('default_password')
                        ->label('Default password')
                        ->password()
                        ->revealable()
                        ->required()
                        ->minLength(8)
                        ->helperText('Used when the password column is empty or shorter than 8 characters. Members should change it after first login.'),
                ])
                ->action(function (array $data): void {
                    $relative = $data['csv_file'];
                    $fullPath = Storage::disk('local')->path($relative);

                    try {
                        $result = app(MemberImportService::class)->import($fullPath, $data['default_password']);
                    } finally {
                        Storage::disk('local')->delete($relative);
                    }

                    $body = "Created: {$result['created']} · Updated (balances): {$result['updated']} · Skipped: {$result['skipped']} · Failed: {$result['failed']}";

                    if ($result['errors'] !== []) {
                        $preview = implode("\n", array_slice($result['errors'], 0, 8));
                        if (count($result['errors']) > 8) {
                            $preview .= "\n… and ".(count($result['errors']) - 8).' more';
                        }
                        $body .= "\n\n".$preview;
                    }

                    Notification::make()
                        ->title('Member import finished')
                        ->body($body)
                        ->color($result['failed'] > 0 || $result['errors'] !== [] ? 'warning' : 'success')
                        ->persistent()
                        ->send();
                }),
            CreateAction::make()->icon('heroicon-o-plus-circle'),
        ];
    }
}
