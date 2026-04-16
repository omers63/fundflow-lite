<?php

namespace App\Filament\Member\Pages;

use App\Models\DirectMessage;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\Attributes\Computed;

class MyInboxPage extends Page
{
    protected string $view = 'filament.member.pages.my-inbox';

    protected static ?string $navigationLabel = 'My Inbox';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-inbox';

    protected static ?int $navigationSort = 5;

    public static function getNavigationGroup(): ?string
    {
        return __('app.nav.group.account');
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
        return 'My Inbox';
    }

    /** Selected thread root ID */
    public ?int $openThreadId = null;

    /** Reply text field bound per-thread via a single field, submit via action */
    public string $replyBody = '';

    public function getHeaderActions(): array
    {
        return [];
    }

    #[Computed]
    public function threads(): \Illuminate\Database\Eloquent\Collection
    {
        $userId = auth()->id();

        // Root messages where I'm sender or recipient
        return DirectMessage::root()
            ->where(function ($q) use ($userId) {
                $q->where('from_user_id', $userId)
                  ->orWhere('to_user_id', $userId);
            })
            ->with(['sender', 'recipient', 'replies.sender', 'replies.recipient'])
            ->orderByDesc('created_at')
            ->get();
    }

    public function openThread(int $id): void
    {
        $this->openThreadId = ($this->openThreadId === $id) ? null : $id;

        // Mark all messages in thread as read (messages addressed to me)
        $userId = auth()->id();
        DirectMessage::thread($id)
            ->where('to_user_id', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function sendReply(int $threadId, string $body): void
    {
        $this->replyToThread(['thread_id' => $threadId, 'body' => $body]);
        $this->replyBody = '';
    }

    public function replyToThread(array $data): void
    {
        $userId = auth()->id();
        $root   = DirectMessage::findOrFail($data['thread_id']);

        // Determine the recipient: the other person in the thread
        $toUserId = $root->from_user_id === $userId
            ? $root->to_user_id
            : $root->from_user_id;

        DirectMessage::create([
            'from_user_id' => $userId,
            'to_user_id'   => $toUserId,
            'parent_id'    => $root->id,
            'body'         => $data['body'],
        ]);

        // Notify the other party in-app
        $recipient = User::find($toUserId);
        if ($recipient) {
            Notification::make()
                ->title('New Reply: ' . ($root->subject ?? 'Message'))
                ->body(auth()->user()->name . ' replied: ' . mb_strimwidth($data['body'], 0, 100, '…'))
                ->icon('heroicon-o-chat-bubble-left-right')
                ->iconColor('info')
                ->sendToDatabase($recipient);
        }

        Notification::make()->title('Reply sent')->success()->send();
    }
}
