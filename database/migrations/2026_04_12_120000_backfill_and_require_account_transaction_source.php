<?php

use App\Models\AccountTransaction;
use App\Models\Contribution;
use App\Models\Member;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        AccountTransaction::query()
            ->withTrashed()
            ->whereNull('source_type')
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    $entry = AccountTransaction::query()->withTrashed()->findOrFail($row->id);
                    $this->backfillOne($entry);
                }
            });

        $stillNull = AccountTransaction::query()->withTrashed()->whereNull('source_type')->count();
        if ($stillNull > 0) {
            throw new RuntimeException(
                "Cannot require ledger source: {$stillNull} account_transactions row(s) still have a null source after backfill."
            );
        }

        Schema::table('account_transactions', function (Blueprint $table) {
            $table->string('source_type')->nullable(false)->change();
            $table->unsignedBigInteger('source_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('account_transactions', function (Blueprint $table) {
            $table->string('source_type')->nullable()->change();
            $table->unsignedBigInteger('source_id')->nullable()->change();
        });
    }

    private function backfillOne(AccountTransaction $entry): void
    {
        $desc = (string) ($entry->description ?? '');

        if (str_contains($desc, 'member import') && $entry->member_id) {
            $member = Member::query()->find($entry->member_id);
            if ($member !== null) {
                $entry->forceFill([
                    'source_type' => $member->getMorphClass(),
                    'source_id' => $member->getKey(),
                ])->save();

                return;
            }
        }

        if (str_starts_with($desc, 'Contribution deduction')) {
            if (preg_match('/Contribution deduction\s*[–—-]\s*([A-Za-z]+)\s+(\d{4})/u', $desc, $m) && $entry->member_id) {
                $ts = strtotime($m[1] . ' 1, ' . $m[2]);
                if ($ts !== false) {
                    $month = (int) date('n', $ts);
                    $year = (int) $m[2];
                    $contribution = Contribution::query()
                        ->withTrashed()
                        ->where('member_id', $entry->member_id)
                        ->where('month', $month)
                        ->where('year', $year)
                        ->orderByDesc('id')
                        ->first();
                    if ($contribution !== null) {
                        $entry->forceFill([
                            'source_type' => $contribution->getMorphClass(),
                            'source_id' => $contribution->getKey(),
                        ])->save();

                        return;
                    }
                }
            }

            $member = Member::query()->find($entry->member_id);
            if ($member !== null) {
                $entry->forceFill([
                    'source_type' => $member->getMorphClass(),
                    'source_id' => $member->getKey(),
                ])->save();

                return;
            }
        }

        if (
            (str_contains($desc, 'Transfer to') || str_contains($desc, 'Transfer from'))
            && str_contains($desc, 'cash account')
            && $entry->member_id
        ) {
            $member = Member::query()->find($entry->member_id);
            if ($member !== null) {
                $entry->forceFill([
                    'source_type' => $member->getMorphClass(),
                    'source_id' => $member->getKey(),
                ])->save();

                return;
            }
        }

        $poster = User::query()->find((int) ($entry->posted_by ?? 0));
        if ($poster === null) {
            $poster = User::query()->orderBy('id')->firstOrFail();
        }

        $entry->forceFill([
            'source_type' => $poster->getMorphClass(),
            'source_id' => $poster->getKey(),
        ])->save();
    }
};
