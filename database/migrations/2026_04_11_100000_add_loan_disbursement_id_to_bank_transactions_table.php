<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->foreignId('loan_disbursement_id')
                ->nullable()
                ->after('loan_id')
                ->constrained('loan_disbursements')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->dropForeign(['loan_disbursement_id']);
            $table->dropColumn('loan_disbursement_id');
        });
    }
};
