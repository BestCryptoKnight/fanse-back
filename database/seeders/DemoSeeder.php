<?php

namespace Database\Seeders;

use App\Models\Media;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\PayoutMethod;
use App\Models\User;
use App\Models\Verification;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Faker;
use Storage;
use Hash;
use Image;
use FFMpeg;
use Illuminate\Support\Str;

class DemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $faker = Faker\Factory::create();

        // media
        $avatars = Storage::disk('local')->files('demo/avatar');
        $covers = Storage::disk('local')->files('demo/landscape');
        $media = Storage::disk('local')->directories('demo/media');
        shuffle($media);

        // users
        $users = [];
        $posts = [];
        $position = 0;
        foreach ($avatars as $k => $a) {
            $email = $k == 0 ? 'demo@uniprogy.com' : $faker->unique()->safeEmail();
            $password = $k == 0 ? 'password' : $faker->password;
            $price = $k == 0 || $k % 2 == 0 ? 0 : 2000;
            $username = str_replace('.', '_', $faker->username);
            $firstName = $faker->firstName('female');
            $lastName = $faker->lastName;
            $user = User::create([
                'email' => $email,
                'name' =>  $firstName . ' ' . $lastName,
                'username' => $faker->username,
                'password' => Hash::make($password),
                'channel_id' => $email,
                'channel_type' => User::CHANNEL_EMAIL,
                'avatar' => 1,
                'cover' => 1,
                'bio' => $faker->realText(200),
                'location' => $faker->city,
                'website' => 'https://' . $faker->domainName,
                'price' => $price,
                'email_verified_at' => Carbon::now(),
                'role' => User::ROLE_CREATOR,
            ]);
            $days = rand(1, 30);
            $user->created_at = Carbon::now('UTC')->subDays($days);

            $user->verification()->create([
                'country' => 'US',
                'info' => [
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'address' => '7744 Columbia St',
                    'city' => 'New York',
                    'state' => 'NY',
                    'zip' => '10128'
                ],
                'status' => Verification::STATUS_APPROVED
            ]);
            $user->payoutMethod()->create([
                'type' => PayoutMethod::TYPE_PAYPAL,
                'info' => ['paypal' => $email]
            ]);

            // upload avatar and cover
            $avatar = Storage::disk('local')->path($avatars[$k]);
            Storage::put('profile/avatar/' . $user->id . '.jpg', file_get_contents($avatar));

            $cover = Storage::disk('local')->path($covers[$k]);
            Storage::put('profile/cover/' . $user->id . '.jpg', file_get_contents($cover));

            if ($price) {
                $user->bundles()->create([
                    'months' => 3,
                    'discount' => 10,
                ]);
                $user->bundles()->create([
                    'months' => 6,
                    'discount' => 20,
                ]);
            }

            // posts
            for ($i = 0; $i < 5; $i++) {
                $num = rand(1, 3);
                $ms = collect([]);
                for ($j = 0; $j < $num; $j++) {
                    $ms->add($this->media($media[$position], $user));
                    $position++;
                    if ($position == count($media)) {
                        $position = 0;
                    }
                }
                $rand = rand(0, 3);
                $post = $user->posts()->create([
                    'message' => $this->postText(count($posts)),
                    'price' => $user->price ? null : ($user->id == 1 || $rand == 0 ? 1000 : null),
                ]);
                foreach ($ms as $m) {
                    $m->info = ['screenshot' => 0];
                    $m->status = Media::STATUS_ACTIVE;
                    $m->save();
                }
                $post->media()->sync($ms->pluck('id'));
                $post->created_at = Carbon::now('UTC')->subHours(rand(10, $days * 24));
                $post->save();
                $posts[] = $post;
                //break;
            }

            $user->save();
            $users[] = $user;
            //break;
        }

        // comments
        $comments = [];
        foreach ($posts as $post) {
            $l = rand(3, 12);
            for ($i = 0; $i < $l; $i++) {
                $user = $users[rand(0, count($users) - 1)];
                $post->likes()->toggle([$user->id]);

                $comment = $post->comments()->create([
                    'user_id' => $user->id,
                    'message' => $this->commentText(count($comments))
                ]);
                $when = $post->created_at->addMinutes(rand(1, 60));
                $comment->created_at = $when;
                $comment->save();
                $comments[] = $comment;

                if ($post->user->id == 1) {
                    $noti = $post->user->notifications()->create([
                        'type' => Notification::TYPE_COMMENT,
                        'info' => [
                            'comment_id' => $comment->id,
                            'user_id' => $comment->user_id,
                            'post_id' => $post->id
                        ]
                    ]);
                    $noti->created_at = $when;
                    $noti->save();
                }
            }
        }

        // messages
        $messages = [];
        $user = $users[0];
        foreach ($users as $current) {
            if ($current->id == $user->id) {
                continue;
            }
            for ($i = 0; $i < 5; $i++) {
                $message = ($i % 2 == 0 ? $user->messages() : $current->messages())->create([
                    'message' => $this->commentText(count($messages))
                ]);
                $message->created_at = Carbon::now('UTC')->subMinutes(rand(0, 240));
                $message->save();
                $current->mailbox()->attach($message, ['party_id' => $user->id, 'read' => true]);
                $user->mailbox()->attach($message, ['party_id' => $current->id, 'read' => true]);
                $messages[] = $message;
            }
        }

        // bookmarks
        foreach ($posts as $post) {
            $rand = rand(0, 3);
            if ($rand == 0) {
                $user->bookmarks()->toggle([$post->id]);
            }
        }

        // lists
        foreach ($users as $current) {
            if ($current->id == $user->id) {
                continue;
            }
            $user->listees()->attach($current->id, ['list_ids' => [0]]);
        }

        // subscriptions
        foreach ($users as $current) {
            if ($current->id == $user->id || !$current->price) {
                continue;
            }
            if (
                in_array($current->id, [2, 6])
            ) {
                $created = Carbon::now('UTC')->subDays(rand(1, 30));
                $token = 'PP-XX-' . rand(100000, 999999);
                $payment = $user->payments()->create([
                    'type' => Payment::TYPE_SUBSCRIPTION_NEW,
                    'token' => $token,
                    'to_id' => $current->id,
                    'info' => ['sub_id' => $current->id],
                    'amount' => $current->price,
                    'gateway' => "paypal",
                    'status' => Payment::STATUS_COMPLETE
                ]);
                $payment->created_at = $created;
                $payment->save();
                $expires = $created->copy()->addMonths(1);
                $subscription = $user->subscriptions()->create([
                    'sub_id' => $current->id,
                    'token' => $token,
                    'gateway' => "paypal",
                    'amount' => $current->price,
                    'expires' => $expires,
                    'info' => ['sub_id' => $current->id]
                ]);
            }
        }

        // payments for posts
        foreach ($users as $current) {
            if ($current->id == $user->id || !$current->price) {
                continue;
            }
            foreach ($posts as $post) {
                if ($post->user_id == $user->id) {
                    $token = 'PP-XX-' . rand(100000, 999999);
                    $created = Carbon::now('UTC')->subDays(rand(1, 30));
                    $payment = $current->payments()->create([
                        'type' => Payment::TYPE_POST,
                        'to_id' => $user->id,
                        'info' => ['post_id' => $post->id],
                        'amount' => $post->price,
                        'token' => $token,
                        'gateway' => "paypal",
                        'status' => Payment::STATUS_COMPLETE
                    ]);
                    $payment->created_at = $created;
                    $payment->save();
                }
            }
        }

        // payouts
        for ($i = 0; $i < 5; $i++) {
            $created = Carbon::now('UTC')->subDays(rand(1, 30));
            $payout = $user->payouts()->create([
                'amount' => rand(1, 3) * 1000,
                'info' => $user->payoutMethod,
                'status' => Payout::STATUS_COMPLETE
            ]);
            $payout->created_at = $created;
            $payout->updated_at = $created;
            $payout->save();
        }
    }

    private function media($path, $user)
    {
        $extension = file_exists(Storage::disk('local')->path($path) . '/media.mp4') ? 'mp4' : 'jpg';
        $type = $extension == 'mp4' ? Media::TYPE_VIDEO : Media::TYPE_IMAGE;
        $media = $user->media()->create([
            'type' => $type,
            'extension' => $extension
        ]);
        $files = Storage::disk('local')->files($path);
        foreach ($files as $file) {
            Storage::disk('local')->copy($file, 'public/media/' . $media->hash . '/' . basename($file));
        }
        return $media;
    }

    private function postText($i)
    {
        $options = [
            "Y'all really don't know how crazy I can get 🙈 on the plane",
            "Your little showgirl 🤩",
            "You couldn't have possibly thought Christmas was over yet… Did you? 😜 Keep an eye out for more gifts in your DM…❤️🦌",
            "happy monday! new week, new goals. what are you setting out to accomplish this week? I think mine is to focus on my studies! 🤓",
            "Only in your wildest dreams 🐆🤎",
            "Not your average girl next door 🏡🙇🏼‍♀️",
            "Tuesday's are better with me 😜 do u agree or do u agree?",
            "Happy Monday babe!! I'm up for a fun week 😏 r u???",
            "Am I your favorite girl? 💛",
            "My eyes are up here 😏",
            "Do y'all like it when I'm sweet... or sour 🍬😏?",
            "When life gives you lemons...🍋💜💛",
            "Felt so good to be back in the studio today 💓🎼",
            "These tan lines about to be crazyyy 😂",
            "Whose down? 🥵",
            "Happiest outside 🥰",
            "Do you know what's in the backpack? 🤪",
            "Finally finishing moving in... I miss u! Let's catch up 😘",
            "I need a study buddy 🙃",
            "It's the dramatic gaze for me 🤍",
            "National Mean Girls Day 💕💅🏼",
            "This has me feeling some type of way 🙊",
            "Bet you didn't know I had skills like this but what can I say? I'm a woman of many talents 🤪",
            "who's ready for this next set? 🥵🥵🥵 I can't wait to blow your minds 🤯 dropping tonight 👀👀👀👀👀",
            "happiest in pjs😘",
            "Girly Glam ✨",
            "welcome to my LALA land ❤️",
            "I swear July and August only lasted like 2 seconds lol... hello September 💙",
            "See the beauty in every day... la vie en rose 🌹",
            "who wants to challenge me to a game of cod?😈",
            "can I be your flower girl?😋",
            "What's for breakfast? 🍳",
            "know what's on the menu? ME-N-U 😜",
            "You miss all the shots you don't take. Don't miss this one... 😉",
            "my green eyes aren't the only thing saying hi😉🍒",
        ];
        return isset($options[$i]) ? $options[$i] : $options[rand(0, count($options) - 1)];
    }

    private function commentText($i)
    {
        $options = [
            "Yes!! 😍☀️",
            "👍",
            "😻🔥",
            "😍😍",
            "Lovely and Beautiful 😍🌸",
            "it wasn't that naughty😂",
            "Oh damn baby",
            "😍😍😍beautiful ♥️♥️♥️♥️♥️",
            "Going to find out who's been naughty or nice 🤶🎄🎁🍑🔥💖😋",
            "Love you babe❤️",
            "I love the new pink hair awesome on u I love your energy n vibe mwah",
            "👏🏼👏🏼",
            "What's that song called 😅 love the dance btw👌",
            "Te amo preciosa",
            "Omg you look delicious!",
            "🔥so hot",
            "🤩🤩🤩🤩",
        ];
        if ($i >= count($options)) {
            $i = $i % count($options);
        }
        return $options[$i];
    }
}
