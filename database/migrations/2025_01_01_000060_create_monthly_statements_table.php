<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_statements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->string('period', 7);
            $table->decimal('opening_balance', 12, 2)->default(0);
            $table->decimal('total_contributions', 12, 2)->default(0);
            $table->decimal('total_repayments', 12, 2)->default(0);
            $table->decimal('closing_balance', 12, 2)->default(0);
            $table->timestamp('generated_at')->useCurrent();
            $table->timestamps();

            $table->unique(['member_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_statements');
    }
};
