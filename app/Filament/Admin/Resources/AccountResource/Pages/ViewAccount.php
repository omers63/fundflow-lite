<?php

namespace App\Filament\Admin\Resources\AccountResource\Pages;

use App\Filament\Admin\Resources\AccountResource;
use App\Models\Account;
use Filament\Forms;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

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

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Account Details')->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Account Name')
                    ->disabled(),
                Forms\Components\TextInput::make('type_label')
                    ->label('Type')
                    ->disabled(),
                Forms\Components\TextInput::make('member.user.name')
                    ->label('Member')
                    ->placeholder('—')
                    ->disabled(),
                Forms\Components\TextInput::make('member.member_number')
                    ->label('Member #')
                    ->placeholder('—')
                    ->disabled(),
                Forms\Components\TextInput::make('loan_id')
                    ->label('Loan #')
                    ->placeholder('—')
                    ->disabled(),
                Forms\Components\TextInput::make('balance')
                    ->label('Current Balance (SAR)')
                    ->prefix('SAR')
                    ->disabled(),
            ])->columns(3),
        ]);
    }
}
