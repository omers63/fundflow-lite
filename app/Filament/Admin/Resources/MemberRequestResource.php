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

    public static function getNavigationLabel(): string
    {
        return __('Requests');
    }

    public static function getNavigationGroup(): ?string
    {
        return 'membership';
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
                    ->label(__('Member #'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('requester.user.name')
                    ->label(__('Member'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->label(__('Type'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => MemberRequest::typeLabel($state)),
                Tables\Columns\TextColumn::make('details_display')
                    ->label(__('Details'))
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
                    ->label(__('Submitted'))
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('reviewedBy.name')
                    ->label(__('Reviewed by'))
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
                        MemberRequest::STATUS_PENDING => __('Pending'),
                        MemberRequest::STATUS_APPROVED => __('Approved'),
                        MemberRequest::STATUS_REJECTED => __('Rejected'),
                        MemberRequest::STATUS_CANCELLED => __('Cancelled'),
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
                        ->label(__('Details'))
                        ->icon('heroicon-o-eye')
                        ->color('gray')
                        ->modalHeading(__('Request payload'))
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel(__('Close'))
                        ->modalContent(fn (MemberRequest $record): View => view(
                            'filament.admin.components.member-request-payload',
                            ['record' => $record],
                        )),
                    Action::make('approve')
                        ->label(__('Approve'))
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(fn (MemberRequest $record): bool => $record->isPending())
                        ->requiresConfirmation()
                        ->modalHeading(__('Approve this request?'))
                        ->modalDescription(__('The change will be applied immediately for supported request types.'))
                        ->action(function (MemberRequest $record): void {
                            try {
                                app(MemberRequestService::class)->approve($record, auth()->user());
                                Notification::make()->title(__('Request approved'))->success()->send();
                            } catch (ValidationException $e) {
                                Notification::make()
                                    ->title(__('Cannot approve'))
                                    ->body(collect($e->errors())->flatten()->first() ?? $e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),
                    Action::make('reject')
                        ->label(__('Reject'))
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->visible(fn (MemberRequest $record): bool => $record->isPending())
                        ->schema([
                            Forms\Components\Textarea::make('admin_note')
                                ->label(__('Note to member (optional)'))
                                ->rows(3)
                                ->maxLength(2000),
                        ])
                        ->action(function (MemberRequest $record, array $data): void {
                            app(MemberRequestService::class)->reject(
                                $record,
                                auth()->user(),
                                $data['admin_note'] ?? null,
                            );
                            Notification::make()->title(__('Request rejected'))->success()->send();
                        }),
                    DeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('approve_selected')
                        ->label(__('Approve selected'))
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading(__('Approve selected requests'))
                        ->modalDescription(__('Only rows that are still pending are processed; each is approved like the row action (ledger changes apply where supported). Other rows are skipped.'))
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

                            $body = __('Approved').": {$approved}. ".__('Failed').": {$failed}. ".__('Skipped (not pending)').": {$skipped}.";

                            Notification::make()
                                ->title(__('Bulk approve finished'))
                                ->body($body)
                                ->color($failed > 0 ? 'warning' : 'success')
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('reject_selected')
                        ->label(__('Reject selected'))
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->schema([
                            Forms\Components\Textarea::make('admin_note')
                                ->label(__('Note to members (optional)'))
                                ->rows(3)
                                ->maxLength(2000),
                        ])
                        ->modalHeading(__('Reject selected requests'))
                        ->modalDescription(__('The note below is stored on each selected row that is still pending. Other rows are skipped.'))
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

                            $body = __('Rejected').": {$rejected}. ".__('Failed').": {$failed}. ".__('Skipped (not pending)').": {$skipped}.";

                            Notification::make()
                                ->title(__('Bulk reject finished'))
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
