<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->decimal('amount_disbursed', 15, 2)->default(0)->after('amount_approved');
        });

        // Backfill: loans that were already fully disbursed in the single-shot model.
        DB::table('loans')
            ->whereIn('status', ['active', 'completed', 'early_settled'])
            ->update(['amount_disbursed' => DB::raw('amount_approved')]);
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn('amount_disbursed');
        });
    }
};
