<?php

namespace App\Filament\Admin\Pages;

use App\Models\DirectMessage;
use App\Models\Member;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms;
use Filament\Forms\Components\Component;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class AdminInboxPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.admin.pages.admin-inbox';

    protected static ?string $navigationLabel = null;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?int $navigationSort = 90;

    public static function getNavigationLabel(): string
    {
        return __('Messages');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = DirectMessage::where('to_user_id', auth()->id())
            ->whereNull('read_at')
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public function getTitle(): string
    {
        return __('Messages Inbox');
    }

    public function getHeaderActions(): array
    {
        return [
            Action::make('message_all_members')
                ->label(__('Message all members'))
                ->icon('heroicon-o-megaphone')
                ->color('primary')
                ->modalHeading(__('Send message to all members'))
                ->modalDescription(fn (): string => __('This sends the same message (and attachments) to every member who has a login account. Currently: ')
                    .Member::query()->whereNotNull('user_id')->count()
                    .' '.__('member(s).'))
                ->modalWidth('2xl')
                ->schema($this->bulkMessageFormSchema())
                ->action(function (array $data): void {
                    $members = Member::query()
                        ->whereNotNull('user_id')
                        ->with('user')
                        ->get();

                    $this->sendMessageToMembersCollection($members, $data);
                }),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Member::query()
                    ->with('user')
                    ->select('members.*')
                    ->selectSub(
                        DirectMessage::query()
                            ->whereNull('deleted_at')
                            ->whereColumn('to_user_id', 'members.user_id')
                            ->whereHas('sender', fn (Builder $q): Builder => $q->where('role', 'admin'))
                            ->selectRaw('count(*)'),
                        'messages_received_count'
                    )
                    ->selectSub(
                        DirectMessage::query()
                            ->whereNull('deleted_at')
                            ->whereColumn('from_user_id', 'members.user_id')
                            ->whereHas('recipient', fn (Builder $q): Builder => $q->where('role', 'admin'))
                            ->selectRaw('count(*)'),
                        'messages_sent_count'
                    )
                    ->selectSub(
                        DirectMessage::query()
                            ->whereNull('deleted_at')
                            ->whereColumn('from_user_id', 'members.user_id')
                            ->where('to_user_id', auth()->id())
                            ->whereNull('read_at')
                            ->selectRaw('count(*)'),
                        'unread_messages_count'
                    )
                    ->selectSub(
                        DirectMessage::query()
                            ->whereNull('deleted_at')
                            ->where(function (Builder $query): void {
                                $query->where(function (Builder $q): void {
                                    $q->whereColumn('to_user_id', 'members.user_id')
                                        ->whereHas('sender', fn (Builder $sq): Builder => $sq->where('role', 'admin'));
                                })->orWhere(function (Builder $q): void {
                                    $q->whereColumn('from_user_id', 'members.user_id')
                                        ->whereHas('recipient', fn (Builder $rq): Builder => $rq->where('role', 'admin'));
                                });
                            })
                            ->selectRaw('MAX(created_at)'),
                        'last_message_at'
                    )
            )
            ->defaultSort('last_message_at', 'desc')
            ->columns([
                TextColumn::make('user.name')
                    ->label(__('Member'))
                    ->searchable()
                    ->sortable()
                    ->placeholder(__('No linked user')),
                TextColumn::make('member_number')
                    ->label(__('Member #'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('messages_received_count')
                    ->label(__('Received'))
                    ->badge()
                    ->color('primary'),
                TextColumn::make('messages_sent_count')
                    ->label(__('Sent'))
                    ->badge()
                    ->color('success'),
                TextColumn::make('unread_messages_count')
                    ->label(__('Unread'))
                    ->badge()
                    ->color('danger'),
                TextColumn::make('last_message_at')
                    ->label(__('Last Message'))
                    ->since()
                    ->sortable()
                    ->placeholder(__('No messages yet')),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('communicate')
                        ->label(__('Communicate'))
                        ->icon('heroicon-o-chat-bubble-left-right')
                        ->color('primary')
                        ->disabled(fn (Member $record): bool => blank($record->user_id))
                        ->modalHeading(fn (Member $record): string => __('Conversation with').' '.($record->user?->name ?? __('Member')))
                        ->modalDescription(__('Single communication thread with full history.'))
                        ->modalWidth('5xl')
                        ->modalSubmitActionLabel(__('Send Message'))
                        ->modalContent(fn (Member $record) => view(
                            'filament.admin.pages.partials.member-conversation-modal',
                            [
                                'messages' => $this->conversationMessages($record),
                                'userId' => auth()->id(),
                            ]
                        ))
                        ->schema([
                            Forms\Components\Textarea::make('body')
                                ->label(__('Message'))
                                ->rows(4)
                                ->required()
                                ->maxLength(3000),
                            Forms\Components\FileUpload::make('attachments')
                                ->label(__('Attachments'))
                                ->multiple()
                                ->disk('public')
                                ->directory('direct-messages')
                                ->openable()
                                ->downloadable()
                                ->maxFiles(5),
                        ])
                        ->action(function (Action $action, Member $record, array $data): void {
                            $this->sendMessageToMember(
                                $record,
                                (string) ($data['body'] ?? ''),
                                is_array($data['attachments'] ?? null) ? $data['attachments'] : [],
                                false
                            );
                            $action->data(['body' => '', 'attachments' => []], shouldMutate: false);
                            $action->halt();
                        }),
                    Action::make('delete_conversation')
                        ->label(__('Delete Conversation'))
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading(fn (Member $record): string => __('Delete conversation with').' '.($record->user?->name ?? __('member')).'?')
                        ->modalDescription(__('This will clear all previous communications with this member from the inbox.'))
                        ->action(function (Member $record): void {
                            $this->deleteConversation($record);
                        }),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('send_messages')
                        ->label(__('Send message'))
                        ->icon('heroicon-o-chat-bubble-left-right')
                        ->color('info')
                        ->modalHeading(__('Send message to selected members'))
                        ->modalDescription(__('The same message and attachments are delivered to each selected member’s conversation thread.'))
                        ->modalWidth('2xl')
                        ->schema($this->bulkMessageFormSchema())
                        ->action(function (array $data, EloquentCollection $records): void {
                            $members = $records->filter(
                                fn ($record): bool => $record instanceof Member && filled($record->user_id)
                            );

                            $this->sendMessageToMembersCollection($members, $data);
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('clear_conversations')
                        ->label(__('Clear Conversations'))
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading(__('Clear selected conversations?'))
                        ->modalDescription(__('This will delete all previous communications for the selected member rows.'))
                        ->action(function (EloquentCollection $records): void {
                            $members = $records->filter(fn ($record): bool => $record instanceof Member);

                            $membersCleared = 0;
                            $messagesDeleted = 0;

                            foreach ($members as $member) {
                                /** @var Member $member */
                                $deletedForMember = $this->purgeConversationForMember($member);
                                $messagesDeleted += $deletedForMember;

                                if ($deletedForMember > 0) {
                                    $membersCleared++;
                                }
                            }

                            if ($messagesDeleted === 0) {
                                Notification::make()
                                    ->title(__('No conversations deleted'))
                                    ->warning()
                                    ->send();

                                return;
                            }

                            Notification::make()
                                ->title(__('Conversations cleared'))
                                ->body(__('Members').": {$membersCleared}. ".__('Messages deleted').": {$messagesDeleted}.")
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->emptyStateHeading(__('No members found'))
            ->emptyStateDescription(__('Members will appear here once created.'));
    }

    /**
     * @return array<int, Component>
     */
    protected function bulkMessageFormSchema(): array
    {
        return [
            Forms\Components\Textarea::make('body')
                ->label(__('Message'))
                ->rows(5)
                ->required()
                ->maxLength(3000),
            Forms\Components\FileUpload::make('attachments')
                ->label(__('Attachments (optional)'))
                ->multiple()
                ->disk('public')
                ->directory('direct-messages')
                ->openable()
                ->downloadable()
                ->maxFiles(5),
        ];
    }

    /**
     * @param  EloquentCollection<int, Member>|Collection<int, Member>  $members
     */
    protected function sendMessageToMembersCollection(EloquentCollection|Collection $members, array $data): void
    {
        $body = trim((string) ($data['body'] ?? ''));
        $attachments = is_array($data['attachments'] ?? null)
            ? array_values(array_filter($data['attachments'], fn ($file): bool => filled($file)))
            : [];

        if ($body === '' && $attachments === []) {
            Notification::make()
                ->title(__('Message body or at least one attachment is required'))
                ->warning()
                ->send();

            return;
        }

        if ($body === '') {
            $body = ' ';
        }

        $sent = 0;
        $skipped = 0;

        foreach ($members as $member) {
            if (! $member instanceof Member || blank($member->user_id)) {
                $skipped++;

                continue;
            }

            if ($this->sendMessageToMember($member, $body, $attachments, true)) {
                $sent++;
            } else {
                $skipped++;
            }
        }

        if ($sent === 0) {
            Notification::make()
                ->title(__('No messages sent'))
                ->body($skipped > 0 ? __('No eligible members.') : '')
                ->warning()
                ->send();

            return;
        }

        Notification::make()
            ->title(__('Messages sent'))
            ->body(__('Delivered to')." {$sent} ".__('member(s)').($skipped > 0 ? ". ".__('Skipped').": {$skipped}." : '.'))
            ->success()
            ->send();
    }

    public function conversationMessages(Member $member): Collection
    {
        if (! $member->user_id) {
            return collect();
        }

        DirectMessage::query()
            ->where('from_user_id', $member->user_id)
            ->where('to_user_id', auth()->id())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return DirectMessage::query()
            ->whereNull('deleted_at')
            ->where(function (Builder $q) use ($member): void {
                $q->where(function (Builder $sq) use ($member): void {
                    $sq->where('to_user_id', $member->user_id)
                        ->whereHas('sender', fn (Builder $admin): Builder => $admin->where('role', 'admin'));
                })->orWhere(function (Builder $sq) use ($member): void {
                    $sq->where('from_user_id', $member->user_id)
                        ->whereHas('recipient', fn (Builder $admin): Builder => $admin->where('role', 'admin'));
                });
            })
            ->with(['sender', 'recipient'])
            ->orderBy('created_at')
            ->get();
    }

    /**
     * @param  bool  $suppressAdminToast  When true, only the final summary notification is shown (bulk sends).
     */
    public function sendMessageToMember(Member $member, string $body, array $attachments = [], bool $suppressAdminToast = false): bool
    {
        $body = trim($body);
        if ($body === '' && $attachments === []) {
            if (! $suppressAdminToast) {
                Notification::make()
                    ->title(__('Message body or at least one attachment is required'))
                    ->warning()
                    ->send();
            }

            return false;
        }

        if ($body === '') {
            $body = ' ';
        }

        if (! $member->user_id) {
            if (! $suppressAdminToast) {
                Notification::make()
                    ->title(__('Member account not found'))
                    ->danger()
                    ->send();
            }

            return false;
        }

        $userId = auth()->id();
        $attachments = array_values(array_filter($attachments, fn ($file): bool => filled($file)));
        $root = DirectMessage::root()
            ->where(function (Builder $q) use ($member): void {
                $q->where(function (Builder $sq) use ($member): void {
                    $sq->where('from_user_id', $member->user_id)
                        ->whereHas('recipient', fn (Builder $admin): Builder => $admin->where('role', 'admin'));
                })->orWhere(function (Builder $sq) use ($member): void {
                    $sq->where('to_user_id', $member->user_id)
                        ->whereHas('sender', fn (Builder $admin): Builder => $admin->where('role', 'admin'));
                });
            })
            ->orderBy('created_at')
            ->first();

        if ($root === null) {
            DirectMessage::create([
                'from_user_id' => $userId,
                'to_user_id' => $member->user_id,
                'subject' => __('Conversation with').' '.($member->user->name ?? __('member')),
                'body' => $body,
                'attachments' => $attachments,
            ]);
        } else {
            DirectMessage::create([
                'from_user_id' => $userId,
                'to_user_id' => $member->user_id,
                'parent_id' => $root->id,
                'subject' => $root->subject,
                'body' => $body,
                'attachments' => $attachments,
            ]);
        }

        $recipient = User::query()->find($member->user_id);
        if ($recipient) {
            Notification::make()
                ->title(__('Message from Administration'))
                ->body(auth()->user()->name.': '.mb_strimwidth(trim($body), 0, 100, '…'))
                ->icon('heroicon-o-chat-bubble-left-right')
                ->iconColor('info')
                ->sendToDatabase($recipient);
        }

        if (! $suppressAdminToast) {
            Notification::make()
                ->title(__('Message sent'))
                ->success()
                ->send();
        }

        return true;
    }

    public function deleteConversation(Member $member): void
    {
        if (! $member->user_id) {
            Notification::make()
                ->title(__('Member account not found'))
                ->danger()
                ->send();

            return;
        }

        $deleted = $this->purgeConversationForMember($member);

        if ($deleted === 0) {
            Notification::make()
                ->title(__('No conversation to delete'))
                ->warning()
                ->send();

            return;
        }

        Notification::make()
            ->title(__('Conversation deleted'))
            ->success()
            ->send();
    }

    protected function purgeConversationForMember(Member $member): int
    {
        if (! $member->user_id) {
            return 0;
        }

        return DirectMessage::query()
            ->where(function (Builder $q) use ($member): void {
                $q->where(function (Builder $sq) use ($member): void {
                    $sq->where('to_user_id', $member->user_id)
                        ->whereHas('sender', fn (Builder $admin): Builder => $admin->where('role', 'admin'));
                })->orWhere(function (Builder $sq) use ($member): void {
                    $sq->where('from_user_id', $member->user_id)
                        ->whereHas('recipient', fn (Builder $admin): Builder => $admin->where('role', 'admin'));
                });
            })
            ->delete();
    }
}
