<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('loan_installments', function (Blueprint $table) {
            $table->boolean('show_as_loan_repayment_in_collections')
                ->default(true)
                ->after('paid_by_guarantor');
        });
    }

    public function down(): void
    {
        Schema::table('loan_installments', function (Blueprint $table) {
            $table->dropColumn('show_as_loan_repayment_in_collections');
        });
    }
};
