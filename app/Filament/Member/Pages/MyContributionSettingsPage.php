<?php

namespace App\Filament\Member\Pages;

use App\Models\Member;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class MyContributionSettingsPage extends Page
{
    protected string $view = 'filament.member.pages.my-contribution-settings';

    protected static ?string $navigationLabel = 'Contribution Settings';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?int $navigationSort = 10;

    public int $monthly_contribution_amount = 500;

    public static function getNavigationGroup(): ?string
    {
        return __('app.nav.group.account');
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
        return [
            Action::make('save_allocation')
                ->label('Update My Allocation')
                ->icon('heroicon-o-check')
                ->color('primary')
                ->fillForm(['monthly_contribution_amount' => $this->monthly_contribution_amount])
                ->schema([
                    Forms\Components\Select::make('monthly_contribution_amount')
                        ->label('Monthly Contribution Amount')
                        ->options(Member::contributionAmountOptions())
                        ->required()
                        ->helperText('Select a multiple of SAR 500, from SAR 500 to SAR 3,000.'),
                ])
                ->action(function (array $data) {
                    $member = $this->currentMember();

                    if (!$member) {
                        Notification::make()->title('Member record not found.')->danger()->send();

                        return;
                    }

                    $member->update([
                        'monthly_contribution_amount' => $data['monthly_contribution_amount'],
                    ]);

                    $this->monthly_contribution_amount = $data['monthly_contribution_amount'];

                    Notification::make()
                        ->title('Allocation Updated')
                        ->body('Your monthly contribution amount is now SAR ' . number_format($data['monthly_contribution_amount']))
                        ->success()
                        ->send();
                }),
        ];
    }

    public function getTitle(): string
    {
        return 'Contribution Settings';
    }
}
