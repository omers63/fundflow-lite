<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Resources\LoanResource;
use App\Models\LoanInstallment;
use Carbon\Carbon;
use Filament\Widgets\Widget;

class OverdueInstallmentsWidget extends Widget
{
    protected string $view = 'filament.admin.widgets.overdue-installments';

    protected static ?int $sort = 10;

    protected int|string|array $columnSpan = 'full';

    public function getColumnSpan(): int|string|array
    {
        return 'full';
    }

    public function getData(): array
    {
        $items = LoanInstallment::where('status', 'overdue')
            ->with(['loan.member.user', 'loan.loanTier'])
            ->orderBy('due_date')
            ->limit(15)
            ->get()
            ->map(function (LoanInstallment $inst) {
                $daysOverdue = (int) Carbon::parse($inst->due_date)->diffInDays(now(), absolute: true);
                $loan = $inst->loan;

                return [
                    'id'               => $inst->id,
                    'installment_no'   => $inst->installment_number,
                    'due_date'         => $inst->due_date?->format('d M Y'),
                    'amount'           => (float) $inst->amount,
                    'late_fee'         => (float) $inst->late_fee_amount,
                    'days_overdue'     => $daysOverdue,
                    'borrower'         => $loan?->member?->user?->name ?? '—',
                    'member_number'    => $loan?->member?->member_number ?? '—',
                    'loan_id'          => $loan?->id,
                    'loan_tier'        => $loan?->loanTier?->label ?? '—',
                    'loan_url'         => $loan ? LoanResource::getUrl('view', ['record' => $loan->id]) : null,
                    'paid_by_guarantor' => (bool) $inst->paid_by_guarantor,
                ];
            });

        $totalOverdueAmount = LoanInstallment::where('status', 'overdue')->sum('amount');
        $totalOverdueFees   = LoanInstallment::where('status', 'overdue')->sum('late_fee_amount');
        $totalCount         = LoanInstallment::where('status', 'overdue')->count();

        return [
            'items'               => $items,
            'total_count'         => $totalCount,
            'total_overdue_amount' => (float) $totalOverdueAmount,
            'total_overdue_fees'  => (float) $totalOverdueFees,
        ];
    }
}
