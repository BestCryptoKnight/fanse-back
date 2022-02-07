<?php

namespace Database\Seeders;

use App\Models\PayoutMethod;
use App\Models\User;
use App\Models\Verification;
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
        $user = User::create([
            'email' => $email,
            'name' => 'Admin',
            'username' => 'admin',
            'password' => Hash::make("password"),
            'channel_id' => $email,
            'channel_type' => User::CHANNEL_EMAIL,
            'email_verified_at' => Carbon::now(),
            'role' => User::ROLE_ADMIN
        ]);
        $user->verification()->create([
            'country' => 'US',
            'info' => [
                'first_name' => 'Admin',
                'last_name' => 'Account',
                'address' => '7744 Columbia St',
                'city' => 'New York',
                'state' => 'NY',
                'zip' => '10128'
            ],
            'status' => Verification::STATUS_APPROVED
        ]);
        $user->payoutMethod()->create([
            'gateway' => 'paypal',
            'info' => ['paypal' => $email]
        ]);
    }
}
