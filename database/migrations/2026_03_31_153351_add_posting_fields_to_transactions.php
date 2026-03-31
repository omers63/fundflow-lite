<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->foreignId('member_id')->nullable()->after('bank_id')->constrained('members')->nullOnDelete();
            $table->timestamp('posted_at')->nullable()->after('raw_data');
            $table->foreignId('posted_by')->nullable()->after('posted_at')->constrained('users')->nullOnDelete();
        });

        Schema::table('sms_transactions', function (Blueprint $table) {
            $table->foreignId('member_id')->nullable()->after('bank_id')->constrained('members')->nullOnDelete();
            $table->timestamp('posted_at')->nullable()->after('raw_data');
            $table->foreignId('posted_by')->nullable()->after('posted_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->dropForeign(['member_id']);
            $table->dropForeign(['posted_by']);
            $table->dropColumn(['member_id', 'posted_at', 'posted_by']);
        });

        Schema::table('sms_transactions', function (Blueprint $table) {
            $table->dropForeign(['member_id']);
            $table->dropForeign(['posted_by']);
            $table->dropColumn(['member_id', 'posted_at', 'posted_by']);
        });
    }
};
