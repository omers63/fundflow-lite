<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_id')->constrained()->cascadeOnDelete();
            $table->foreignId('import_session_id')->constrained('bank_import_sessions')->cascadeOnDelete();
            $table->date('transaction_date');
            $table->decimal('amount', 15, 2);
            $table->string('transaction_type')->default('credit'); // credit | debit
            $table->text('description')->nullable();
            $table->string('reference')->nullable();
            $table->boolean('is_duplicate')->default(false);
            $table->foreignId('duplicate_of_id')->nullable()->constrained('bank_transactions')->nullOnDelete();
            $table->json('raw_data')->nullable();
            $table->timestamps();

            $table->index(['bank_id', 'transaction_date']);
            $table->index(['bank_id', 'reference']);
            $table->index('is_duplicate');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_transactions');
    }
};
