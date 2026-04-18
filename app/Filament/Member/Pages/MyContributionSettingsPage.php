<?php

namespace App\Filament\Member\Pages;

use App\Filament\Member\Resources\MyDependentsResource;
use App\Models\Member;
use App\Models\MemberRequest;
use App\Services\MemberRequestService;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Validation\ValidationException;

class MyContributionSettingsPage extends Page
{
    protected string $view = 'filament.member.pages.my-contribution-settings';

    protected static ?string $navigationLabel = 'Contribution Settings';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?int $navigationSort = 1;

    public int $monthly_contribution_amount = 500;

    public static function getNavigationGroup(): ?string
    {
        return __('app.nav.group.settings');
    }

    public function mount(): void
    {
        $member = $this->currentMember();
        $this->monthly_contribution_amount = $member?->monthly_contribution_amount ?? 500;
    }

    protected function currentMember(): ?Member
    {
        return Member::where('user_id', auth()->id())->first();
    }

    public function getHeaderActions(): array
    {
        $member = $this->currentMember();

        if ($member?->parent_id !== null) {
            return [
                Action::make('family_requests')
                    ->label('My Dependents')
                    ->icon('heroicon-o-users')
                    ->url(MyDependentsResource::getUrl())
                    ->color('primary'),
            ];
        }

        return [
            Action::make('save_allocation')
                ->label('Request allocation change')
                ->icon('heroicon-o-paper-airplane')
                ->color('primary')
                ->fillForm(['monthly_contribution_amount' => $this->monthly_contribution_amount])
                ->schema([
                    Forms\Components\Select::make('monthly_contribution_amount')
                        ->label('Requested monthly contribution amount')
                        ->options(Member::contributionAmountOptions())
                        ->required()
                        ->helperText('Multiples of SAR 500, from SAR 500 to SAR 3,000. Submits a request for administration approval.'),
                ])
                ->action(function (array $data): void {
                    $member = $this->currentMember();

                    if (! $member) {
                        Notification::make()->title('Member record not found.')->danger()->send();

                        return;
                    }

                    try {
                        app(MemberRequestService::class)->submit($member, MemberRequest::TYPE_OWN_ALLOCATION, [
                            'requested_amount' => (int) $data['monthly_contribution_amount'],
                        ]);
                    } catch (ValidationException $e) {
                        $msg = collect($e->errors())->flatten()->first() ?? $e->getMessage();
                        Notification::make()->title('Could not submit request')->body($msg)->danger()->send();

                        return;
                    }

                    Notification::make()
                        ->title('Request submitted')
                        ->body('You will be notified when administration reviews your allocation change.')
                        ->success()
                        ->send();

                    $this->mount();
                }),
        ];
    }

    public function getTitle(): string
    {
        return 'Contribution Settings';
    }
}
