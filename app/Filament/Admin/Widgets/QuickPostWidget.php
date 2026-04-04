<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Bank;
use App\Models\Member;
use App\Services\QuickPostWorkflowService;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;
use Livewire\Attributes\Computed;

class QuickPostWidget extends Widget
{
    protected string $view = 'filament.admin.widgets.quick-post';

    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    public function getColumnSpan(): int|string|array
    {
        return 'full';
    }

    // ── Modal state ──────────────────────────────────────────────────────────
    public bool $showModal = false;

    public string $txType = 'bank'; // 'bank' | 'sms'

    // Form fields
    public ?int $bankId = null;

    public string $transactionDate = '';

    public float $amount = 0;

    public string $transactionType = 'credit';

    public string $reference = '';

    public string $description = '';

    public string $rawSms = '';

    public ?int $memberId = null;

    // Result
    public bool $showResult = false;

    public array $resultSteps = [];

    public string $resultTxId = '';

    public bool $resultError = false;

    public string $resultErrorMessage = '';

    public function mount(): void
    {
        $this->transactionDate = now()->format('Y-m-d');
    }

    #[Computed]
    public function banks(): array
    {
        return Bank::active()->pluck('name', 'id')->toArray();
    }

    #[Computed]
    public function members(): array
    {
        return Member::with('user')
            ->active()
            ->get()
            ->mapWithKeys(fn(Member $m) => [$m->id => "{$m->member_number} – {$m->user->name}"])
            ->toArray();
    }

    public function openModal(string $type): void
    {
        $this->txType = $type;
        $this->resetForm();
        $this->showModal = true;
        $this->showResult = false;
        $this->resultError = false;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->showResult = false;
        $this->resultError = false;
    }

    public function submit(): void
    {
        $this->validate($this->txType === 'bank' ? $this->bankRules() : $this->smsRules());

        try {
            $service = app(QuickPostWorkflowService::class);

            if ($this->txType === 'bank') {
                $result = $service->runForBank([
                    'bank_id' => $this->bankId,
                    'transaction_date' => $this->transactionDate,
                    'amount' => $this->amount,
                    'transaction_type' => $this->transactionType,
                    'reference' => $this->reference ?: null,
                    'description' => $this->description ?: null,
                    'member_id' => $this->memberId,
                ]);
            } else {
                $result = $service->runForSms([
                    'bank_id' => $this->bankId,
                    'transaction_date' => $this->transactionDate,
                    'amount' => $this->amount,
                    'transaction_type' => $this->transactionType,
                    'reference' => $this->reference ?: null,
                    'raw_sms' => $this->rawSms ?: '(manual)',
                    'member_id' => $this->memberId,
                ]);
            }

            $this->resultSteps = $result['steps'];
            $this->resultTxId = (string) $result['tx']->id;
            $this->showResult = true;
            $this->resultError = false;

            Notification::make()
                ->title('Workflow completed')
                ->body(count($result['steps']) . ' steps processed successfully.')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            $this->resultError = true;
            $this->resultErrorMessage = $e->getMessage();
            $this->showResult = true;
        }
    }

    private function resetForm(): void
    {
        $this->bankId = null;
        $this->transactionDate = now()->format('Y-m-d');
        $this->amount = 0;
        $this->transactionType = 'credit';
        $this->reference = '';
        $this->description = '';
        $this->rawSms = '';
        $this->memberId = null;
    }

    private function bankRules(): array
    {
        return [
            'bankId' => ['required', 'integer', 'exists:banks,id'],
            'transactionDate' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'transactionType' => ['required', 'in:credit,debit'],
            'memberId' => ['required', 'integer', 'exists:members,id'],
        ];
    }

    private function smsRules(): array
    {
        return [
            'transactionDate' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'transactionType' => ['required', 'in:credit,debit'],
            'memberId' => ['required', 'integer', 'exists:members,id'],
        ];
    }
}
