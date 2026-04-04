<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Bank;
use App\Models\BankImportSession;
use App\Models\BankImportTemplate;
use App\Models\BankTransaction;
use App\Models\SmsImportSession;
use App\Models\SmsImportTemplate;
use App\Models\SmsTransaction;
use Carbon\Carbon;
use Filament\Widgets\Widget;

class BankingStatsWidget extends Widget
{
    protected static bool $isDiscovered = false;

    protected string $view = 'filament.admin.widgets.banking-stats';

    protected int|string|array $columnSpan = 'full';

    public function getData(): array
    {
        $now = Carbon::now();

        $banks = Bank::count();
        $activeBanks = Bank::active()->count();

        $bankTemplates = BankImportTemplate::count();
        $smsTemplates = SmsImportTemplate::count();

        $bankTxTotal = BankTransaction::count();
        $smsTxTotal = SmsTransaction::count();

        $bankTxDupes = BankTransaction::where('is_duplicate', true)->count();
        $smsTxDupes = SmsTransaction::where('is_duplicate', true)->count();

        $bankTxPosted = BankTransaction::whereNotNull('posted_at')->count();
        $smsTxPosted = SmsTransaction::whereNotNull('posted_at')->count();

        // This month imports
        $bankSessionsThisMonth = BankImportSession::whereMonth('created_at', $now->month)
            ->whereYear('created_at', $now->year)
            ->count();
        $smsSessionsThisMonth = SmsImportSession::whereMonth('created_at', $now->month)
            ->whereYear('created_at', $now->year)
            ->count();

        // Totals
        $totalTx = $bankTxTotal + $smsTxTotal;
        $totalDupes = $bankTxDupes + $smsTxDupes;
        $totalPosted = $bankTxPosted + $smsTxPosted;
        $dupeRate = $totalTx > 0 ? round($totalDupes / $totalTx * 100, 1) : 0;
        $postRate = $totalTx > 0 ? round($totalPosted / $totalTx * 100, 1) : 0;

        // 6-month import activity
        $trend = [];
        for ($i = 5; $i >= 0; $i--) {
            $d = Carbon::now()->subMonths($i)->startOfMonth();
            $bankCount = BankTransaction::whereYear('created_at', $d->year)
                ->whereMonth('created_at', $d->month)->count();
            $smsCount = SmsTransaction::whereYear('created_at', $d->year)
                ->whereMonth('created_at', $d->month)->count();
            $trend[] = [
                'label' => $d->format('M'),
                'bank' => $bankCount,
                'sms' => $smsCount,
                'total' => $bankCount + $smsCount,
            ];
        }

        // Recent import sessions (last 5)
        $recentSessions = BankImportSession::with('bank')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn ($s) => [
                'bank' => $s->bank?->name ?? '—',
                'filename' => $s->filename,
                'status' => $s->status,
                'imported' => $s->imported_count,
                'duplicates' => $s->duplicate_count,
                'errors' => $s->error_count,
                'date' => Carbon::parse($s->created_at)->diffForHumans(),
            ])->toArray();

        return [
            'banks' => $banks,
            'active_banks' => $activeBanks,
            'bank_templates' => $bankTemplates,
            'sms_templates' => $smsTemplates,
            'bank_tx_total' => $bankTxTotal,
            'sms_tx_total' => $smsTxTotal,
            'total_tx' => $totalTx,
            'total_dupes' => $totalDupes,
            'total_posted' => $totalPosted,
            'dupe_rate' => $dupeRate,
            'post_rate' => $postRate,
            'bank_sessions_month' => $bankSessionsThisMonth,
            'sms_sessions_month' => $smsSessionsThisMonth,
            'trend' => $trend,
            'recent_sessions' => $recentSessions,
        ];
    }
}
