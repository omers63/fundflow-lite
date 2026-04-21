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

    public static function getNavigationLabel(): string
    {
        return __('app.member.contribution_settings');
    }

    public int $monthly_contribution_amount = 500;

    public static function getNavigationGroup(): ?string
    {
        return 'settings';
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
                ->label(__('app.member.save_allocation'))
                ->icon('heroicon-o-check-circle')
                ->color('primary')
                ->fillForm(['monthly_contribution_amount' => $this->monthly_contribution_amount])
                ->schema([
                    Forms\Components\Select::make('monthly_contribution_amount')
                        ->label(__('app.member.monthly_contribution_amount'))
                        ->options(Member::contributionAmountOptions())
                        ->required()
                        ->helperText(__('app.member.monthly_contribution_helper')),
                ])
                ->action(function (array $data): void {
                    $member = $this->currentMember();

                    if (! $member) {
                        Notification::make()->title(__('app.member.member_record_not_found'))->danger()->send();

                        return;
                    }

                    $newAmount = (int) $data['monthly_contribution_amount'];
                    $oldAmount = (int) $member->monthly_contribution_amount;

                    if (! Member::isValidContributionAmount($newAmount)) {
                        Notification::make()->title(__('app.member.invalid_amount_selected'))->danger()->send();
                        return;
                    }

                    if ($newAmount === $oldAmount) {
                        Notification::make()->title(__('app.member.no_changes_detected'))->info()->send();
                        return;
                    }

                    $member->update(['monthly_contribution_amount' => $newAmount]);

                    User::where('role', 'admin')->each(function (User $admin) use ($member, $oldAmount, $newAmount): void {
                        Notification::make()
                            ->title(__('app.member.member_allocation_updated'))
                            ->body(
                                __('app.member.member_allocation_updated_body', [
                                    'name' => ($member->user?->name ?? __('app.resource.member')),
                                    'old' => number_format($oldAmount),
                                    'new' => number_format($newAmount),
                                ])
                            )
                            ->icon('heroicon-o-adjustments-horizontal')
                            ->iconColor('info')
                            ->sendToDatabase($admin);
                    });

                    Notification::make()
                        ->title(__('app.member.allocation_updated'))
                        ->body(__('app.member.allocation_updated_body'))
                        ->success()
                        ->send();

                    $this->mount();
                }),
        ];

        if ($member && $member->dependents()->exists()) {
            $actions[] = Action::make('family_requests')
                ->label(__('app.member.my_dependents'))
                ->icon('heroicon-o-users')
                ->url(MyDependentsResource::getUrl())
                ->color('gray');
        }

        return $actions;
    }

    public function getTitle(): string
    {
        return __('app.member.contribution_settings');
    }
}
