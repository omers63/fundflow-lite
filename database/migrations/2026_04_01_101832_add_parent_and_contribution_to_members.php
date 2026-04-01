<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            // Self-referential: a member can have one parent member
            $table->foreignId('parent_id')
                ->nullable()
                ->after('user_id')
                ->constrained('members')
                ->nullOnDelete();

            // Allocated monthly contribution in multiples of SAR 500 (500–3000)
            $table->unsignedSmallInteger('monthly_contribution_amount')
                ->default(500)
                ->after('parent_id')
                ->comment('Multiples of 500; range 500–3000');
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropColumn(['parent_id', 'monthly_contribution_amount']);
        });
    }
};
