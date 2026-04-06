<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fund_tiers', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('tier_number')->unique()->comment('0 = Emergency');
            $table->string('label', 100)->nullable();
            $table->foreignId('loan_tier_id')->nullable()->constrained('loan_tiers')->nullOnDelete();
            $table->decimal('percentage', 5, 2)->default(100.00)->comment('% of master fund balance available for this tier');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::table('fund_tiers')->insert([
            ['tier_number' => 0, 'label' => 'Emergency', 'loan_tier_id' => null, 'percentage' => 100.00],
            ['tier_number' => 1, 'label' => 'Tier 1', 'loan_tier_id' => 1, 'percentage' => 100.00],
            ['tier_number' => 2, 'label' => 'Tier 2', 'loan_tier_id' => 2, 'percentage' => 100.00],
            ['tier_number' => 3, 'label' => 'Tier 3', 'loan_tier_id' => 3, 'percentage' => 100.00],
            ['tier_number' => 4, 'label' => 'Tier 4', 'loan_tier_id' => 4, 'percentage' => 100.00],
            ['tier_number' => 5, 'label' => 'Tier 5', 'loan_tier_id' => 5, 'percentage' => 100.00],
            ['tier_number' => 6, 'label' => 'Tier 6', 'loan_tier_id' => 6, 'percentage' => 100.00],
            ['tier_number' => 7, 'label' => 'Tier 7', 'loan_tier_id' => 7, 'percentage' => 100.00],
            ['tier_number' => 8, 'label' => 'Tier 8', 'loan_tier_id' => 8, 'percentage' => 100.00],
            ['tier_number' => 9, 'label' => 'Tier 9', 'loan_tier_id' => 9, 'percentage' => 100.00],
            ['tier_number' => 10, 'label' => 'Tier 10', 'loan_tier_id' => 10, 'percentage' => 100.00],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('fund_tiers');
    }
};
