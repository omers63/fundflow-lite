<?php

namespace App\Filament\Member\Pages;

use App\Filament\Member\Resources\MyDependentsResource;
use App\Models\Member;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

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
        $actions = [
            Action::make('save_allocation')
                ->label('Save allocation')
                ->icon('heroicon-o-check-circle')
                ->color('primary')
                ->fillForm(['monthly_contribution_amount' => $this->monthly_contribution_amount])
                ->schema([
                    Forms\Components\Select::make('monthly_contribution_amount')
                        ->label('Monthly contribution amount')
                        ->options(Member::contributionAmountOptions())
                        ->required()
                        ->helperText('Multiples of SAR 500, from SAR 500 to SAR 3,000. Applies immediately and notifies administration.'),
                ])
                ->action(function (array $data): void {
                    $member = $this->currentMember();

                    if (! $member) {
                        Notification::make()->title('Member record not found.')->danger()->send();

                        return;
                    }

                    $newAmount = (int) $data['monthly_contribution_amount'];
                    $oldAmount = (int) $member->monthly_contribution_amount;

                    if (! Member::isValidContributionAmount($newAmount)) {
                        Notification::make()->title('Invalid amount selected.')->danger()->send();
                        return;
                    }

                    if ($newAmount === $oldAmount) {
                        Notification::make()->title('No changes detected.')->info()->send();
                        return;
                    }

                    $member->update(['monthly_contribution_amount' => $newAmount]);

                    User::where('role', 'admin')->each(function (User $admin) use ($member, $oldAmount, $newAmount): void {
                        Notification::make()
                            ->title('Member allocation updated')
                            ->body(
                                ($member->user?->name ?? 'Member')
                                .' changed own allocation from SAR '.number_format($oldAmount)
                                .' to SAR '.number_format($newAmount).'.'
                            )
                            ->icon('heroicon-o-adjustments-horizontal')
                            ->iconColor('info')
                            ->sendToDatabase($admin);
                    });

                    Notification::make()
                        ->title('Allocation updated')
                        ->body('Your monthly contribution amount was updated successfully.')
                        ->success()
                        ->send();

                    $this->mount();
                }),
        ];

        if ($member && $member->dependents()->exists()) {
            $actions[] = Action::make('family_requests')
                ->label('My Dependents')
                ->icon('heroicon-o-users')
                ->url(MyDependentsResource::getUrl())
                ->color('gray');
        }

        return $actions;
    }

    public function getTitle(): string
    {
        return 'Contribution Settings';
    }
}
