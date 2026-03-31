<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('import_session_id')->constrained('sms_import_sessions')->cascadeOnDelete();
            $table->date('transaction_date')->nullable();
            $table->decimal('amount', 15, 2)->nullable();
            $table->string('transaction_type')->default('credit'); // credit | debit
            $table->string('reference')->nullable();
            $table->text('raw_sms');
            $table->json('raw_data')->nullable();
            $table->boolean('is_duplicate')->default(false);
            $table->foreignId('duplicate_of_id')->nullable()->constrained('sms_transactions')->nullOnDelete();
            $table->timestamps();

            $table->index(['bank_id', 'transaction_date']);
            $table->index(['bank_id', 'reference']);
            $table->index('is_duplicate');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_transactions');
    }
};
