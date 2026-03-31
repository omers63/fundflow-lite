<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            // Always stored as a positive amount.
            // entry_type determines whether it adds or subtracts from the balance.
            $table->decimal('amount', 15, 2);
            $table->string('entry_type'); // credit | debit
            $table->text('description')->nullable();
            // Polymorphic link to the source record
            // (Contribution, BankTransaction, SmsTransaction, Loan, LoanInstallment)
            $table->nullableMorphs('source');
            // For master-account entries: which member this posting relates to
            $table->foreignId('member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->foreignId('posted_by')->constrained('users');
            $table->datetime('transacted_at');
            $table->timestamps();

            $table->index(['account_id', 'transacted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_transactions');
    }
};
