<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\FundTiersResource\Pages;
use App\Models\Account;
use App\Models\FundTier;
use App\Models\LoanTier;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FundTiersResource extends Resource
{
    protected static ?string $model = FundTier::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationLabel = 'Fund Tiers';

    protected static ?int $navigationSort = 3;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function getNavigationGroup(): ?string
    {
        return __('app.nav.group.settings');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make()->schema([
                Forms\Components\TextInput::make('tier_number')->label('Tier #')->numeric()->required()->minValue(0)->maxValue(20),
                Forms\Components\TextInput::make('label')->label('Label')->maxLength(100)->placeholder('e.g. Emergency'),
                Forms\Components\Select::make('loan_tier_id')
                    ->label('Linked Loan Tier')
                    ->options(LoanTier::all()->pluck('label', 'id'))
                    ->nullable()
                    ->placeholder('Emergency (standalone)'),
                Forms\Components\TextInput::make('percentage')
                    ->label('% of Master Fund')
                    ->numeric()
                    ->suffix('%')
                    ->minValue(1)
                    ->maxValue(100)
                    ->default(100)
                    ->required(),
                Forms\Components\Toggle::make('is_active')->label('Active')->default(true),
            ])->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        $masterBalance = (float) (Account::masterFund()?->balance ?? 0);

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('tier_number')->label('Fund Tier')->sortable(),
                Tables\Columns\TextColumn::make('label')->label('Label'),
                Tables\Columns\TextColumn::make('loanTier.label')->label('Loan Tier')->placeholder('Emergency'),
                Tables\Columns\TextColumn::make('percentage')->label('% Allocation')->suffix('%'),
                Tables\Columns\TextColumn::make('allocated_amount')
                    ->label('Allocated (SAR)')
                    ->money('SAR')
                    ->getStateUsing(fn(FundTier $r) => $r->allocated_amount),
                Tables\Columns\TextColumn::make('active_exposure')
                    ->label('Active Loans (SAR)')
                    ->money('SAR')
                    ->getStateUsing(fn(FundTier $r) => $r->active_exposure),
                Tables\Columns\TextColumn::make('available_amount')
                    ->label('Available (SAR)')
                    ->money('SAR')
                    ->getStateUsing(fn(FundTier $r) => $r->available_amount)
                    ->color(fn(FundTier $r) => $r->available_amount > 0 ? 'success' : 'danger'),
                Tables\Columns\TextColumn::make('active_loans_count')
                    ->label('Active Loans #')
                    ->getStateUsing(fn(FundTier $r) => $r->active_loans_count),
                Tables\Columns\IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->defaultSort('tier_number')
            ->filters([
                Tables\Filters\SelectFilter::make('loan_tier_id')
                    ->label('Linked loan tier')
                    ->relationship('loanTier', 'label')
                    ->searchable()
                    ->preload(),
                Tables\Filters\TernaryFilter::make('is_active')->label('Active'),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
                ForceDeleteAction::make(),
            ])
            ->description('Master Fund Balance: SAR ' . number_format($masterBalance, 2));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFundTiers::route('/'),
            'create' => Pages\CreateFundTier::route('/create'),
            'edit' => Pages\EditFundTier::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->withTrashed();
    }
}
