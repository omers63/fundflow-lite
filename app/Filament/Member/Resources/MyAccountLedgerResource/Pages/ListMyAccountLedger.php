<?php

namespace App\Filament\Member\Resources\MyAccountLedgerResource\Pages;

use App\Filament\Member\Resources\MyAccountLedgerResource;
use App\Models\Member;
use App\Services\AccountingService;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListMyAccountLedger extends ListRecords
{
    protected static string $resource = MyAccountLedgerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('transfer_funds')
                ->label(__('Transfer Funds'))
                ->icon('heroicon-o-arrows-right-left')
                ->color('warning')
                ->modalHeading(__('Transfer Funds to Another Member'))
                ->modalDescription(__('Move funds from your cash account to another member cash account.'))
                ->schema(function (): array {
                    $member = Member::query()
                        ->where('user_id', auth()->id())
                        ->first();

                    return [
                        Forms\Components\Placeholder::make('your_cash_balance')
                            ->label(__('Your Cash Balance'))
                            ->content(__('SAR :amount', ['amount' => number_format((float) ($member?->cash_balance ?? 0), 2)])),
                        Forms\Components\Select::make('recipient_member_id')
                            ->label(__('Recipient Member'))
                            ->required()
                            ->searchable()
                            ->options(fn() => Member::query()
                                ->where('id', '!=', $member?->id)
                                ->whereHas('user', fn($q) => $q
                                    ->where('status', 'approved')
                                    ->where('role', 'member'))
                                ->with('user')
                                ->orderBy('member_number')
                                ->limit(200)
                                ->get()
                                ->mapWithKeys(fn(Member $m) => [
                                    $m->id => trim(($m->member_number ? "{$m->member_number} — " : '') . ($m->user?->name ?? __('Member'))),
                                ])
                                ->all()),
                        Forms\Components\TextInput::make('amount')
                            ->label(__('Amount to Transfer (SAR)'))
                            ->required()
                            ->numeric()
                            ->minValue(0.01)
                            ->prefix(__('SAR')),
                        Forms\Components\TextInput::make('note')
                            ->label(__('Note (optional)'))
                            ->maxLength(200),
                    ];
                })
                ->action(function (array $data): void {
                    $from = Member::query()
                        ->where('user_id', auth()->id())
                        ->first();

                    if (!$from) {
                        Notification::make()->title(__('Your member record was not found.'))->danger()->send();

                        return;
                    }

                    $to = Member::query()
                        ->whereKey((int) ($data['recipient_member_id'] ?? 0))
                        ->with('user')
                        ->first();

                    if (!$to) {
                        Notification::make()->title(__('Recipient member was not found.'))->danger()->send();

                        return;
                    }

                    try {
                        app(AccountingService::class)->transferMemberCash(
                            from: $from,
                            to: $to,
                            amount: (float) $data['amount'],
                            note: is_string($data['note'] ?? null) ? $data['note'] : '',
                        );

                        Notification::make()
                            ->title(__('Transfer Successful'))
                            ->body(__(':currency :amount transferred to :name.', [
                                'currency' => __('SAR'),
                                'amount' => number_format((float) $data['amount'], 2),
                                'name' => $to->user?->name ?? __('Member'),
                            ]))
                            ->success()
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title(__('Transfer Failed'))
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }

    public function getTitle(): string
    {
        return __('My Ledger');
    }

    public function getSubheading(): ?string
    {
        return __('Full history of all credits and debits on your cash and fund accounts.');
    }
}
