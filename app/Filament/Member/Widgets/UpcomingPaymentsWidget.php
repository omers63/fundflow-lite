<?php

namespace App\Filament\Member\Widgets;

use App\Models\Contribution;
use App\Models\LoanInstallment;
use Carbon\Carbon;
use Filament\Widgets\Widget;

class UpcomingPaymentsWidget extends Widget
{
    protected static ?int $sort = 3;

    protected string $view = 'filament.member.widgets.upcoming-payments';

    protected int|string|array $columnSpan = 'full';

    public function getData(): array
    {
        $member = auth()->user()?->member;
        if (! $member) {
            return ['months' => [], 'member' => null];
        }

        $months = [];

        for ($i = 0; $i < 3; $i++) {
            $date = Carbon::now()->addMonths($i)->startOfMonth();
            $m = (int) $date->month;
            $y = (int) $date->year;

            // Contribution for this month (already paid or expected)
            $contribution = Contribution::where('member_id', $member->id)
                ->where('month', $m)
                ->where('year', $y)
                ->first();

            // Installments due in this month
            $installments = LoanInstallment::whereHas('loan', fn ($q) => $q->where('member_id', $member->id))
                ->whereYear('due_date', $y)
                ->whereMonth('due_date', $m)
                ->with('loan.loanTier')
                ->orderBy('due_date')
                ->get();

            $totalDue = ($contribution ? 0 : (float) ($member->monthly_contribution_amount ?? 500))
                + $installments->whereIn('status', ['pending', 'overdue'])->sum('amount');

            $months[] = [
                'label' => $date->locale(app()->getLocale())->translatedFormat('F Y'),
                'is_current' => $i === 0,
                'contribution' => [
                    'amount' => (float) ($member->monthly_contribution_amount ?? 500),
                    'paid' => $contribution !== null,
                    'is_late' => $contribution?->is_late ?? false,
                ],
                'installments' => $installments->map(fn ($inst) => [
                    'amount' => (float) $inst->amount,
                    'due_date' => $inst->due_date->locale(app()->getLocale())->translatedFormat('d M'),
                    'status' => $inst->status,
                    'loan_id' => $inst->loan_id,
                    'tier' => $inst->loan?->loanTier?->label ?? __('Loan #:id', ['id' => $inst->loan_id]),
                ])->values()->toArray(),
                'total_due' => $totalDue,
            ];
        }

        return ['months' => $months, 'member' => $member];
    }
}
