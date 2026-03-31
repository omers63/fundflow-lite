<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sms_import_templates', function (Blueprint $table) {
            // Regex with named group "member" to extract the member identifier from the SMS text
            $table->string('member_match_pattern')->nullable()->after('reference_pattern')
                ->comment('Regex with named group "member". e.g. /Account:\s*(?P<member>\d+)/');
            // Which Member / User field to match the extracted value against
            $table->string('member_match_field')->nullable()->default('member_number')->after('member_match_pattern')
                ->comment('user_name | member_number');
        });
    }

    public function down(): void
    {
        Schema::table('sms_import_templates', function (Blueprint $table) {
            $table->dropColumn(['member_match_pattern', 'member_match_field']);
        });
    }
};
