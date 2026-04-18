<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\MemberRequestResource\Pages;
use App\Models\MemberRequest;
use App\Services\MemberRequestService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Validation\ValidationException;

class MemberRequestResource extends Resource
{
    protected static ?string $model = MemberRequest::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'Requests';

    protected static ?string $modelLabel = 'Member request';

    protected static ?string $pluralModelLabel = 'Member requests';

    protected static ?int $navigationSort = 5;

    public static function getNavigationGroup(): ?string
    {
        return __('app.nav.group.membership');
    }

    public static function getNavigationBadge(): ?string
    {
        $n = MemberRequest::query()->where('status', MemberRequest::STATUS_PENDING)->count();

        return $n > 0 ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(static fn (): Builder => MemberRequest::query())
            ->summaries(false, false)
            ->modifyQueryUsing(
                fn (Builder $q): Builder => $q->with([
                    'requester.user',
                    'reviewedBy',
                ])
            )
            ->columns([
                Tables\Columns\TextColumn::make('requester.member_number')
                    ->label('Member #')
                    ->sortable(),
                Tables\Columns\TextColumn::make('requester.user.name')
                    ->label('Member')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => MemberRequest::typeLabel($state)),
                Tables\Columns\TextColumn::make('details_display')
                    ->label('Details')
                    ->getStateUsing(fn (MemberRequest $record): string => $record->describePayload())
                    ->wrap()
                    ->limit(80),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        MemberRequest::STATUS_PENDING => 'warning',
                        MemberRequest::STATUS_APPROVED => 'success',
                        MemberRequest::STATUS_REJECTED => 'danger',
                        MemberRequest::STATUS_CANCELLED => 'gray',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Submitted')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('reviewedBy.name')
                    ->label('Reviewed by')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('reviewed_at')
                    ->dateTime('d M Y H:i')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        MemberRequest::STATUS_PENDING => 'Pending',
                        MemberRequest::STATUS_APPROVED => 'Approved',
                        MemberRequest::STATUS_REJECTED => 'Rejected',
                        MemberRequest::STATUS_CANCELLED => 'Cancelled',
                    ]),
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        MemberRequest::TYPE_ADD_DEPENDENT => MemberRequest::typeLabel(MemberRequest::TYPE_ADD_DEPENDENT),
                        MemberRequest::TYPE_REMOVE_DEPENDENT => MemberRequest::typeLabel(MemberRequest::TYPE_REMOVE_DEPENDENT),
                        MemberRequest::TYPE_OWN_ALLOCATION => MemberRequest::typeLabel(MemberRequest::TYPE_OWN_ALLOCATION),
                        MemberRequest::TYPE_DEPENDENT_ALLOCATION => MemberRequest::typeLabel(MemberRequest::TYPE_DEPENDENT_ALLOCATION),
                        MemberRequest::TYPE_REQUEST_INDEPENDENCE => MemberRequest::typeLabel(MemberRequest::TYPE_REQUEST_INDEPENDENCE),
                    ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                ActionGroup::make([
                    Action::make('view_payload')
                        ->label('Details')
                        ->icon('heroicon-o-eye')
                        ->color('gray')
                        ->modalHeading('Request payload')
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Close')
                        ->modalContent(fn (MemberRequest $record): View => view(
                            'filament.admin.components.member-request-payload',
                            ['record' => $record],
                        )),
                    Action::make('approve')
                        ->label('Approve')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(fn (MemberRequest $record): bool => $record->isPending())
                        ->requiresConfirmation()
                        ->modalHeading('Approve this request?')
                        ->modalDescription('The change will be applied immediately for supported request types.')
                        ->action(function (MemberRequest $record): void {
                            try {
                                app(MemberRequestService::class)->approve($record, auth()->user());
                                Notification::make()->title('Request approved')->success()->send();
                            } catch (ValidationException $e) {
                                Notification::make()
                                    ->title('Cannot approve')
                                    ->body(collect($e->errors())->flatten()->first() ?? $e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),
                    Action::make('reject')
                        ->label('Reject')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->visible(fn (MemberRequest $record): bool => $record->isPending())
                        ->schema([
                            Forms\Components\Textarea::make('admin_note')
                                ->label('Note to member (optional)')
                                ->rows(3)
                                ->maxLength(2000),
                        ])
                        ->action(function (MemberRequest $record, array $data): void {
                            app(MemberRequestService::class)->reject(
                                $record,
                                auth()->user(),
                                $data['admin_note'] ?? null,
                            );
                            Notification::make()->title('Request rejected')->success()->send();
                        }),
                    DeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('approve_selected')
                        ->label('Approve selected')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Approve selected requests')
                        ->modalDescription('Only rows that are still pending are processed; each is approved like the row action (ledger changes apply where supported). Other rows are skipped.')
                        ->authorizeIndividualRecords('update')
                        ->action(function (EloquentCollection $records): void {
                            $service = app(MemberRequestService::class);
                            $admin = auth()->user();
                            $pending = $records->filter(fn (MemberRequest $r) => $r->isPending())->values();
                            $skipped = $records->count() - $pending->count();

                            $approved = 0;
                            $failed = 0;

                            foreach ($pending as $record) {
                                try {
                                    $service->approve($record, $admin);
                                    $approved++;
                                } catch (ValidationException $e) {
                                    $failed++;
                                } catch (\Throwable $e) {
                                    $failed++;
                                    report($e);
                                }
                            }

                            $body = "Approved: {$approved}. Failed: {$failed}. Skipped (not pending): {$skipped}.";

                            Notification::make()
                                ->title('Bulk approve finished')
                                ->body($body)
                                ->color($failed > 0 ? 'warning' : 'success')
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('reject_selected')
                        ->label('Reject selected')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->schema([
                            Forms\Components\Textarea::make('admin_note')
                                ->label('Note to members (optional)')
                                ->rows(3)
                                ->maxLength(2000),
                        ])
                        ->modalHeading('Reject selected requests')
                        ->modalDescription('The note below is stored on each selected row that is still pending. Other rows are skipped.')
                        ->authorizeIndividualRecords('update')
                        ->action(function (EloquentCollection $records, array $data): void {
                            $service = app(MemberRequestService::class);
                            $admin = auth()->user();
                            $note = $data['admin_note'] ?? null;
                            $pending = $records->filter(fn (MemberRequest $r) => $r->isPending())->values();
                            $skipped = $records->count() - $pending->count();

                            $rejected = 0;
                            $failed = 0;

                            foreach ($pending as $record) {
                                try {
                                    $service->reject($record, $admin, $note);
                                    $rejected++;
                                } catch (ValidationException $e) {
                                    $failed++;
                                } catch (\Throwable $e) {
                                    $failed++;
                                    report($e);
                                }
                            }

                            $body = "Rejected: {$rejected}. Failed: {$failed}. Skipped (not pending): {$skipped}.";

                            Notification::make()
                                ->title('Bulk reject finished')
                                ->body($body)
                                ->color($failed > 0 ? 'danger' : 'warning')
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make()
                        ->authorizeIndividualRecords('delete'),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMemberRequests::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
