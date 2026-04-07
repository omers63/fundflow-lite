<?php

namespace App\Filament\Admin\Widgets;

use App\Models\MembershipApplication;
use Carbon\Carbon;
use Filament\Widgets\Widget;

class ApplicationStatsWidget extends Widget
{
    protected static bool $isLazy = false;

    protected string $view = 'filament.admin.widgets.application-stats';

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = '3s';

    public function getPollingInterval(): ?string
    {
        return $this->pollingInterval;
    }

    public function getData(): array
    {
        $now = Carbon::now();

        $pending = MembershipApplication::where('status', 'pending')->count();
        $approved = MembershipApplication::where('status', 'approved')->count();
        $rejected = MembershipApplication::where('status', 'rejected')->count();
        $total = $pending + $approved + $rejected;

        $newThisMonth = MembershipApplication::whereMonth('created_at', $now->month)
            ->whereYear('created_at', $now->year)
            ->count();

        $approvedThisMonth = MembershipApplication::where('status', 'approved')
            ->whereMonth('reviewed_at', $now->month)
            ->whereYear('reviewed_at', $now->year)
            ->count();

        $rejectedThisMonth = MembershipApplication::where('status', 'rejected')
            ->whereMonth('reviewed_at', $now->month)
            ->whereYear('reviewed_at', $now->year)
            ->count();

        // Average days to review (for approved/rejected) — Carbon avoids SQLite-only JULIANDAY / MySQL-only quirks
        $reviewedApplications = MembershipApplication::whereIn('status', ['approved', 'rejected'])
            ->whereNotNull('reviewed_at')
            ->get(['created_at', 'reviewed_at']);

        $avgReviewDays = $reviewedApplications->isEmpty()
            ? 0.0
            : (float) $reviewedApplications->avg(
                fn(MembershipApplication $a): float => (float) Carbon::parse($a->created_at)->diffInDays(Carbon::parse($a->reviewed_at))
            );

        // Recent pending (oldest first — need attention)
        $recentPending = MembershipApplication::where('status', 'pending')
            ->with('user')
            ->orderBy('created_at')
            ->limit(5)
            ->get()
            ->map(fn($a) => [
                'name' => $a->user?->name ?? '—',
                'email' => $a->user?->email ?? '—',
                'days_ago' => (int) Carbon::parse($a->created_at)->diffInDays(now()),
                'type' => $a->application_type ?? 'new',
            ])
            ->toArray();

        // 6-month application volumes
        $trend = [];
        for ($i = 5; $i >= 0; $i--) {
            $d = Carbon::now()->subMonths($i)->startOfMonth();
            $row = MembershipApplication::whereYear('created_at', $d->year)
                ->whereMonth('created_at', $d->month)
                ->selectRaw("
                    COUNT(*) as total,
                    SUM(CASE WHEN status='approved' THEN 1 ELSE 0 END) as approved,
                    SUM(CASE WHEN status='rejected' THEN 1 ELSE 0 END) as rejected,
                    SUM(CASE WHEN status='pending'  THEN 1 ELSE 0 END) as pending
                ")
                ->first();
            $trend[] = [
                'label' => $d->format('M'),
                'total' => (int) ($row->total ?? 0),
                'approved' => (int) ($row->approved ?? 0),
                'rejected' => (int) ($row->rejected ?? 0),
                'pending' => (int) ($row->pending ?? 0),
            ];
        }

        return [
            'total' => $total,
            'pending' => $pending,
            'approved' => $approved,
            'rejected' => $rejected,
            'new_this_month' => $newThisMonth,
            'approved_this_month' => $approvedThisMonth,
            'rejected_this_month' => $rejectedThisMonth,
            'avg_review_days' => round($avgReviewDays, 1),
            'recent_pending' => $recentPending,
            'trend' => $trend,
        ];
    }
}
