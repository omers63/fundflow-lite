<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Secondary indexes aligned with Filament widgets, scopes, and relation managers.
     * Safe to run once; down() drops by explicit names.
     */
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->index(['status', 'deleted_at'], 'members_status_deleted_at_index');
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->index(['member_id', 'status'], 'loans_member_id_status_index');
        });

        Schema::table('loan_installments', function (Blueprint $table) {
            $table->index(['loan_id', 'status'], 'loan_installments_loan_id_status_index');
            $table->index(['status', 'due_date'], 'loan_installments_status_due_date_index');
        });

        Schema::table('contributions', function (Blueprint $table) {
            $table->index('paid_at', 'contributions_paid_at_index');
        });

        Schema::table('account_transactions', function (Blueprint $table) {
            $table->index(['member_id', 'transacted_at'], 'account_transactions_member_transacted_index');
            $table->index(['account_id', 'deleted_at', 'transacted_at'], 'account_transactions_account_deleted_transacted_index');
        });

        foreach (['bank_transactions', 'sms_transactions'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $prefix = $tableName;
                $table->index('posted_at', "{$prefix}_posted_at_index");
                $table->index('created_at', "{$prefix}_created_at_index");
                $table->index(['import_session_id', 'posted_at'], "{$prefix}_import_session_posted_index");
                $table->index(['transaction_type', 'posted_at'], "{$prefix}_type_posted_index");
                $table->index(['is_duplicate', 'posted_at'], "{$prefix}_duplicate_posted_index");
                $table->index(['posted_at', 'amount'], "{$prefix}_posted_amount_index");
            });
        }

        Schema::table('bank_import_sessions', function (Blueprint $table) {
            $table->index(['bank_id', 'created_at'], 'bank_import_sessions_bank_created_index');
            $table->index(['status', 'created_at'], 'bank_import_sessions_status_created_index');
        });

        Schema::table('sms_import_sessions', function (Blueprint $table) {
            $table->index(['bank_id', 'created_at'], 'sms_import_sessions_bank_created_index');
            $table->index(['status', 'created_at'], 'sms_import_sessions_status_created_index');
        });
    }

    public function down(): void
    {
        Schema::table('sms_import_sessions', function (Blueprint $table) {
            $table->dropIndex('sms_import_sessions_status_created_index');
            $table->dropIndex('sms_import_sessions_bank_created_index');
        });

        Schema::table('bank_import_sessions', function (Blueprint $table) {
            $table->dropIndex('bank_import_sessions_status_created_index');
            $table->dropIndex('bank_import_sessions_bank_created_index');
        });

        foreach (['sms_transactions', 'bank_transactions'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $prefix = $tableName;
                $table->dropIndex("{$prefix}_posted_amount_index");
                $table->dropIndex("{$prefix}_duplicate_posted_index");
                $table->dropIndex("{$prefix}_type_posted_index");
                $table->dropIndex("{$prefix}_import_session_posted_index");
                $table->dropIndex("{$prefix}_created_at_index");
                $table->dropIndex("{$prefix}_posted_at_index");
            });
        }

        Schema::table('account_transactions', function (Blueprint $table) {
            $table->dropIndex('account_transactions_account_deleted_transacted_index');
            $table->dropIndex('account_transactions_member_transacted_index');
        });

        Schema::table('contributions', function (Blueprint $table) {
            $table->dropIndex('contributions_paid_at_index');
        });

        Schema::table('loan_installments', function (Blueprint $table) {
            $table->dropIndex('loan_installments_status_due_date_index');
            $table->dropIndex('loan_installments_loan_id_status_index');
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->dropIndex('loans_member_id_status_index');
        });

        Schema::table('members', function (Blueprint $table) {
            $table->dropIndex('members_status_deleted_at_index');
        });
    }
};
