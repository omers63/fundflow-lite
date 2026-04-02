<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('label')->nullable();
            $table->string('group', 50)->default('general');
            $table->timestamps();
        });

        DB::table('settings')->insert([
            ['key' => 'loan.settlement_threshold_pct',  'value' => '0.16',  'label' => 'Loan Settlement Threshold (%)',          'group' => 'loan'],
            ['key' => 'loan.min_fund_balance',           'value' => '6000',  'label' => 'Min Fund Balance for Loan Eligibility',   'group' => 'loan'],
            ['key' => 'loan.eligibility_months',         'value' => '12',    'label' => 'Months of Membership Before Eligible',    'group' => 'loan'],
            ['key' => 'loan.max_borrow_multiplier',      'value' => '2',     'label' => 'Max Loan = N × Fund Balance',             'group' => 'loan'],
            ['key' => 'loan.default_grace_cycles',       'value' => '2',     'label' => 'Default Warnings Before Guarantor Debit', 'group' => 'loan'],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
