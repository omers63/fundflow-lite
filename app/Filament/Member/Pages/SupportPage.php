<?php

namespace App\Filament\Member\Pages;

use App\Models\Member;
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

    protected static ?int $navigationSort = 4;

    public static function getNavigationGroup(): ?string
    {
        return __('app.nav.group.account');
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
                        ->options([
                            'general_inquiry'    => 'General Inquiry',
                            'cash_deposit'       => 'Cash Deposit Request',
                            'loan_inquiry'       => 'Loan Inquiry',
                            'contribution_query' => 'Contribution Query',
                            'balance_query'      => 'Balance / Account Query',
                            'complaint'          => 'Complaint',
                            'other'              => 'Other',
                        ])
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
                    $user   = auth()->user();
                    $member = Member::where('user_id', $user->id)->first();

                    $categoryLabels = [
                        'general_inquiry'    => 'General Inquiry',
                        'cash_deposit'       => 'Cash Deposit Request',
                        'loan_inquiry'       => 'Loan Inquiry',
                        'contribution_query' => 'Contribution Query',
                        'balance_query'      => 'Balance / Account Query',
                        'complaint'          => 'Complaint',
                        'other'              => 'Other',
                    ];

                    $categoryLabel = $categoryLabels[$data['category']] ?? $data['category'];
                    $memberInfo    = $member
                        ? "{$user->name} (#{$member->member_number})"
                        : $user->name;

                    $body = "From: {$memberInfo}\nCategory: {$categoryLabel}\n\n{$data['message']}";

                    // Notify all admin users
                    User::where('role', 'admin')->each(function (User $admin) use ($data, $body, $memberInfo) {
                        Notification::make()
                            ->title("Support Request: {$data['subject']}")
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
