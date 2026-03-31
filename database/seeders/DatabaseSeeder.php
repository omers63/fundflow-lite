<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@fundflow.sa'],
            [
                'name' => 'FundFlow Admin',
                'email' => 'admin@fundflow.sa',
                'phone' => '+966500000000',
                'role' => 'admin',
                'status' => 'approved',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
    }
}
