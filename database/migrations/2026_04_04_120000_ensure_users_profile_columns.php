<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Older local SQLite (or pre-change DBs) may have a users table without phone/role/status.
     * The DatabaseSeeder expects these columns; add them only when missing.
     */
    public function up(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'phone')) {
                $table->string('phone', 30)->nullable();
            }
            if (!Schema::hasColumn('users', 'role')) {
                $table->string('role', 20)->default('member');
            }
            if (!Schema::hasColumn('users', 'status')) {
                $table->string('status', 20)->default('pending');
            }
        });
    }

    public function down(): void
    {
        // Intentionally empty: cannot know if these columns came from this fix or from create_users_table.
    }
};
