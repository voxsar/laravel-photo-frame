<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'miyuru@artslabcreatives.com'],
            [
                'name' => 'Miyuru',
                'password' => Hash::make('password'),
            ]
        );

        $this->command->info('Admin user ready: miyuru@artslabcreatives.com');
    }
}
