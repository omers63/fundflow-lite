<?php

namespace App\Filament\Member\Pages;

use App\Models\Member;
use App\Models\SupportRequest;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class SupportPage extends Page
{
    protected string $view = 'filament.member.pages.support';

    protected static ?string $navigationLabel = 'Support & Requests';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?int $navigationSort = 3;

    public static function getNavigationLabel(): string
    {
        return __('app.member.support_requests');
    }

    public static function getNavigationGroup(): ?string
    {
        return 'settings';
    }

    public function getTitle(): string
    {
        return __('app.member.support_requests');
    }

    public function getHeaderActions(): array
    {
        return [
            Action::make('submit_request')
                ->label(__('app.member.submit_request'))
                ->icon('heroicon-o-paper-airplane')
                ->color('primary')
                ->modalHeading(__('app.member.submit_support_request_heading'))
                ->modalDescription(__('app.member.submit_support_request_desc'))
                ->modalWidth('lg')
                ->schema([
                    Forms\Components\Select::make('category')
                        ->label(__('app.member.category'))
                        ->options(SupportRequest::categoryOptions())
                        ->required()
                        ->native(false),
                    Forms\Components\TextInput::make('subject')
                        ->label(__('app.member.subject'))
                        ->required()
                        ->maxLength(150),
                    Forms\Components\Textarea::make('message')
                        ->label(__('app.member.message'))
                        ->required()
                        ->rows(5)
                        ->maxLength(2000),
                ])
                ->action(function (array $data): void {
                    $user = auth()->user();
                    $member = Member::where('user_id', $user->id)->first();

                    $supportRequest = SupportRequest::query()->create([
                        'user_id' => $user->id,
                        'member_id' => $member?->id,
                        'category' => $data['category'],
                        'subject' => $data['subject'],
                        'message' => $data['message'],
                    ]);

                    $categoryLabel = SupportRequest::categoryLabel($data['category']);
                    $memberInfo = $member
                        ? "{$user->name} (#{$member->member_number})"
                        : $user->name;

                    $body = __('Request #:id from :from', [
                        'id' => $supportRequest->id,
                        'from' => $memberInfo,
                    ])
                        ."\n".__('Category: :category', ['category' => $categoryLabel])
                        ."\n\n".$data['message'];

                    // Notify all admin users (in addition to permanent storage above).
                    User::where('role', 'admin')->each(function (User $admin) use ($data, $body, $supportRequest) {
                        Notification::make()
                            ->title(__('app.member.support_request_title', ['id' => $supportRequest->id, 'subject' => $data['subject']]))
                            ->body($body)
                            ->icon('heroicon-o-chat-bubble-left-right')
                            ->iconColor('warning')
                            ->sendToDatabase($admin);
                    });

                    Notification::make()
                        ->title(__('app.member.request_submitted'))
                        ->body(__('app.member.request_submitted_body'))
                        ->success()
                        ->send();
                }),
        ];
    }
}
