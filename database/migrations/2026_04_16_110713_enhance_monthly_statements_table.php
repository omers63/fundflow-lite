<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('monthly_statements', function (Blueprint $table) {
            $table->json('details')->nullable()->after('generated_at');
            $table->timestamp('notified_at')->nullable()->after('details');
        });

        // Statement configuration settings
        $settings = [
            ['key' => 'statement.brand_name',          'value' => 'FundFlow',          'label' => 'Organization Name on Statement',          'group' => 'statement'],
            ['key' => 'statement.accent_color',         'value' => '#059669',           'label' => 'Statement Header Color (hex)',             'group' => 'statement'],
            ['key' => 'statement.tagline',              'value' => 'Member Fund Management', 'label' => 'Tagline / Sub-brand',                'group' => 'statement'],
            ['key' => 'statement.footer_disclaimer',    'value' => 'This is a computer-generated statement. Confidential — for the named member only.', 'label' => 'Footer Disclaimer Text', 'group' => 'statement'],
            ['key' => 'statement.signature_line',       'value' => 'FundFlow Administration', 'label' => 'Authorized Signature Line',         'group' => 'statement'],
            ['key' => 'statement.auto_email',           'value' => '1',                 'label' => 'Auto-Email on Generation',                'group' => 'statement'],
            ['key' => 'statement.include_transactions', 'value' => '1',                 'label' => 'Include Transaction Details in PDF',      'group' => 'statement'],
            ['key' => 'statement.include_loan_section', 'value' => '1',                 'label' => 'Include Loan Standing Section',           'group' => 'statement'],
            ['key' => 'statement.include_compliance',   'value' => '1',                 'label' => 'Include Compliance Snapshot',             'group' => 'statement'],
        ];

        foreach ($settings as $s) {
            DB::table('settings')->insertOrIgnore($s);
        }
    }

    public function down(): void
    {
        Schema::table('monthly_statements', function (Blueprint $table) {
            $table->dropColumn(['details', 'notified_at']);
        });

        DB::table('settings')->where('group', 'statement')->delete();
    }
};
