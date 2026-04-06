<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Soft deletes for configuration, tiers, import metadata, and audit-style rows.
     *
     * Not included: users, members, loans, ledger (accounts, account_transactions),
     * contributions, bank/sms transactions, database_backups — those flows rely on
     * physical removal or paired file cleanup.
     */
    public function up(): void
    {
        $tables = [
            'settings',
            'fund_tiers',
            'loan_tiers',
            'banks',
            'bank_import_templates',
            'bank_import_sessions',
            'sms_import_templates',
            'sms_import_sessions',
            'notification_logs',
            'monthly_statements',
        ];

        foreach ($tables as $table) {
            if (!Schema::hasTable($table) || Schema::hasColumn($table, 'deleted_at')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->softDeletes();
            });
        }
    }

    public function down(): void
    {
        $tables = [
            'monthly_statements',
            'notification_logs',
            'sms_import_sessions',
            'sms_import_templates',
            'bank_import_sessions',
            'bank_import_templates',
            'banks',
            'loan_tiers',
            'fund_tiers',
            'settings',
        ];

        foreach ($tables as $table) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'deleted_at')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropSoftDeletes();
            });
        }
    }
};
