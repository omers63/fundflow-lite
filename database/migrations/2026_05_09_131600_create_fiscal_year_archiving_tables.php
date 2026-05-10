<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscal_years', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique(); // ex: FY2026
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status')->default('open'); // open|closing|closed|restoring
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('close_metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('fiscal_year_closures', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('fiscal_year_id')->constrained('fiscal_years')->cascadeOnDelete();
            $table->string('action'); // close|restore|dry_run
            $table->string('status')->default('started'); // started|completed|failed
            $table->string('archive_connection')->default('archive');
            $table->string('archive_batch_id')->nullable()->index();
            $table->foreignId('started_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('finished_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->json('row_counts')->nullable();
            $table->json('integrity_checks')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_year_closures');
        Schema::dropIfExists('fiscal_years');
    }
};
