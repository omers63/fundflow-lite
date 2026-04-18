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

    public static function getNavigationGroup(): ?string
    {
        return __('app.nav.group.settings');
    }

    public function getTitle(): string
    {
        return 'Support & Requests';
    }

    public function getHeaderActions(): array
    {
        return [
            Action::make('submit_request')
                ->label('Submit Request')
                ->icon('heroicon-o-paper-airplane')
                ->color('primary')
                ->modalHeading('Submit a Support Request')
                ->modalDescription('Your message will be sent to the fund administrators who will respond via the messaging system.')
                ->modalWidth('lg')
                ->schema([
                    Forms\Components\Select::make('category')
                        ->label('Category')
                        ->options(SupportRequest::CATEGORY_LABELS)
                        ->required()
                        ->native(false),
                    Forms\Components\TextInput::make('subject')
                        ->label('Subject')
                        ->required()
                        ->maxLength(150),
                    Forms\Components\Textarea::make('message')
                        ->label('Message')
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

                    $body = "Request #{$supportRequest->id}\nFrom: {$memberInfo}\nCategory: {$categoryLabel}\n\n{$data['message']}";

                    // Notify all admin users (in addition to permanent storage above).
                    User::where('role', 'admin')->each(function (User $admin) use ($data, $body, $supportRequest) {
                        Notification::make()
                            ->title("Support Request #{$supportRequest->id}: {$data['subject']}")
                            ->body($body)
                            ->icon('heroicon-o-chat-bubble-left-right')
                            ->iconColor('warning')
                            ->sendToDatabase($admin);
                    });

                    Notification::make()
                        ->title('Request Submitted')
                        ->body('Your request has been sent to the fund administrators. They will respond via the messaging system.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
