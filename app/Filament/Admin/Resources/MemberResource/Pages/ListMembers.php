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
use Illuminate\Support\HtmlString;
use Livewire\Component;

class ListMembers extends ListRecords
{
    protected static string $resource = MemberResource::class;

    public function getTitle(): string
    {
        return __('Members');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export_csv')
                ->label(__('Export Members'))
                ->icon('heroicon-o-arrow-down-tray')
                ->color('warning')
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
                ->label(__('Import Members'))
                ->icon('heroicon-o-arrow-up-tray')
                ->color('success')
                ->visible(fn(): bool => MemberResource::canCreate() || (bool) auth()->user()?->can('Update:Member'))
                ->modalHeading(__('Import members from CSV'))
                ->modalDescription(new HtmlString(
                    '<div class="space-y-3 text-sm">' .
                        '<div class="rounded-lg border border-blue-200 bg-blue-50/80 p-3 text-xs dark:border-blue-500/30 dark:bg-blue-500/10">' .
                            '<p class="font-semibold text-blue-900 dark:text-blue-200 mb-1">' . e(__('Need a starter file?')) . '</p>' .
                            '<p class="text-blue-900/90 dark:text-blue-100/90">' .
                                e(__('Download a ready sample with 20 varied rows (including optional fields): ')) .
                                '<a href="' . route('downloads.member-import-sample') . '" class="font-semibold text-blue-700 underline hover:text-blue-600 dark:text-blue-300 dark:hover:text-blue-200">members-import-sample-20.csv</a>' .
                            '</p>' .
                        '</div>' .
                        '<div class="rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">' .
                            '<table class="w-full text-xs">' .
                                '<tbody class="divide-y divide-gray-100 dark:divide-gray-800">' .
                                    '<tr>' .
                                        '<td class="px-3 py-2 font-semibold text-gray-700 dark:text-gray-200 w-44 bg-gray-50 dark:bg-gray-900/30">' . e(__('CSV format')) . '</td>' .
                                        '<td class="px-3 py-2 text-gray-600 dark:text-gray-300">' . e(__('First row must be headers.')) . '</td>' .
                                    '</tr>' .
                                    '<tr>' .
                                        '<td class="px-3 py-2 font-semibold text-gray-700 dark:text-gray-200 bg-gray-50 dark:bg-gray-900/30">' . e(__('Required fields')) . '</td>' .
                                        '<td class="px-3 py-2 text-gray-600 dark:text-gray-300">' . e(__('email (always), name (required for new members only).')) . '</td>' .
                                    '</tr>' .
                                    '<tr>' .
                                        '<td class="px-3 py-2 font-semibold text-gray-700 dark:text-gray-200 bg-gray-50 dark:bg-gray-900/30">' . e(__('Optional fields')) . '</td>' .
                                        '<td class="px-3 py-2 text-gray-600 dark:text-gray-300">' . e(__('password, phone, joined_at, status, monthly_contribution_amount, parent_member_number, cash_balance, fund_balance.')) . '</td>' .
                                    '</tr>' .
                                    '<tr>' .
                                        '<td class="px-3 py-2 font-semibold text-gray-700 dark:text-gray-200 bg-gray-50 dark:bg-gray-900/30">' . e(__('Balance rules')) . '</td>' .
                                        '<td class="px-3 py-2 text-gray-600 dark:text-gray-300">' . e(__('cash_balance must be >= 0. fund_balance may be negative (paired debit on master + member fund).')) . '</td>' .
                                    '</tr>' .
                                    '<tr>' .
                                        '<td class="px-3 py-2 font-semibold text-gray-700 dark:text-gray-200 bg-gray-50 dark:bg-gray-900/30">' . e(__('Existing email')) . '</td>' .
                                        '<td class="px-3 py-2 text-gray-600 dark:text-gray-300">' . e(__('If user already has a member, only cash/fund adjustments are applied; other columns are ignored. Requires Update:Member. If no member record exists, import fails for that row.')) . '</td>' .
                                    '</tr>' .
                                    '<tr>' .
                                        '<td class="px-3 py-2 font-semibold text-gray-700 dark:text-gray-200 bg-gray-50 dark:bg-gray-900/30">' . e(__('New member')) . '</td>' .
                                        '<td class="px-3 py-2 text-gray-600 dark:text-gray-300">' . e(__('Requires Create:Member. Place parent rows before dependents.')) . '</td>' .
                                    '</tr>' .
                                    '<tr>' .
                                        '<td class="px-3 py-2 font-semibold text-gray-700 dark:text-gray-200 bg-gray-50 dark:bg-gray-900/30">' . e(__('Allowed values')) . '</td>' .
                                        '<td class="px-3 py-2 text-gray-600 dark:text-gray-300">' . e(__('status: active, suspended, delinquent, terminated. monthly_contribution_amount: 500 to 3000 in steps of 500.')) . '</td>' .
                                    '</tr>' .
                                '</tbody>' .
                            '</table>' .
                        '</div>' .
                    '</div>'
                ))
                ->modalWidth('2xl')
                ->schema([
                    Forms\Components\FileUpload::make('csv_file')
                        ->label(__('CSV file'))
                        ->disk('local')
                        ->directory('member-imports')
                        ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'])
                        ->required(),
                    Forms\Components\TextInput::make('default_password')
                        ->label(__('Default password'))
                        ->password()
                        ->revealable()
                        ->required()
                        ->minLength(8)
                        ->helperText(__('Used when the password column is empty or shorter than 8 characters. Members should change it after first login.')),
                ])
                ->action(function (array $data, Component $livewire): void {
                    $relative = $data['csv_file'];
                    $fullPath = Storage::disk('local')->path($relative);

                    try {
                        $result = app(MemberImportService::class)->import($fullPath, $data['default_password']);
                    } finally {
                        Storage::disk('local')->delete($relative);
                    }

                    $body = __('Created: :created · Updated (balances): :updated · Skipped: :skipped · Failed: :failed', [
                        'created' => $result['created'],
                        'updated' => $result['updated'],
                        'skipped' => $result['skipped'],
                        'failed' => $result['failed'],
                    ]);

                    if ($result['errors'] !== []) {
                        $preview = implode("\n", array_slice($result['errors'], 0, 8));
                        if (count($result['errors']) > 8) {
                            $preview .= "\n" . __('... and :count more', ['count' => count($result['errors']) - 8]);
                        }
                        $body .= "\n\n" . $preview;
                    }

                    Notification::make()
                        ->title(__('Member import finished'))
                        ->body($body)
                        ->color($result['failed'] > 0 || $result['errors'] !== [] ? 'warning' : 'success')
                        ->persistent()
                        ->send();

                    MemberResource::dispatchMemberListHeaderWidgetsRefresh($livewire);
                }),
            CreateAction::make()
                ->label(__('New Member'))
                ->icon('heroicon-o-plus-circle')
                ->url(MemberResource::getUrl('create'))
                ->visible(fn(): bool => MemberResource::canCreate()),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return $this->availableWidgets();
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }

    public function getSubheading(): ?string
    {
        return __('Manage all fund members — review statuses, contribution commitments, and loan activity.');
    }

    /**
     * @return array<class-string>
     */
    private function availableWidgets(): array
    {
        return [MemberStatsWidget::class];
    }
}
