<?php

namespace App\Filament\Admin\Resources\AccountResource\Pages;

use App\Filament\Admin\Resources\AccountResource;
use App\Filament\Admin\Widgets\AccountDetailWidget;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;

class ViewAccount extends ViewRecord
{
    protected static string $resource = AccountResource::class;

    protected function getHeaderActions(): array
    {
        return [];
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
            $parts[] = 'Member: ' . $record->member->user->name . ' (' . $record->member->member_number . ')';
        }
        if ($record->loan_id) {
            $parts[] = 'Loan #' . $record->loan_id;
        }

        return implode(' · ', $parts);
    }

    protected function getHeaderWidgets(): array
    {
        return [AccountDetailWidget::class];
    }

    protected function getHeaderWidgetsData(): array
    {
        return [
            AccountDetailWidget::class => ['accountId' => $this->record->id],
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        $record = $this->record;
        $balance = (float) $record->balance;

        return $schema->schema([
            Section::make('Account Details')
                ->columns(3)
                ->schema([
                    TextEntry::make('name')
                        ->label('Account Name')
                        ->weight(FontWeight::SemiBold),
                    TextEntry::make('type_label')
                        ->label('Type')
                        ->badge()
                        ->color(fn() => $record->type_color),
                    TextEntry::make('is_active')
                        ->label('Status')
                        ->formatStateUsing(fn($state) => $state ? 'Active' : 'Inactive')
                        ->badge()
                        ->color(fn() => $record->is_active ? 'success' : 'danger'),
                    TextEntry::make('member.user.name')
                        ->label('Member Name')
                        ->placeholder('—'),
                    TextEntry::make('member.member_number')
                        ->label('Member Number')
                        ->placeholder('—'),
                    TextEntry::make('loan_id')
                        ->label('Loan #')
                        ->placeholder('—'),
                    TextEntry::make('balance')
                        ->label('Current Balance (SAR)')
                        ->money('SAR')
                        ->weight(FontWeight::Bold)
                        ->color(fn() => $balance >= 0 ? 'success' : 'danger'),
                    TextEntry::make('created_at')
                        ->label('Opened')
                        ->dateTime('d M Y'),
                    TextEntry::make('updated_at')
                        ->label('Last Updated')
                        ->dateTime('d M Y H:i'),
                ]),
        ]);
    }
}
