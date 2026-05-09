<?php

use App\Models\Account;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        Account::query()->firstOrCreate(
            ['slug' => 'master_fees'],
            [
                'name' => 'Fees Account',
                'type' => Account::TYPE_MASTER_FEES,
                'balance' => 0,
                'is_active' => true,
            ]
        );
    }

    public function down(): void
    {
        Account::query()->where('slug', 'master_fees')->delete();
    }
};

