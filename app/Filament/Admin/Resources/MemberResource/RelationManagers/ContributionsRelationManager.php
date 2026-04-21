<?php

namespace App\Filament\Admin\Resources\MemberResource\RelationManagers;

use App\Filament\Admin\Resources\ContributionResource;
use App\Filament\Admin\Resources\MemberResource;
use App\Filament\Admin\Resources\MemberResource\Concerns\InteractsWithMemberCycleHeaderActions;
use App\Models\Contribution;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Livewire\Component;

class ContributionsRelationManager extends RelationManager
{
    use InteractsWithMemberCycleHeaderActions;

    protected static string $relationship = 'contributions';

    protected static ?string $title = null;

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('Contributions');
    }

    /**
     * Allow view/edit/delete on member View pages even when the panel defaults
     * to read-only relation managers.
     */
    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return ContributionResource::form($schema);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->striped()
            ->headerActions([
                $this->contributeCycleHeaderAction(),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('year')->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('month')
                    ->formatStateUsing(fn ($state) => date('F', mktime(0, 0, 0, $state, 1)))
                    ->toggleable(),
                Tables\Columns\TextColumn::make('amount')->money('SAR')->toggleable(),
                Tables\Columns\BadgeColumn::make('payment_method')
                    ->label(__('Source'))
                    ->formatStateUsing(fn (?string $state): string => Contribution::paymentMethodLabel($state))
                    ->toggleable(),
                Tables\Columns\TextColumn::make('reference_number')->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('paid_at')->label(__('Paid On'))
                    ->dateTime('d M Y')->sortable()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_late')
                    ->label(__('Late'))
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->falseIcon('heroicon-o-check-circle')
                    ->trueColor('warning')
                    ->falseColor('success')
                    ->toggleable(),
            ])
            ->defaultSort('paid_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('month')
                    ->options(array_combine(range(1, 12), array_map(fn ($m) => date('F', mktime(0, 0, 0, $m, 1)), range(1, 12)))),
                Tables\Filters\Filter::make('year')
                    ->schema([Forms\Components\TextInput::make('year')->numeric()->default(now()->year)])
                    ->query(fn ($query, $data) => ($data['year'] ?? null) ? $query->where('year', $data['year']) : $query),
                Tables\Filters\SelectFilter::make('payment_method')
                    ->label(__('Source'))
                    ->options(fn (): array => Contribution::paymentMethodOptions()),
                Tables\Filters\TernaryFilter::make('is_late')
                    ->label(__('Late payment'))
                    ->trueLabel(__('Late only'))
                    ->falseLabel(__('On-time only')),
                Tables\Filters\Filter::make('paid_at')
                    ->schema([
                        Forms\Components\DatePicker::make('paid_from')->label(__('Paid from')),
                        Forms\Components\DatePicker::make('paid_until')->label(__('Paid until')),
                    ])
                    ->columns(2)
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['paid_from'] ?? null, fn ($q) => $q->whereDate('paid_at', '>=', $data['paid_from']))
                            ->when($data['paid_until'] ?? null, fn ($q) => $q->whereDate('paid_at', '<=', $data['paid_until']));
                    }),
                Tables\Filters\Filter::make('amount')
                    ->schema([
                        Forms\Components\TextInput::make('amount_min')->label(__('Min amount (SAR)'))->numeric(),
                        Forms\Components\TextInput::make('amount_max')->label(__('Max amount (SAR)'))->numeric(),
                    ])
                    ->columns(2)
                    ->query(function ($query, array $data) {
                        return $query
                            ->when(filled($data['amount_min'] ?? null), fn ($q) => $q->where('amount', '>=', $data['amount_min']))
                            ->when(filled($data['amount_max'] ?? null), fn ($q) => $q->where('amount', '<=', $data['amount_max']));
                    }),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make()
                        ->modalWidth('2xl'),
                    EditAction::make()
                        ->modalWidth('2xl')
                        ->after(function (Component $livewire): void {
                            MemberResource::dispatchMemberRecordHeaderWidgetsRefresh($livewire);
                        }),
                    DeleteAction::make()
                        ->modalDescription(__('Soft-deletes this contribution and reverses its fund ledger postings (master + member fund). Restoring re-posts the contribution to the ledger.'))
                        ->after(function (Component $livewire): void {
                            MemberResource::dispatchMemberRecordHeaderWidgetsRefresh($livewire);
                        }),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->after(function (Component $livewire): void {
                            MemberResource::dispatchMemberRecordHeaderWidgetsRefresh($livewire);
                        }),
                ]),
            ]);
    }
}
