<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * CSV import treats national_id, date_of_birth, city, address, bank_account_number,
     * next_of_kin_name, and next_of_kin_phone as optional; nullable columns store omitted values.
     */
    public function up(): void
    {
        Schema::table('membership_applications', function (Blueprint $table) {
            $table->string('national_id', 20)->nullable()->change();
            $table->date('date_of_birth')->nullable()->change();
            $table->text('address')->nullable()->change();
            $table->string('city', 100)->nullable()->change();
            $table->string('next_of_kin_name', 150)->nullable()->change();
            $table->string('next_of_kin_phone', 30)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('membership_applications', function (Blueprint $table) {
            $table->string('national_id', 20)->nullable(false)->change();
            $table->date('date_of_birth')->nullable(false)->change();
            $table->text('address')->nullable(false)->change();
            $table->string('city', 100)->nullable(false)->change();
            $table->string('next_of_kin_name', 150)->nullable(false)->change();
            $table->string('next_of_kin_phone', 30)->nullable(false)->change();
        });
    }
};
