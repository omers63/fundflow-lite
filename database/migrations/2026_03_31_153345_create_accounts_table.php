<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            // Stable slug used to look up master accounts in code
            $table->string('slug')->unique()->nullable();
            $table->string('name');
            // master_cash | master_fund | member_cash | member_fund | loan
            $table->string('type');
            $table->foreignId('member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->foreignId('loan_id')->nullable()->constrained('loans')->nullOnDelete();
            // Running balance (credits − debits).
            // Loan accounts: negative means money still owed.
            $table->decimal('balance', 15, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['type', 'member_id']);
        });

        // Seed the two master accounts once
        DB::table('accounts')->insertOrIgnore([
            ['slug' => 'master_cash', 'name' => 'Cash Account', 'type' => 'master_cash',
             'balance' => 0, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['slug' => 'master_fund', 'name' => 'Fund Account', 'type' => 'master_fund',
             'balance' => 0, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
