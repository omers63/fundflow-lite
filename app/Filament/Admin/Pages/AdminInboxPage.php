<?php

namespace App\Filament\Admin\Pages;

use App\Models\DirectMessage;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\Attributes\Computed;

class AdminInboxPage extends Page
{
    protected string $view = 'filament.admin.pages.admin-inbox';

    protected static ?string $navigationLabel = 'Messages';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?int $navigationSort = 90;

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
        return 'Admin Messages';
    }

    public ?int $openThreadId = null;

    #[Computed]
    public function threads(): \Illuminate\Database\Eloquent\Collection
    {
        $userId = auth()->id();

        return DirectMessage::root()
            ->where(function ($q) use ($userId) {
                $q->where('from_user_id', $userId)
                  ->orWhere('to_user_id', $userId);
            })
            ->with(['sender', 'recipient', 'replies.sender', 'replies.recipient'])
            ->orderByDesc('updated_at')
            ->get();
    }

    public function openThread(int $id): void
    {
        $this->openThreadId = ($this->openThreadId === $id) ? null : $id;

        // Mark received messages as read
        DirectMessage::thread($id)
            ->where('to_user_id', auth()->id())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function sendReply(int $threadId, string $body): void
    {
        $userId = auth()->id();
        $root   = DirectMessage::findOrFail($threadId);

        $toUserId = $root->from_user_id === $userId
            ? $root->to_user_id
            : $root->from_user_id;

        DirectMessage::create([
            'from_user_id' => $userId,
            'to_user_id'   => $toUserId,
            'parent_id'    => $root->id,
            'body'         => $body,
        ]);

        $recipient = User::find($toUserId);
        if ($recipient) {
            Notification::make()
                ->title('Reply: ' . ($root->subject ?? 'Message'))
                ->body(auth()->user()->name . ': ' . mb_strimwidth($body, 0, 100, '…'))
                ->icon('heroicon-o-chat-bubble-left-right')
                ->iconColor('info')
                ->sendToDatabase($recipient);
        }

        Notification::make()->title('Reply sent')->success()->send();
    }
}
