<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->timestamp('delinquency_suspended_at')->nullable()->after('status');
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->timestamp('guarantor_liability_transferred_at')->nullable()->after('guarantor_released_at');
        });

        DB::table('members')->where('status', 'delinquent')->update([
            'status' => 'suspended',
            'delinquency_suspended_at' => now(),
        ]);

        if (Setting::query()->where('key', 'delinquency.consecutive_miss_threshold')->doesntExist()) {
            Setting::create([
                'key' => 'delinquency.consecutive_miss_threshold',
                'value' => '3',
                'label' => 'Delinquency: consecutive miss threshold',
                'group' => 'delinquency',
            ]);
        }
        if (Setting::query()->where('key', 'delinquency.total_miss_threshold')->doesntExist()) {
            Setting::create([
                'key' => 'delinquency.total_miss_threshold',
                'value' => '15',
                'label' => 'Delinquency: total miss threshold (rolling window)',
                'group' => 'delinquency',
            ]);
        }
        if (Setting::query()->where('key', 'delinquency.total_miss_lookback_months')->doesntExist()) {
            Setting::create([
                'key' => 'delinquency.total_miss_lookback_months',
                'value' => '60',
                'label' => 'Delinquency: rolling window for total misses (months)',
                'group' => 'delinquency',
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn('delinquency_suspended_at');
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn('guarantor_liability_transferred_at');
        });

        Setting::query()->whereIn('key', [
            'delinquency.consecutive_miss_threshold',
            'delinquency.total_miss_threshold',
            'delinquency.total_miss_lookback_months',
        ])->delete();
    }
};
