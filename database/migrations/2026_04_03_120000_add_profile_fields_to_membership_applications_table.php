<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('membership_applications', function (Blueprint $table) {
            $table->string('application_type', 20)->default('new')->after('user_id');
            $table->string('gender', 20)->nullable()->after('application_type');
            $table->string('marital_status', 30)->nullable()->after('gender');
            $table->string('home_phone', 30)->nullable()->after('city');
            $table->string('work_phone', 30)->nullable()->after('home_phone');
            $table->string('mobile_phone', 30)->nullable()->after('work_phone');
            $table->string('work_place', 255)->nullable()->after('employer');
            $table->string('residency_place', 255)->nullable()->after('work_place');
            $table->string('bank_account_number', 50)->nullable()->after('monthly_income');
            $table->string('iban', 34)->nullable()->after('bank_account_number');
            $table->date('membership_date')->nullable()->after('iban');
        });
    }

    public function down(): void
    {
        Schema::table('membership_applications', function (Blueprint $table) {
            $table->dropColumn([
                'application_type',
                'gender',
                'marital_status',
                'home_phone',
                'work_phone',
                'mobile_phone',
                'work_place',
                'residency_place',
                'bank_account_number',
                'iban',
                'membership_date',
            ]);
        });
    }
};
