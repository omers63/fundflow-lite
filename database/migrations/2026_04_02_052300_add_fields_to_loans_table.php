<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Extend the status enum to cover all lifecycle states
        DB::statement("ALTER TABLE loans MODIFY COLUMN status ENUM(
            'pending','approved','rejected','active','disbursed',
            'completed','early_settled','cancelled'
        ) NOT NULL DEFAULT 'pending'");

        Schema::table('loans', function (Blueprint $table) {
            // Tier references
            $table->foreignId('loan_tier_id')->nullable()->after('member_id')
                ->constrained('loan_tiers')->nullOnDelete();
            $table->foreignId('fund_tier_id')->nullable()->after('loan_tier_id')
                ->constrained('fund_tiers')->nullOnDelete();

            // Queue position within the fund tier queue
            $table->unsignedSmallInteger('queue_position')->nullable()->after('fund_tier_id');

            // Guarantor & witnesses
            $table->foreignId('guarantor_member_id')->nullable()->after('approved_by_id')
                ->constrained('members')->nullOnDelete();
            $table->timestamp('guarantor_released_at')->nullable()->after('guarantor_member_id');
            $table->string('witness1_name', 255)->nullable()->after('guarantor_released_at');
            $table->string('witness1_phone', 50)->nullable()->after('witness1_name');
            $table->string('witness2_name', 255)->nullable()->after('witness1_phone');
            $table->string('witness2_phone', 50)->nullable()->after('witness2_name');

            // Disbursement portions (how the loan was funded)
            $table->decimal('member_portion', 12, 2)->nullable()->after('amount_approved');
            $table->decimal('master_portion', 12, 2)->nullable()->after('member_portion');
            $table->decimal('repaid_to_master', 12, 2)->default(0)->after('master_portion');

            // Contribution exemption & first repayment tracking
            $table->unsignedTinyInteger('exempted_month')->nullable()->after('disbursed_at');
            $table->unsignedSmallInteger('exempted_year')->nullable()->after('exempted_month');
            $table->unsignedTinyInteger('first_repayment_month')->nullable()->after('exempted_year');
            $table->unsignedSmallInteger('first_repayment_year')->nullable()->after('first_repayment_month');

            // Settlement threshold snapshot (e.g. 0.16)
            $table->decimal('settlement_threshold', 5, 4)->default(0.1600)->after('first_repayment_year');

            // Late repayment tracking per loan
            $table->unsignedSmallInteger('late_repayment_count')->default(0)->after('settlement_threshold');
            $table->decimal('late_repayment_amount', 12, 2)->default(0)->after('late_repayment_count');

            // Settlement & cancellation timestamps/reasons
            $table->timestamp('settled_at')->nullable()->after('due_date');
            $table->text('cancellation_reason')->nullable()->after('rejection_reason');
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropForeign(['loan_tier_id']);
            $table->dropForeign(['fund_tier_id']);
            $table->dropForeign(['guarantor_member_id']);
            $table->dropColumn([
                'loan_tier_id', 'fund_tier_id', 'queue_position',
                'guarantor_member_id', 'guarantor_released_at',
                'witness1_name', 'witness1_phone', 'witness2_name', 'witness2_phone',
                'member_portion', 'master_portion', 'repaid_to_master',
                'exempted_month', 'exempted_year',
                'first_repayment_month', 'first_repayment_year',
                'settlement_threshold', 'late_repayment_count', 'late_repayment_amount',
                'settled_at', 'cancellation_reason',
            ]);
        });

        DB::statement("ALTER TABLE loans MODIFY COLUMN status ENUM(
            'pending','approved','rejected','active','completed'
        ) NOT NULL DEFAULT 'pending'");
    }
};
