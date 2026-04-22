<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_subscription_fees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->smallInteger('year')->unsigned();
            $table->decimal('amount', 15, 2);
            $table->datetime('paid_at');
            $table->text('notes')->nullable();
            $table->foreignId('account_transaction_id')->nullable()->constrained('account_transactions')->nullOnDelete();
            $table->foreignId('posted_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            // One subscription per member per year
            $table->unique(['member_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_subscription_fees');
    }
};
