<?php

namespace App\Filament\Admin\Resources\MemberResource\Pages;

use App\Filament\Admin\Resources\MemberResource;
use App\Models\Account;
use App\Models\Member;
use App\Services\MemberImportService;
use App\Filament\Admin\Widgets\MemberStatsWidget;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;

class ListMembers extends ListRecords
{
    protected static string $resource = MemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export_csv')
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(function () {
                    $filename = 'members-' . now()->format('Y-m-d') . '.csv';

                    return response()->streamDownload(function () {
                        $handle = fopen('php://output', 'w');
                        fputcsv($handle, [
                            'member_number', 'name', 'email', 'phone', 'status',
                            'joined_at', 'monthly_contribution_amount',
                            'cash_balance', 'fund_balance',
                            'late_contributions_count', 'late_repayment_count',
                        ]);

                        Member::with('user')
                            ->withSum(['accounts as cash_balance' => fn($q) => $q->where('type', Account::TYPE_MEMBER_CASH)], 'balance')
                            ->withSum(['accounts as fund_balance' => fn($q) => $q->where('type', Account::TYPE_MEMBER_FUND)], 'balance')
                            ->orderBy('member_number')
                            ->each(function (Member $m) use ($handle) {
                                fputcsv($handle, [
                                    $m->member_number,
                                    $m->user?->name,
                                    $m->user?->email,
                                    $m->user?->phone,
                                    $m->status,
                                    $m->joined_at?->toDateString(),
                                    $m->monthly_contribution_amount,
                                    number_format((float) $m->cash_balance, 2, '.', ''),
                                    number_format((float) $m->fund_balance, 2, '.', ''),
                                    $m->late_contributions_count,
                                    $m->late_repayment_count,
                                ]);
                            });

                        fclose($handle);
                    }, $filename, ['Content-Type' => 'text/csv']);
                }),

            Action::make('importMembers')
                ->label('Import Members')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('success')
                ->visible(fn(): bool => MemberResource::canCreate() || (bool) auth()->user()?->can('Update:Member'))
                ->modalHeading('Import members from CSV')
                ->modalDescription(
                    'First row must be headers. Required: email; name required for new members only (balance-only rows for existing emails may leave name blank). Optional: password, phone, joined_at, status, monthly_contribution_amount, parent_member_number, ' .
                    'cash_balance (≥ 0), fund_balance (may be negative — paired debit on master + member fund, e.g. master-funded loan). ' .
                    'Existing email: if the user already has a member, applies cash/fund adjustments only (other columns ignored); requires Update:Member. No member record → error. ' .
                    'New members require Create:Member. Parent rows before dependents. Status: active, suspended, delinquent, terminated. Contribution: 500–3000 in steps of 500.'
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
                ->action(function (array $data, Component $livewire): void {
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
                            $preview .= "\n… and " . (count($result['errors']) - 8) . ' more';
                        }
                        $body .= "\n\n" . $preview;
                    }

                    Notification::make()
                        ->title('Member import finished')
                        ->body($body)
                        ->color($result['failed'] > 0 || $result['errors'] !== [] ? 'warning' : 'success')
                        ->persistent()
                        ->send();

                    MemberResource::dispatchMemberListHeaderWidgetsRefresh($livewire);
                }),
            CreateAction::make()
                ->label('New Member')
                ->icon('heroicon-o-plus-circle')
                ->url(MemberResource::getUrl('create'))
                ->visible(fn(): bool => MemberResource::canCreate()),
        ];
    }

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
}
