<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\MemberResource\Pages;
use App\Filament\Admin\Resources\MemberResource\RelationManagers\AccountsRelationManager;
use App\Filament\Admin\Resources\MemberResource\RelationManagers\ContributionsRelationManager;
use App\Filament\Admin\Resources\MemberResource\RelationManagers\DependentsRelationManager;
use App\Filament\Admin\Resources\MemberResource\RelationManagers\LoansRelationManager;
use App\Models\Member;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class MemberResource extends Resource
{
    protected static ?string $model = Member::class;
    protected static string|\BackedEnum|null $navigationIcon = null;
    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): ?string
    {
        return __('app.nav.group.membership');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Member Details')
                ->schema([
                    Forms\Components\TextInput::make('member_number')
                        ->disabled(),
                    Forms\Components\Select::make('status')
                        ->options([
                            'active'     => 'Active',
                            'suspended'  => 'Suspended',
                            'delinquent' => 'Delinquent',
                        ])
                        ->required(),
                    Forms\Components\DatePicker::make('joined_at')
                        ->label('Join Date')
                        ->disabled(),
                ])->columns(3),

            Section::make('User Information')
                ->schema([
                    Forms\Components\TextInput::make('user.name')->label('Name')->disabled(),
                    Forms\Components\TextInput::make('user.email')->label('Email')->disabled(),
                    Forms\Components\TextInput::make('user.phone')->label('Phone')->disabled(),
                ])->columns(3),

            Section::make('Contribution & Sponsorship')
                ->schema([
                    Forms\Components\Select::make('monthly_contribution_amount')
                        ->label('Monthly Contribution Amount')
                        ->options(Member::contributionAmountOptions())
                        ->default(500)
                        ->required()
                        ->helperText('Multiples of SAR 500, from SAR 500 to SAR 3,000.'),
                    Forms\Components\Select::make('parent_id')
                        ->label('Parent Member (Sponsor)')
                        ->options(fn (?Member $record) => Member::with('user')
                            ->whereNull('parent_id')
                            ->when($record, fn ($q) => $q->where('id', '!=', $record->id))
                            ->get()
                            ->mapWithKeys(fn ($m) => [$m->id => "{$m->member_number} – {$m->user->name}"]))
                        ->searchable()
                        ->nullable()
                        ->placeholder('None (independent member)')
                        ->disabled(fn (?Member $record) => $record && $record->dependents()->exists())
                        ->helperText(fn (?Member $record) => $record && $record->dependents()->exists()
                            ? 'This member has dependents and cannot be assigned a parent.'
                            : 'The parent member can fund this member\'s cash account.'),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('member_number')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.email')
                    ->label('Email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('user.phone')
                    ->label('Phone'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'active' => 'success',
                        'suspended' => 'warning',
                        'delinquent' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('joined_at')
                    ->date('d M Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('monthly_contribution_amount')
                    ->label('Monthly Alloc.')
                    ->money('SAR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('parent.user.name')
                    ->label('Parent')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('contributions_sum_amount')
                    ->label('Total Contributions')
                    ->money('SAR')
                    ->sortable()
                    ->getStateUsing(fn ($record) => $record->contributions()->sum('amount')),
                Tables\Columns\TextColumn::make('late_contributions_count')
                    ->label('Late #')
                    ->sortable()
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'warning' : 'success'),
                Tables\Columns\TextColumn::make('late_contributions_amount')
                    ->label('Late Amount')
                    ->money('SAR')
                    ->sortable()
                    ->color(fn ($state) => $state > 0 ? 'warning' : 'gray'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(['active' => 'Active', 'suspended' => 'Suspended', 'delinquent' => 'Delinquent']),
            ])
            ->recordActions([
                EditAction::make(),
                ViewAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ContributionsRelationManager::class,
            LoansRelationManager::class,
            AccountsRelationManager::class,
            DependentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListMembers::route('/'),
            'create' => Pages\CreateMember::route('/create'),
            'edit'   => Pages\EditMember::route('/{record}/edit'),
            'view'   => Pages\ViewMember::route('/{record}'),
        ];
    }
}
