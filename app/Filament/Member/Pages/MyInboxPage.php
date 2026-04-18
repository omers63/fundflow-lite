<?php

namespace App\Filament\Member\Pages;

use App\Models\DirectMessage;
use App\Models\Member;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class MyInboxPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.member.pages.my-inbox';

    protected static ?string $navigationLabel = 'My Messages';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-inbox';

    /** Just below Dashboard (Filament default dashboard sort is -2). */
    protected static ?int $navigationSort = -1;

    public static function getNavigationGroup(): ?string
    {
        return null;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = DirectMessage::where('to_user_id', auth()->id())
            ->whereHas('sender', fn (Builder $q): Builder => $q->where('role', 'admin'))
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
        return 'My Messages';
    }

    public function getHeaderActions(): array
    {
        return [];
    }

    public function table(Table $table): Table
    {
        $memberId = auth()->user()?->member?->id ?? 0;

        return $table
            ->query(
                Member::query()
                    ->whereKey($memberId)
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
                            ->whereColumn('to_user_id', 'members.user_id')
                            ->whereHas('sender', fn (Builder $q): Builder => $q->where('role', 'admin'))
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
            ->columns([
                TextColumn::make('conversation')
                    ->label('Conversation')
                    ->state('Administration'),
                TextColumn::make('messages_received_count')
                    ->label('Received')
                    ->badge()
                    ->color('primary'),
                TextColumn::make('messages_sent_count')
                    ->label('Sent')
                    ->badge()
                    ->color('success'),
                TextColumn::make('unread_messages_count')
                    ->label('Unread')
                    ->badge()
                    ->color('danger'),
                TextColumn::make('last_message_at')
                    ->label('Last Message')
                    ->since()
                    ->placeholder('No messages yet'),
            ])
            ->recordActions([
                Action::make('communicate')
                    ->label('Communicate')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('primary')
                    ->modalHeading('Conversation with Administration')
                    ->modalDescription('Single communication thread with full history.')
                    ->modalWidth('5xl')
                    ->modalSubmitActionLabel('Send Message')
                    ->modalContent(fn (Member $record) => view(
                        'filament.member.pages.partials.admin-conversation-modal',
                        [
                            'messages' => $this->conversationMessages($record),
                            'userId' => auth()->id(),
                        ]
                    ))
                    ->schema([
                        Forms\Components\Textarea::make('body')
                            ->label('Message')
                            ->rows(4)
                            ->required()
                            ->maxLength(3000),
                        Forms\Components\FileUpload::make('attachments')
                            ->label('Attachments')
                            ->multiple()
                            ->disk('public')
                            ->directory('direct-messages')
                            ->openable()
                            ->downloadable()
                            ->maxFiles(5),
                    ])
                    ->action(function (Action $action, Member $record, array $data): void {
                        $this->sendMessageToAdmin(
                            $record,
                            (string) ($data['body'] ?? ''),
                            is_array($data['attachments'] ?? null) ? $data['attachments'] : []
                        );
                        $action->data(['body' => '', 'attachments' => []], shouldMutate: false);
                        $action->halt();
                    }),
            ])
            ->emptyStateHeading('No conversation found')
            ->emptyStateDescription('Your member record is required to use inbox messaging.');
    }

    public function conversationMessages(Member $member): Collection
    {
        DirectMessage::query()
            ->where('to_user_id', $member->user_id)
            ->whereHas('sender', fn (Builder $q): Builder => $q->where('role', 'admin'))
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return DirectMessage::query()
            ->whereNull('deleted_at')
            ->where(function (Builder $query) use ($member): void {
                $query->where(function (Builder $q) use ($member): void {
                    $q->where('from_user_id', $member->user_id)
                        ->whereHas('recipient', fn (Builder $admin): Builder => $admin->where('role', 'admin'));
                })->orWhere(function (Builder $q) use ($member): void {
                    $q->where('to_user_id', $member->user_id)
                        ->whereHas('sender', fn (Builder $admin): Builder => $admin->where('role', 'admin'));
                });
            })
            ->with(['sender', 'recipient'])
            ->orderBy('created_at')
            ->get();
    }

    public function sendMessageToAdmin(Member $member, string $body, array $attachments = []): void
    {
        $body = trim($body);
        if ($body === '') {
            Notification::make()
                ->title('Message body is required')
                ->warning()
                ->send();

            return;
        }

        $userId = auth()->id();
        $attachments = array_values(array_filter($attachments, fn ($file): bool => filled($file)));
        $lastAdminSenderId = DirectMessage::query()
            ->whereNull('deleted_at')
            ->where('to_user_id', $member->user_id)
            ->whereHas('sender', fn (Builder $q): Builder => $q->where('role', 'admin'))
            ->latest('created_at')
            ->value('from_user_id');

        $toUserId = $lastAdminSenderId ?: User::query()
            ->where('role', 'admin')
            ->orderBy('id')
            ->value('id');

        if (! $toUserId) {
            Notification::make()
                ->title('No admin user is available')
                ->danger()
                ->send();

            return;
        }

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
                'to_user_id' => $toUserId,
                'subject' => 'Conversation with member #'.$member->member_number,
                'body' => $body,
                'attachments' => $attachments,
            ]);
        } else {
            DirectMessage::create([
                'from_user_id' => $userId,
                'to_user_id' => $toUserId,
                'parent_id' => $root->id,
                'subject' => $root->subject,
                'body' => $body,
                'attachments' => $attachments,
            ]);
        }

        // Notify the other party in-app
        $recipient = User::query()->find($toUserId);
        if ($recipient) {
            Notification::make()
                ->title('New message from member')
                ->body(auth()->user()->name.': '.mb_strimwidth($body, 0, 100, '…'))
                ->icon('heroicon-o-chat-bubble-left-right')
                ->iconColor('info')
                ->sendToDatabase($recipient);
        }

        Notification::make()->title('Message sent')->success()->send();
    }
}
