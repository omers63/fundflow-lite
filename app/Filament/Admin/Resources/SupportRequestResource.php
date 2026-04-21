<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\SupportRequestResource\Pages;
use App\Models\SupportRequest;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;

class SupportRequestResource extends Resource
{
    protected static ?string $model = SupportRequest::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationLabel = 'Support';

    protected static ?string $modelLabel = 'Support request';

    protected static ?string $pluralModelLabel = 'Support requests';

    protected static ?int $navigationSort = 6;

    public static function getNavigationLabel(): string
    {
        return __('Support');
    }

    public static function getNavigationGroup(): ?string
    {
        return 'membership';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(static fn (): Builder => SupportRequest::query())
            ->summaries(false, false)
            ->modifyQueryUsing(
                fn (Builder $q): Builder => $q->with([
                    'user',
                    'member',
                ])
            )
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label(__('ID'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('member.member_number')
                    ->label(__('Member #'))
                    ->placeholder('—')
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label(__('Submitted by'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('category')
                    ->label(__('Category'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => SupportRequest::categoryLabel($state)),
                Tables\Columns\TextColumn::make('subject')
                    ->label(__('Subject'))
                    ->searchable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('message')
                    ->label(__('Message'))
                    ->limit(60)
                    ->tooltip(fn (SupportRequest $record): string => $record->message)
                    ->wrap(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Submitted'))
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->options(SupportRequest::categoryOptions()),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                ActionGroup::make([
                    Action::make('view_message')
                        ->label(__('View'))
                        ->icon('heroicon-o-eye')
                        ->color('gray')
                        ->modalHeading(fn (SupportRequest $record): string => __('Support request #:id', ['id' => $record->id]))
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel(__('Close'))
                        ->modalContent(fn (SupportRequest $record): View => view(
                            'filament.admin.components.support-request-detail',
                            ['record' => $record],
                        )),
                    DeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->authorizeIndividualRecords('delete'),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSupportRequests::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
