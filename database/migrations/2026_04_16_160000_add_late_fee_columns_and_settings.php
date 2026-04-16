<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('contributions', function (Blueprint $table) {
            $table->decimal('late_fee_amount', 12, 2)->nullable()->after('is_late');
        });

        Schema::table('loan_installments', function (Blueprint $table) {
            $table->decimal('late_fee_amount', 12, 2)->nullable()->after('is_late');
        });

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

    public function down(): void
    {
        Schema::table('contributions', function (Blueprint $table) {
            $table->dropColumn('late_fee_amount');
        });

        Schema::table('loan_installments', function (Blueprint $table) {
            $table->dropColumn('late_fee_amount');
        });

        Setting::query()->whereIn('key', [
            'late_fee.contribution_amount',
            'late_fee.repayment_amount',
        ])->delete();
    }
};
