<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->unsignedSmallInteger('late_contributions_count')->default(0)->after('monthly_contribution_amount');
            $table->decimal('late_contributions_amount', 12, 2)->default(0)->after('late_contributions_count');
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn(['late_contributions_count', 'late_contributions_amount']);
        });
    }
};
