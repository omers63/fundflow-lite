<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\BankResource\Pages;
use App\Models\Bank;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BankResource extends Resource
{
    protected static ?string $model = Bank::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?int $navigationSort = 10;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function getNavigationGroup(): ?string
    {
        return 'finance';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Bank Details')
                ->columnSpanFull()
                ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('code')
                    ->label(__('Bank Code'))
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(50)
                    ->helperText(__('Short identifier, e.g. ALRAJHI, RIYAD, ANB')),
                Forms\Components\TextInput::make('swift_code')
                    ->label(__('SWIFT / BIC Code'))
                    ->maxLength(20),
                Forms\Components\TextInput::make('account_number')
                    ->maxLength(100),
                Forms\Components\Toggle::make('is_active')
                    ->label(__('Active'))
                    ->default(true),
                Forms\Components\Textarea::make('notes')
                    ->rows(2)
                    ->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label(__('Name'))->searchable()->sortable(),
                Tables\Columns\TextColumn::make('code')->label(__('Code'))->badge()->sortable(),
                Tables\Columns\TextColumn::make('swift_code')->label(__('SWIFT'))->placeholder(__('—')),
                Tables\Columns\TextColumn::make('account_number')->label(__('Account Number'))->placeholder(__('—')),
                Tables\Columns\IconColumn::make('is_active')->label(__('Active'))->boolean(),
                Tables\Columns\TextColumn::make('import_templates_count')
                    ->label(__('Templates'))
                    ->counts('importTemplates'),
                Tables\Columns\TextColumn::make('import_sessions_count')
                    ->label(__('Imports'))
                    ->counts('importSessions'),
            ])
            ->defaultSort('name')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label(__('Active')),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    DeleteAction::make(),
                    RestoreAction::make(),
                    ForceDeleteAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBanks::route('/'),
            'create' => Pages\CreateBank::route('/create'),
            'edit' => Pages\EditBank::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->withTrashed();
    }
}
