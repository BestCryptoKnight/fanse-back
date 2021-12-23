<?php

namespace Database\Seeders;

use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class PaymentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $demo = User::where('email', 'demo@uniprogy.com')->first();
        $posts = $demo->posts()->where('price', '>', 0)->get();
        $users = User::where('role', '<>', User::ROLE_ADMIN)->where('id', '<>', $demo->id)->get();
        $payments = [];
        for ($i = 0; $i < 40; $i++) {
            $user = $users[rand(0, count($users) - 1)];
            if ($i % 2 == 0) {
                $payments[] = Payment::create([
                    'type' => Payment::TYPE_SUBSCRIPTION_NEW,
                    'user_id' => $user->id,
                    'amount' => 2000,
                    'info' => ['sub_id' => $demo->id],
                    'to_id' => $demo->id,
                    'fee' => config('misc.payment.fee') * 100,
                    'status' => Payment::STATUS_COMPLETE,
                    'gateway' => 'paypal',
                ]);
            } else {
                $post = $posts[rand(0, count($posts) - 1)];
                $payments[] = Payment::create([
                    'type' => Payment::TYPE_POST,
                    'user_id' => $user->id,
                    'amount' => $post->price,
                    'info' => ['post_id' => $post->id],
                    'to_id' => $demo->id,
                    'fee' => config('misc.payment.fee') * 100,
                    'status' => Payment::STATUS_COMPLETE,
                    'gateway' => 'paypal',
                ]);
            }
        }
        foreach ($payments as $p) {
            $p->created_at = Carbon::now()->subMinutes(rand(10, 20000));
            $p->save();
        }
    }
}
