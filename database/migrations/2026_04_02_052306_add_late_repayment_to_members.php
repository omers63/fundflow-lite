<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->unsignedSmallInteger('late_repayment_count')->default(0)->after('late_contributions_amount');
            $table->decimal('late_repayment_amount', 12, 2)->default(0)->after('late_repayment_count');
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn(['late_repayment_count', 'late_repayment_amount']);
        });
    }
};
