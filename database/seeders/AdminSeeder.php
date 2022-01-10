<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Hash;
use Illuminate\Support\Carbon;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $email = 'admin@localhost';
        User::create([
            'email' => $email,
            'name' => 'Admin',
            'username' => 'admin',
            'password' => Hash::make("password"),
            'channel_id' => $email,
            'channel_type' => User::CHANNEL_EMAIL,
            'email_verified_at' => Carbon::now(),
            'role' => User::ROLE_ADMIN
        ]);
    }
}
