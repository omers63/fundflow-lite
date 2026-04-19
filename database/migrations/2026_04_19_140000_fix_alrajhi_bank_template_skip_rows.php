<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // skip_rows was set when CSV lines were split incorrectly (str_getcsv on "\n").
        // With proper fgetcsv parsing, the column header row is index 10; first data row is 11.
        DB::table('bank_import_templates')
            ->where('name', 'AlRajhi-Arabic Monthly Statement')
            ->where('skip_rows', 16)
            ->update(['skip_rows' => 11]);
    }

    public function down(): void
    {
        DB::table('bank_import_templates')
            ->where('name', 'AlRajhi-Arabic Monthly Statement')
            ->where('skip_rows', 11)
            ->update(['skip_rows' => 16]);
    }
};
