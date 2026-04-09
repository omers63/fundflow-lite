<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('contributions')) {
            return;
        }

        Schema::table('contributions', function (Blueprint $table) {
            $table->dropUnique(['member_id', 'month', 'year']);
        });

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            DB::statement(
                'CREATE UNIQUE INDEX contributions_member_period_active_unique ON contributions (member_id, month, year) WHERE deleted_at IS NULL'
            );

            return;
        }

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement(
                'ALTER TABLE contributions ADD COLUMN active_period_key VARCHAR(64)
                AS (CASE WHEN deleted_at IS NULL THEN CONCAT(member_id, "-", month, "-", year) ELSE NULL END) STORED'
            );
            DB::statement('CREATE UNIQUE INDEX contributions_active_period_key_unique ON contributions (active_period_key)');

            return;
        }

        DB::statement(
            'CREATE UNIQUE INDEX contributions_member_period_active_unique ON contributions (member_id, month, year) WHERE deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        if (!Schema::hasTable('contributions')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS contributions_member_period_active_unique');
        } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
            Schema::table('contributions', function (Blueprint $table) {
                $table->dropIndex('contributions_active_period_key_unique');
            });
            Schema::table('contributions', function (Blueprint $table) {
                $table->dropColumn('active_period_key');
            });
        } else {
            DB::statement('DROP INDEX IF EXISTS contributions_member_period_active_unique');
        }

        Schema::table('contributions', function (Blueprint $table) {
            $table->unique(['member_id', 'month', 'year']);
        });
    }
};
