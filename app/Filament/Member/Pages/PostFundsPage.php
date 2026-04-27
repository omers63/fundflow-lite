<?php

namespace App\Filament\Member\Pages;

use App\Models\Bank;
use App\Models\BankTransaction;
use App\Models\Member;
use App\Services\LoanRepaymentService;
use App\Services\QuickPostWorkflowService;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Storage;

class PostFundsPage extends Page
{
    protected string $view = 'filament.member.pages.post-funds';

    protected static bool $shouldRegisterNavigation = false;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-down-circle';

    public function getTitle(): string
    {
        return __('Post Funds');
    }

    public function getHeaderActions(): array
    {
        return [
            Action::make('postFunds')
                ->label(__('Post Funds'))
                ->icon('heroicon-o-arrow-down-circle')
                ->color('success')
                ->modalHeading(__('Post Funds'))
                ->modalDescription(__('Record your transfer. The system will automatically apply it as contribution or repayment based on business rules.'))
                ->modalWidth('lg')
                ->schema($this->postFormSchema())
                ->action(fn(array $data) => $this->runPostWorkflow($data)),
        ];
    }

    public function recentPosts(): array
    {
        $memberId = Member::query()->where('user_id', auth()->id())->value('id');
        if (!$memberId) {
            return [];
        }

        return BankTransaction::query()
            ->where('member_id', $memberId)
            ->where('raw_data->source', 'member_portal_post')
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(function (BankTransaction $tx): array {
                $raw = is_array($tx->raw_data) ? $tx->raw_data : [];
                $apply = (string) ($raw['member_portal_apply'] ?? 'both');

                return [
                    'id' => (int) $tx->id,
                    'date' => optional($tx->transaction_date)?->format('Y-m-d'),
                    'amount' => (float) $tx->amount,
                    'apply' => $apply,
                    'apply_label' => match ($apply) {
                        'contribution' => __('Contribution(s)'),
                        'repayment' => __('Repayment'),
                        default => __('Contribution(s) & Repayment'),
                    },
                    'reference' => (string) ($tx->reference ?? ''),
                    'attachment_url' => is_string($raw['attachment_url'] ?? null) ? $raw['attachment_url'] : null,
                ];
            })
            ->all();
    }

    protected function postFormSchema(): array
    {
        return [
            Forms\Components\DatePicker::make('transaction_date')
                ->label(__('Transaction date'))
                ->required()
                ->default(now()->toDateString()),
            Forms\Components\TextInput::make('amount')
                ->label(__('Amount (SAR)'))
                ->required()
                ->numeric()
                ->minValue(0.01),
            Forms\Components\TextInput::make('reference')
                ->label(__('Reference'))
                ->maxLength(120)
                ->placeholder(__('Bank transfer reference (optional)')),
            Forms\Components\FileUpload::make('attachment')
                ->label(__('Attachment'))
                ->helperText(__('Optional transfer receipt or proof.'))
                ->directory('member-postings')
                ->preserveFilenames()
                ->acceptedFileTypes(['application/pdf', 'image/*'])
                ->maxSize(5120),
        ];
    }

    protected function runPostWorkflow(array $data): void
    {
        $member = Member::query()
            ->where('user_id', auth()->id())
            ->with(['user', 'dependents.user', 'dependents.accounts'])
            ->first();

        if (!$member) {
            Notification::make()
                ->title(__('Member record not found'))
                ->danger()
                ->send();

            return;
        }

        $bank = Bank::query()->active()->orderBy('id')->first() ?? Bank::query()->orderBy('id')->first();
        if (!$bank) {
            Notification::make()
                ->title(__('No active bank is configured'))
                ->body(__('Ask an administrator to configure at least one bank before posting funds.'))
                ->danger()
                ->send();

            return;
        }

        $attachmentPath = $data['attachment'] ?? null;
        $attachmentUrl = null;
        if (is_string($attachmentPath) && $attachmentPath !== '') {
            try {
                $attachmentUrl = Storage::url($attachmentPath);
            } catch (\Throwable) {
                $attachmentUrl = null;
            }
        }

        $apply = $this->resolveApplyMode($member);
        $descriptionPrefix = $apply === 'repayment' ? 'Member self-post repayment' : 'Member self-post contribution';

        try {
            $result = app(QuickPostWorkflowService::class)->runForBank([
                'bank_id' => $bank->id,
                'transaction_date' => $data['transaction_date'],
                'amount' => (float) $data['amount'],
                'transaction_type' => 'credit',
                'reference' => $data['reference'] ?: null,
                'description' => $descriptionPrefix,
                'member_id' => $member->id,
                'apply' => $apply,
                'raw_data' => [
                    'source' => 'member_portal_post',
                    'member_portal_apply' => $apply,
                    'attachment_path' => $attachmentPath,
                    'attachment_url' => $attachmentUrl,
                    'submitted_by_user_id' => auth()->id(),
                ],
            ]);

            $summary = collect($result['steps'])
                ->pluck('note')
                ->filter(fn($note) => is_string($note) && $note !== '')
                ->take(3)
                ->implode(' · ');

            Notification::make()
                ->title(__('Funds posted successfully'))
                ->body($summary !== '' ? $summary : __('Funds were posted successfully.'))
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title(__('Posting failed'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function resolveApplyMode(Member $member): string
    {
        if ($member->isExemptFromContributions()) {
            return 'repayment';
        }

        $repaymentDue = app(LoanRepaymentService::class)->shouldOfferOpenPeriodRepayment($member);
        if ($repaymentDue) {
            return 'repayment';
        }

        return 'contribution';
    }
}

