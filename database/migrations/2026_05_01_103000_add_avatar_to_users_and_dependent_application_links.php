<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('avatar_path')->nullable()->after('phone');
        });

        Schema::table('membership_applications', function (Blueprint $table): void {
            $table->foreignId('parent_member_id')
                ->nullable()
                ->after('user_id')
                ->constrained('members')
                ->nullOnDelete();
            $table->foreignId('submitted_by_user_id')
                ->nullable()
                ->after('parent_member_id')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('membership_applications', function (Blueprint $table): void {
            $table->dropForeign(['parent_member_id']);
            $table->dropForeign(['submitted_by_user_id']);
            $table->dropColumn(['parent_member_id', 'submitted_by_user_id']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('avatar_path');
        });
    }
};
