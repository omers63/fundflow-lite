<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        $legacyContribution = (float) (Setting::query()->where('key', 'late_fee.contribution_amount')->value('value') ?? 0);
        $legacyRepayment = (float) (Setting::query()->where('key', 'late_fee.repayment_amount')->value('value') ?? 0);

        $tierLabels = [
            1 => '1+ calendar days after due',
            10 => '10+ calendar days after due',
            20 => '20+ calendar days after due',
            30 => '30+ calendar days after due',
        ];

        foreach ([1, 10, 20, 30] as $d) {
            $ck = "late_fee.contribution_day_{$d}";
            if (Setting::query()->where('key', $ck)->doesntExist()) {
                Setting::create([
                    'key' => $ck,
                    'value' => (string) $legacyContribution,
                    'label' => "Contribution late fee (SAR) — {$tierLabels[$d]}",
                    'group' => 'late_fee',
                ]);
            }

            $rk = "late_fee.repayment_day_{$d}";
            if (Setting::query()->where('key', $rk)->doesntExist()) {
                Setting::create([
                    'key' => $rk,
                    'value' => (string) $legacyRepayment,
                    'label' => "Repayment late fee (SAR) — {$tierLabels[$d]}",
                    'group' => 'late_fee',
                ]);
            }
        }

        Setting::withTrashed()->whereIn('key', [
            'late_fee.contribution_amount',
            'late_fee.repayment_amount',
        ])->forceDelete();
    }

    public function down(): void
    {
        Setting::query()->whereIn('key', [
            'late_fee.contribution_day_1',
            'late_fee.contribution_day_10',
            'late_fee.contribution_day_20',
            'late_fee.contribution_day_30',
            'late_fee.repayment_day_1',
            'late_fee.repayment_day_10',
            'late_fee.repayment_day_20',
            'late_fee.repayment_day_30',
        ])->delete();

        if (Setting::query()->where('key', 'late_fee.contribution_amount')->doesntExist()) {
            Setting::create([
                'key' => 'late_fee.contribution_amount',
                'value' => '0',
                'label' => 'Late fee per late contribution (SAR), 0 = none',
                'group' => 'late_fee',
            ]);
        }
        if (Setting::query()->where('key', 'late_fee.repayment_amount')->doesntExist()) {
            Setting::create([
                'key' => 'late_fee.repayment_amount',
                'value' => '0',
                'label' => 'Late fee per late loan repayment installment (SAR), 0 = none',
                'group' => 'late_fee',
            ]);
        }
    }
};
