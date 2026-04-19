<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_import_templates', function (Blueprint $table) {
            if (! Schema::hasColumn('bank_import_templates', 'balance_column')) {
                $table->string('balance_column')->nullable()->after('reference_column');
            }
        });

        Schema::table('bank_transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('bank_transactions', 'running_balance')) {
                $table->decimal('running_balance', 15, 2)->nullable()->after('amount');
                $table->index(['bank_id', 'running_balance']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('bank_transactions', function (Blueprint $table) {
            if (Schema::hasColumn('bank_transactions', 'running_balance')) {
                $table->dropIndex(['bank_id', 'running_balance']);
                $table->dropColumn('running_balance');
            }
        });

        Schema::table('bank_import_templates', function (Blueprint $table) {
            if (Schema::hasColumn('bank_import_templates', 'balance_column')) {
                $table->dropColumn('balance_column');
            }
        });
    }
};
