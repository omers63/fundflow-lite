<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\LoanTiersResource\Pages;
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

class LoanTiersResource extends Resource
{
    protected static ?string $model = LoanTier::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-queue-list';

    protected static ?string $navigationLabel = 'Loan Tiers';

    protected static ?int $navigationSort = 2;

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
                Forms\Components\TextInput::make('tier_number')->label('Tier #')->numeric()->required()->minValue(1)->maxValue(20),
                Forms\Components\TextInput::make('label')->label('Label')->maxLength(100)->placeholder('e.g. Tier 1'),
                Forms\Components\TextInput::make('min_amount')->label('Min Amount (SAR)')->numeric()->prefix('SAR')->required(),
                Forms\Components\TextInput::make('max_amount')->label('Max Amount (SAR)')->numeric()->prefix('SAR')->required(),
                Forms\Components\TextInput::make('min_monthly_installment')->label('Min Monthly Installment (SAR)')->numeric()->prefix('SAR')->required(),
                Forms\Components\Toggle::make('is_active')->label('Active')->default(true),
            ])->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('tier_number')->label('Tier')->sortable(),
                Tables\Columns\TextColumn::make('label')->label('Label'),
                Tables\Columns\TextColumn::make('min_amount')->label('Min Amount')->money('SAR'),
                Tables\Columns\TextColumn::make('max_amount')->label('Max Amount')->money('SAR'),
                Tables\Columns\TextColumn::make('min_monthly_installment')->label('Min Installment/mo')->money('SAR'),
                Tables\Columns\TextColumn::make('active_loans_count')->label('Active Loans')->getStateUsing(fn(LoanTier $r) => $r->active_loans_count),
                Tables\Columns\IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->defaultSort('tier_number')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('Active'),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
                ForceDeleteAction::make(),
            ])
            ->reorderable('tier_number');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLoanTiers::route('/'),
            'create' => Pages\CreateLoanTier::route('/create'),
            'edit' => Pages\EditLoanTier::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->withTrashed();
    }
}
