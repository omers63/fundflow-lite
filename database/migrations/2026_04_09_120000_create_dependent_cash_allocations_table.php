<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('dependent_cash_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_member_id')->constrained('members')->cascadeOnDelete();
            $table->foreignId('dependent_member_id')->constrained('members')->cascadeOnDelete();
            $table->unsignedTinyInteger('allocation_month');
            $table->unsignedSmallInteger('allocation_year');
            $table->decimal('amount', 15, 2);
            $table->timestamps();

            $table->unique(
                ['dependent_member_id', 'allocation_month', 'allocation_year'],
                'dependent_cash_alloc_cycle_unique'
            );
            $table->index('parent_member_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dependent_cash_allocations');
    }
};
