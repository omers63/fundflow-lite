<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_tiers', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('tier_number')->unique();
            $table->string('label', 100)->nullable()->comment('e.g. "Emergency", "Tier 1"');
            $table->decimal('min_amount', 12, 2);
            $table->decimal('max_amount', 12, 2);
            $table->decimal('min_monthly_installment', 12, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::table('loan_tiers')->insert([
            ['tier_number' =>  1, 'label' => 'Tier 1',  'min_amount' =>   1000, 'max_amount' =>  30000, 'min_monthly_installment' => 1000],
            ['tier_number' =>  2, 'label' => 'Tier 2',  'min_amount' =>  31000, 'max_amount' =>  60000, 'min_monthly_installment' => 1500],
            ['tier_number' =>  3, 'label' => 'Tier 3',  'min_amount' =>  61000, 'max_amount' =>  90000, 'min_monthly_installment' => 2000],
            ['tier_number' =>  4, 'label' => 'Tier 4',  'min_amount' =>  91000, 'max_amount' => 120000, 'min_monthly_installment' => 2500],
            ['tier_number' =>  5, 'label' => 'Tier 5',  'min_amount' => 121000, 'max_amount' => 150000, 'min_monthly_installment' => 3000],
            ['tier_number' =>  6, 'label' => 'Tier 6',  'min_amount' => 151000, 'max_amount' => 180000, 'min_monthly_installment' => 3500],
            ['tier_number' =>  7, 'label' => 'Tier 7',  'min_amount' => 181000, 'max_amount' => 210000, 'min_monthly_installment' => 4000],
            ['tier_number' =>  8, 'label' => 'Tier 8',  'min_amount' => 211000, 'max_amount' => 240000, 'min_monthly_installment' => 4500],
            ['tier_number' =>  9, 'label' => 'Tier 9',  'min_amount' => 241000, 'max_amount' => 270000, 'min_monthly_installment' => 5000],
            ['tier_number' => 10, 'label' => 'Tier 10', 'min_amount' => 271000, 'max_amount' => 300000, 'min_monthly_installment' => 5500],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_tiers');
    }
};
