<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('accounts')
            ->where('slug', 'master_investment_fund')
            ->update(['name' => 'Investment Account', 'updated_at' => now()]);

        DB::table('accounts')
            ->where('slug', 'master_expense_account')
            ->update(['name' => 'Expense Account', 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('accounts')
            ->where('slug', 'master_investment_fund')
            ->update(['name' => 'Master Investment Fund', 'updated_at' => now()]);

        DB::table('accounts')
            ->where('slug', 'master_expense_account')
            ->update(['name' => 'Master Expense Account', 'updated_at' => now()]);
    }
};

