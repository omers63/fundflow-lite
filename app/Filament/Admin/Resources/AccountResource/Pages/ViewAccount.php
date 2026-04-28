<?php

namespace App\Filament\Admin\Resources\AccountResource\Pages;

use App\Filament\Admin\Pages\PostedFundsPage;
use App\Filament\Admin\Resources\AccountResource;
use App\Models\Account;
use Filament\Actions\Action;
use App\Filament\Admin\Widgets\AccountDetailWidget;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Livewire\Attributes\On;

class ViewAccount extends ViewRecord
{
    protected static string $resource = AccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('postedFunds')
                ->label(__('Posted Funds'))
                ->icon('heroicon-o-banknotes')
                ->color('info')
                ->visible(fn(): bool => in_array($this->record->type, [Account::TYPE_MASTER_CASH, Account::TYPE_MEMBER_CASH], true))
                ->url(PostedFundsPage::getUrl(['account' => $this->record->getKey()])),
        ];
    }

    public function getTitle(): string
    {
        return $this->record->name;
    }

    public function getSubheading(): ?string
    {
        $record = $this->record;
        $parts = [$record->type_label];

        if ($record->member) {
            $parts[] = __('Member:') . ' ' . $record->member->user->name . ' (' . $record->member->member_number . ')';
        }
        if ($record->loan_id) {
            $parts[] = __('Loan #') . $record->loan_id;
        }

        return implode(' · ', $parts);
    }

    protected function getHeaderWidgets(): array
    {
        return [AccountDetailWidget::class];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }

    /**
     * Filament passes this array into header/footer widgets as Livewire properties.
     * (There is no getHeaderWidgetsData() hook — only getWidgetData().)
     */
    public function getWidgetData(): array
    {
        return [
            ...parent::getWidgetData(),
            'accountId' => $this->getRecord()->getKey(),
        ];
    }

    #[On('refresh-account-widgets')]
    public function refreshAccountRecordFromLedger(mixed $accountId): void
    {
        if ((int) $this->getRecord()->getKey() !== (int) $accountId) {
            return;
        }

        $this->getRecord()->refresh();
        $this->getRecord()->loadMissing(['member.user']);
    }

    public function infolist(Schema $schema): Schema
    {
        $record = $this->record;
        $balance = (float) $record->balance;

        return $schema->schema([
            Section::make(__('Account Details'))
                ->columnSpanFull()
                ->columns(3)
                ->schema([
                    TextEntry::make('name')
                        ->label(__('Account Name'))
                        ->weight(FontWeight::SemiBold),
                    TextEntry::make('type_label')
                        ->label(__('Type'))
                        ->badge()
                        ->color(fn() => $record->type_color),
                    TextEntry::make('is_active')
                        ->label(__('Status'))
                        ->formatStateUsing(fn($state) => $state ? __('Active') : __('Inactive'))
                        ->badge()
                        ->color(fn() => $record->is_active ? 'success' : 'danger'),
                    TextEntry::make('member.user.name')
                        ->label(__('Member Name'))
                        ->placeholder(__('—')),
                    TextEntry::make('member.member_number')
                        ->label(__('Member Number'))
                        ->placeholder(__('—')),
                    TextEntry::make('loan_id')
                        ->label(__('Loan #'))
                        ->placeholder(__('—')),
                    TextEntry::make('balance')
                        ->label(__('Current Balance (SAR)'))
                        ->money('SAR')
                        ->weight(FontWeight::Bold)
                        ->color(fn() => $balance >= 0 ? 'success' : 'danger'),
                    TextEntry::make('created_at')
                        ->label(__('Opened'))
                        ->dateTime('d M Y'),
                    TextEntry::make('updated_at')
                        ->label(__('Last Updated'))
                        ->dateTime('d M Y H:i'),
                ]),
        ]);
    }
}
