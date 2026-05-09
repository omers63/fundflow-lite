<?php

use App\Models\Account;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        Account::query()->firstOrCreate(
            ['slug' => 'master_investment_fund'],
            [
                'name' => 'Investment Account',
                'type' => Account::TYPE_MASTER_INVESTMENT_FUND,
                'balance' => 0,
                'is_active' => true,
            ]
        );

        Account::query()->firstOrCreate(
            ['slug' => 'master_expense_account'],
            [
                'name' => 'Expense Account',
                'type' => Account::TYPE_MASTER_EXPENSE_ACCOUNT,
                'balance' => 0,
                'is_active' => true,
            ]
        );
    }

    public function down(): void
    {
        Account::query()->whereIn('slug', [
            'master_investment_fund',
            'master_expense_account',
        ])->delete();
    }
};

