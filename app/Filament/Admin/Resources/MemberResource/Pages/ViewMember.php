<?php

namespace App\Filament\Admin\Resources\MemberResource\Pages;

use App\Filament\Admin\Resources\MemberResource;
use App\Filament\Admin\Widgets\MemberRecordInsightsWidget;
use App\Models\DirectMessage;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewMember extends ViewRecord
{
    protected static string $resource = MemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('send_message')
                ->label('Send Message')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->color('info')
                ->visible(fn() => $this->record->user !== null)
                ->modalHeading(fn() => "Send Message to {$this->record->user->name}")
                ->modalWidth('lg')
                ->schema([
                    Forms\Components\TextInput::make('subject')
                        ->label('Subject')
                        ->required()
                        ->maxLength(150),
                    Forms\Components\Textarea::make('body')
                        ->label('Message')
                        ->required()
                        ->rows(5)
                        ->maxLength(3000),
                ])
                ->action(function (array $data): void {
                    $member = $this->record;
                    DirectMessage::create([
                        'from_user_id' => auth()->id(),
                        'to_user_id'   => $member->user_id,
                        'subject'      => $data['subject'],
                        'body'         => $data['body'],
                    ]);

                    Notification::make()
                        ->title('New Message from Administration')
                        ->body($data['subject'] . ': ' . mb_strimwidth($data['body'], 0, 100, '…'))
                        ->icon('heroicon-o-chat-bubble-left-right')
                        ->iconColor('info')
                        ->actions([
                            \Filament\Notifications\Actions\Action::make('view')
                                ->label('View Inbox')
                                ->url(route('filament.member.pages.my-inbox-page')),
                        ])
                        ->sendToDatabase($member->user);

                    Notification::make()
                        ->title('Message sent to ' . $member->user->name)
                        ->success()
                        ->send();
                }),

            EditAction::make(),
        ];
    }

    public function getSubheading(): ?string
    {
        return 'Full member profile — financial standing, activity, and history.';
    }

    protected function getHeaderWidgets(): array
    {
        return [
            MemberRecordInsightsWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }

    protected function getWidgetsData(): array
    {
        return [
            'record' => $this->record,
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $this->record->unsetRelation('user');
        $this->record->load('user');
        $app = $this->record->latestMembershipApplication();

        if ($app?->membership_date) {
            $data['joined_at'] = $app->membership_date->toDateString();
        }

        return $data;
    }
}
