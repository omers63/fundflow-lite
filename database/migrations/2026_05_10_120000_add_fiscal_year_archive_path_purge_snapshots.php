<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fiscal_years', function (Blueprint $table): void {
            $table->string('archive_database_path')->nullable()->after('close_metadata');
            $table->timestamp('purged_primary_at')->nullable()->after('archive_database_path');
            $table->json('purge_metadata')->nullable()->after('purged_primary_at');
        });

        Schema::create('fiscal_year_account_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('fiscal_year_id')->constrained('fiscal_years')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            // Balance from `accounts.balance` immediately before purge of this FY slice (opening for next FY)
            $table->decimal('closing_balance', 18, 2);
            $table->timestamps();

            $table->unique(['fiscal_year_id', 'account_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_year_account_snapshots');

        Schema::table('fiscal_years', function (Blueprint $table): void {
            $table->dropColumn(['archive_database_path', 'purged_primary_at', 'purge_metadata']);
        });
    }
};
