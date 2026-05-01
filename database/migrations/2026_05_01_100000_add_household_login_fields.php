<?php

use App\Models\Member;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique('users_email_unique');
            $table->index('email');
        });

        Schema::table('members', function (Blueprint $table): void {
            $table->string('household_email')->nullable()->after('parent_id')->index();
            $table->boolean('is_separated')->default(false)->after('household_email');
            $table->boolean('direct_login_enabled')->default(false)->after('is_separated');
            $table->string('portal_pin')->nullable()->after('direct_login_enabled');
        });

        $members = Member::query()->with(['user', 'parent.user'])->orderBy('id')->get();

        foreach ($members as $member) {
            $memberEmail = $member->user?->email;
            $parentEmail = $member->parent?->user?->email;

            if ($member->parent_id === null) {
                DB::table('members')->where('id', $member->id)->update([
                    'household_email' => $memberEmail,
                    'is_separated' => false,
                    'direct_login_enabled' => false,
                ]);

                continue;
            }

            $isSeparated = filled($parentEmail) && $memberEmail !== $parentEmail;

            DB::table('members')->where('id', $member->id)->update([
                'household_email' => $parentEmail ?? $memberEmail,
                'is_separated' => $isSeparated,
                'direct_login_enabled' => $isSeparated,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table): void {
            $table->dropColumn([
                'household_email',
                'is_separated',
                'direct_login_enabled',
                'portal_pin',
            ]);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['email']);
            $table->unique('email');
        });
    }
};
