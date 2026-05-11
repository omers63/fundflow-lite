<?php

namespace App\Filament\Resources\Tenants\Schemas;

use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TenantForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Tenant')
                    ->schema([
                        TextInput::make('id')
                            ->required()
                            ->alphaDash()
                            ->maxLength(64)
                            ->helperText('Unique tenant key, e.g. al-hassan'),
                        TextInput::make('name')->required(),
                        TextInput::make('slug')->required()->alphaDash(),
                        TextInput::make('tenancy_db_name')
                            ->label('Tenant DB name')
                            ->helperText('Optional. Auto-generated when empty.'),
                    ])->columns(2),
                Section::make('Domain')
                    ->schema([
                        TextInput::make('primary_domain')
                            ->label('Primary domain')
                            ->required(fn(string $operation): bool => $operation === 'create')
                            ->dehydrated(false)
                            ->helperText('Example: al-hassan.localhost'),
                    ]),
                Section::make('Provision admin user')
                    ->schema([
                        TextInput::make('admin_name')->required(fn(string $operation): bool => $operation === 'create')->dehydrated(false),
                        TextInput::make('admin_email')->email()->required(fn(string $operation): bool => $operation === 'create')->dehydrated(false),
                        TextInput::make('admin_password')->password()->required(fn(string $operation): bool => $operation === 'create')->dehydrated(false)->minLength(8),
                    ])->columns(2),
            ]);
    }
}
