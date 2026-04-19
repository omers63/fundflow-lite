<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_import_templates', function (Blueprint $table) {
            if (! Schema::hasColumn('bank_import_templates', 'optional_columns')) {
                $table->json('optional_columns')->nullable()->after('balance_column');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bank_import_templates', function (Blueprint $table) {
            if (Schema::hasColumn('bank_import_templates', 'optional_columns')) {
                $table->dropColumn('optional_columns');
            }
        });
    }
};
