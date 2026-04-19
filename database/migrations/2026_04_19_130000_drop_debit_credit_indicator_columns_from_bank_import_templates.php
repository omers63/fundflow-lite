<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_import_templates', function (Blueprint $table) {
            $table->dropColumn(['type_column', 'credit_indicator', 'debit_indicator']);
        });
    }

    public function down(): void
    {
        Schema::table('bank_import_templates', function (Blueprint $table) {
            $table->string('type_column')->nullable();
            $table->string('credit_indicator')->nullable()->default('CR');
            $table->string('debit_indicator')->nullable()->default('DR');
        });
    }
};
