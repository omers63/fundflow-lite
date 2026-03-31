<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_import_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_default')->default(false);

            // CSV format settings
            $table->string('delimiter')->default(',');
            $table->string('encoding')->default('UTF-8');
            $table->boolean('has_header')->default(true);
            $table->unsignedTinyInteger('skip_rows')->default(0);

            // Column mapping — each value is a column name (when has_header=true)
            // or a 0-based integer index (when has_header=false)
            $table->string('date_column');
            $table->string('date_format')->default('Y-m-d');

            // Amount columns: 'single' (one col, +/-) or 'split' (separate credit/debit cols)
            $table->string('amount_type')->default('single');
            $table->string('amount_column')->nullable();
            $table->string('credit_column')->nullable();
            $table->string('debit_column')->nullable();

            // Optional: a column that explicitly indicates CR/DR
            $table->string('type_column')->nullable();
            $table->string('credit_indicator')->nullable()->default('CR');
            $table->string('debit_indicator')->nullable()->default('DR');

            $table->string('description_column')->nullable();
            $table->string('reference_column')->nullable();

            // Duplicate detection rules
            $table->json('duplicate_match_fields')->nullable();
            $table->unsignedTinyInteger('duplicate_date_tolerance')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_import_templates');
    }
};
