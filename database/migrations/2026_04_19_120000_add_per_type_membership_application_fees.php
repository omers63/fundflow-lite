<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $legacy = (float) (Setting::get('membership.application_fee_amount', 0) ?? 0);
        $legacy = max(0.0, $legacy);

        foreach (['new' => 'New', 'resume' => 'Resume', 'renew' => 'Renew'] as $suffix => $label) {
            $key = "membership.application_fee_{$suffix}";
            if (Setting::query()->where('key', $key)->exists()) {
                continue;
            }
            Setting::create([
                'key' => $key,
                'value' => (string) $legacy,
                'label' => "Public apply: {$label} membership application fee (SAR), 0 = none",
                'group' => 'membership',
            ]);
        }
    }

    public function down(): void
    {
        Setting::query()->whereIn('key', [
            'membership.application_fee_new',
            'membership.application_fee_resume',
            'membership.application_fee_renew',
        ])->delete();
    }
};
