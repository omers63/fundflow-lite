<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_installments', function (Blueprint $table) {
            $table->boolean('is_late')->default(false)->after('status');
            $table->boolean('paid_by_guarantor')->default(false)->after('is_late');
        });
    }

    public function down(): void
    {
        Schema::table('loan_installments', function (Blueprint $table) {
            $table->dropColumn(['is_late', 'paid_by_guarantor']);
        });
    }
};
