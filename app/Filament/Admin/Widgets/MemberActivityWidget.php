<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Contribution;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\Member;
use Carbon\Carbon;
use Filament\Widgets\Widget;

class MemberActivityWidget extends Widget
{
    protected string $view = 'filament.admin.widgets.member-activity';

    public ?Member $record = null;

    protected int|string|array $columnSpan = 'full';

    public function getColumnSpan(): int|string|array
    {
        return 'full';
    }

    public function getData(): array
    {
        if (!$this->record) {
            return ['hasRecord' => false];
        }

        $member = $this->record;

        // ── Contribution grid (12 months) ─────────────────────────────────
        $months = collect(range(11, 0))->map(fn($i) => now()->subMonths($i)->startOfMonth());
        $contribs = Contribution::where('member_id', $member->id)
            ->orderByRaw('year DESC, month DESC')
            ->get();

        $monthlyContrib = $member->monthly_contribution_amount;

        $grid = $months->map(function (Carbon $m) use ($contribs, $monthlyContrib) {
            $row = $contribs->first(fn($c) => $c->month == $m->month && $c->year == $m->year);

            return [
                'label' => $m->format('M y'),
                'amount' => $row ? (float) $row->amount : 0.0,
                'paid' => $row !== null,
                'late' => $row ? ($row->amount < $monthlyContrib) : false,
                'future' => $m->isFuture(),
            ];
        });

        $paidCount = $grid->where('paid', true)->count();
        $missedCount = $grid->where('paid', false)->where('future', false)->count();

        // ── Loan installments (last 8 installments) ───────────────────────
        $installments = LoanInstallment::whereHas('loan', fn($q) => $q->where('member_id', $member->id))
            ->with('loan')
            ->orderBy('due_date', 'desc')
            ->limit(8)
            ->get()
            ->map(fn($i) => [
                'loan_id' => $i->loan_id,
                'due_date' => $i->due_date instanceof Carbon ? $i->due_date->format('d M Y') : $i->due_date,
                'amount' => number_format((float) $i->amount, 2),
                'status' => $i->status,
                'is_late' => (bool) ($i->is_late ?? false),
                'paid_at' => $i->paid_at ? Carbon::parse($i->paid_at)->format('d M Y') : null,
            ]);

        // ── Recent loans ───────────────────────────────────────────────────
        $loans = Loan::where('member_id', $member->id)
            ->orderBy('applied_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function (Loan $l) {
                return [
                    'id' => $l->id,
                    'amount' => number_format((float) ($l->amount_approved ?? $l->amount_requested), 2),
                    'status' => $l->status,
                    'applied_at' => $l->applied_at ? Carbon::parse($l->applied_at)->format('d M Y') : '—',
                    'disbursed_at' => $l->disbursed_at ? Carbon::parse($l->disbursed_at)->format('d M Y') : null,
                    'fully_paid_at' => $l->fully_paid_at ? Carbon::parse($l->fully_paid_at)->format('d M Y') : null,
                    'installment_count' => (int) ($l->installments_count ?? 0),
                ];
            });

        // ── Chart data ────────────────────────────────────────────────────
        $chartLabels = $grid->pluck('label')->values()->toArray();
        $chartData = $grid->pluck('amount')->values()->toArray();

        return [
            'hasRecord' => true,
            'monthly_contrib' => $monthlyContrib,
            'grid' => $grid->values()->toArray(),
            'paid_count' => $paidCount,
            'missed_count' => $missedCount,
            'chart_labels' => $chartLabels,
            'chart_data' => $chartData,
            'installments' => $installments->toArray(),
            'loans' => $loans->toArray(),
        ];
    }
}
