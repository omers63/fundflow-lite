<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('membership_applications', function (Blueprint $table) {
            $table->decimal('membership_fee_amount', 12, 2)->nullable()->after('application_form_path');
            $table->string('membership_fee_transfer_reference', 120)->nullable()->after('membership_fee_amount');
            $table->timestamp('membership_fee_posted_at')->nullable()->after('membership_fee_transfer_reference');
        });

        if (Setting::query()->where('key', 'membership.application_fee_amount')->doesntExist()) {
            Setting::create([
                'key' => 'membership.application_fee_amount',
                'value' => '0',
                'label' => 'Membership application fee (SAR), 0 = disabled',
                'group' => 'membership',
            ]);
        }
        if (Setting::query()->where('key', 'membership.application_fee_bank_instructions')->doesntExist()) {
            Setting::create([
                'key' => 'membership.application_fee_bank_instructions',
                'value' => "Transfer the fee to the fund bank account below. Include your full name in the transfer reference.\n\nBank: (configure in System settings)\nIBAN:\nAccount:",
                'label' => 'Public apply: bank transfer instructions for membership fee',
                'group' => 'membership',
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('membership_applications', function (Blueprint $table) {
            $table->dropColumn([
                'membership_fee_amount',
                'membership_fee_transfer_reference',
                'membership_fee_posted_at',
            ]);
        });

        Setting::query()->whereIn('key', [
            'membership.application_fee_amount',
            'membership.application_fee_bank_instructions',
        ])->delete();
    }
};
