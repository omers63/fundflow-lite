<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_import_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->boolean('is_default')->default(false);

            // --- CSV file format ---
            $table->string('delimiter')->default(',');
            $table->string('encoding')->default('UTF-8');
            $table->boolean('has_header')->default(true);
            $table->unsignedTinyInteger('skip_rows')->default(0);

            // --- Column mapping ---
            // Column that holds the raw SMS text
            $table->string('sms_column');
            // Optional column that holds the message received/sent date
            $table->string('date_column')->nullable();
            $table->string('date_format')->default('Y-m-d H:i:s');

            // --- Regex extraction patterns (applied to the SMS text) ---
            // Named capture group "amount" is extracted; strip commas before parsing
            $table->string('amount_pattern')->nullable()
                ->comment('Regex with named group "amount". e.g. /SAR\s*(?P<amount>[\d,]+\.?\d*)/i');
            // Named capture group "date" — used only when date_column is absent
            $table->string('date_pattern')->nullable()
                ->comment('Regex with named group "date". e.g. /on\s+(?P<date>\d{2}\/\d{2}\/\d{4})/i');
            $table->string('date_pattern_format')->nullable()
                ->comment('PHP date format string for the date extracted by date_pattern. e.g. d/m/Y');
            // Named capture group "reference"
            $table->string('reference_pattern')->nullable()
                ->comment('Regex with named group "reference". e.g. /[Rr]ef[:\s]+(?P<reference>\d+)/');

            // --- Transaction type detection ---
            // JSON arrays of keywords (case-insensitive) found in the SMS text
            $table->json('credit_keywords')->nullable()
                ->comment('e.g. ["credited","received","deposit","credit"]');
            $table->json('debit_keywords')->nullable()
                ->comment('e.g. ["debited","paid","purchase","debit","withdraw"]');
            // Fallback when no keyword matches: 'credit' or 'debit'
            $table->string('default_transaction_type')->default('credit');

            // --- Duplicate detection ---
            $table->json('duplicate_match_fields')->nullable()
                ->comment('Subset of: date, amount, type, reference, raw_sms');
            $table->unsignedTinyInteger('duplicate_date_tolerance')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_import_templates');
    }
};
