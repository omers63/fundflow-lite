<x-filament-panels::page>

@php $userId = auth()->id(); @endphp

<div class="space-y-3">

    @forelse($this->threads as $thread)

    @php
        $allMessages = collect([$thread])->merge($thread->replies);
        $myUnread = $allMessages->filter(fn($m) => $m->to_user_id === $userId && !$m->read_at)->count();
        $isOpen   = $this->openThreadId === $thread->id;
        $other    = $thread->from_user_id === $userId ? $thread->recipient : $thread->sender;
    @endphp

    <div class="rounded-xl ring-1 {{ $myUnread > 0 ? 'ring-primary-300 dark:ring-primary-700 bg-primary-50 dark:bg-primary-900/20' : 'ring-gray-200 dark:ring-gray-700 bg-white dark:bg-gray-800' }} shadow-sm overflow-hidden">

        <button wire:click="openThread({{ $thread->id }})" type="button"
                class="w-full text-left px-5 py-4 flex items-center justify-between gap-4 hover:bg-gray-50/50 dark:hover:bg-gray-700/30 transition-colors">
            <div class="flex items-center gap-3 min-w-0">
                <div class="flex-shrink-0 h-9 w-9 rounded-full bg-primary-100 dark:bg-primary-800 flex items-center justify-center text-sm font-bold text-primary-700 dark:text-primary-300">
                    {{ strtoupper(mb_substr($other?->name ?? '?', 0, 1)) }}
                </div>
                <div class="min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="font-semibold text-sm text-gray-900 dark:text-white">{{ $thread->subject ?? '(No subject)' }}</span>
                        @if($myUnread > 0)
                        <span class="inline-flex items-center rounded-full bg-primary-600 px-2 py-0.5 text-xs font-bold text-white">{{ $myUnread }} new</span>
                        @endif
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                        {{ $other?->name }} &middot; {{ $thread->created_at->diffForHumans() }}
                        @if($allMessages->count() > 1) &middot; {{ $allMessages->count() }} messages @endif
                    </p>
                </div>
            </div>
            <x-dynamic-component :component="$isOpen ? 'heroicon-o-chevron-up' : 'heroicon-o-chevron-down'" class="w-4 h-4 text-gray-400 flex-shrink-0" />
        </button>

        @if($isOpen)
        <div class="border-t border-gray-100 dark:border-gray-700 px-5 py-4 space-y-4">
            @foreach($allMessages as $msg)
            @php $isMine = $msg->from_user_id === $userId; @endphp
            <div class="flex gap-3 {{ $isMine ? 'flex-row-reverse' : '' }}">
                <div class="flex-shrink-0 h-8 w-8 rounded-full {{ $isMine ? 'bg-indigo-100 dark:bg-indigo-800 text-indigo-700 dark:text-indigo-300' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400' }} flex items-center justify-center text-xs font-bold">
                    {{ strtoupper(mb_substr($msg->sender?->name ?? '?', 0, 1)) }}
                </div>
                <div class="max-w-xl">
                    <p class="text-xs text-gray-400 mb-1 {{ $isMine ? 'text-right' : '' }}">{{ $msg->sender?->name }}</p>
                    <div class="rounded-xl {{ $isMine ? 'bg-indigo-600 text-white rounded-tr-none' : 'bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-tl-none' }} px-4 py-3 text-sm whitespace-pre-wrap">{{ $msg->body }}</div>
                    <p class="text-xs text-gray-400 mt-1 {{ $isMine ? 'text-right' : '' }}">
                        {{ $msg->created_at->format('d M Y H:i') }}
                    </p>
                </div>
            </div>
            @endforeach

            {{-- Reply form --}}
            <div class="border-t border-gray-100 dark:border-gray-700 pt-4" x-data="{ body: '' }">
                <div class="flex gap-2 items-end">
                    <textarea
                        x-model="body"
                        rows="2"
                        placeholder="Write a reply…"
                        class="flex-1 rounded-lg text-sm bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary-500 resize-none"
                    ></textarea>
                    <button type="button"
                            x-on:click="$wire.sendReply({{ $thread->id }}, body); body = ''"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 px-4 py-2 text-sm font-medium text-white transition-colors">
                        <x-heroicon-o-paper-airplane class="w-4 h-4" />
                        Reply
                    </button>
                </div>
            </div>
        </div>
        @endif

    </div>

    @empty
    <div class="rounded-xl bg-white dark:bg-gray-800 ring-1 ring-gray-200 dark:ring-gray-700 p-10 text-center">
        <x-heroicon-o-inbox class="w-12 h-12 text-gray-300 dark:text-gray-600 mx-auto mb-3" />
        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">No messages yet</p>
        <p class="text-xs text-gray-400 mt-1">Use "Send Message" on a member's profile to start a conversation.</p>
    </div>
    @endforelse

</div>

</x-filament-panels::page>
