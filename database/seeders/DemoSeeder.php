<?php

namespace Database\Seeders;

use App\Models\Media;
use App\Models\Notification;
use App\Models\User;
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
            $price = $k % 2 == 0 ? 0 : 2000;
            $username = str_replace('.', '_', $faker->username);
            $user = User::create([
                'email' => $email,
                'name' => $faker->firstName('female') . ' ' . $faker->lastName,
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
                $post = $user->posts()->create([
                    'message' => $faker->realText(100),
                    'price' => $user->price ? null : rand(0, 1) * 1000,
                ]);
                foreach ($ms as $m) {
                    $m->info = ['screenshot' => 0];
                    $m->status = Media::STATUS_ACTIVE;
                    $m->save();
                }
                $post->media()->sync($ms->pluck('id'));
                $posts[] = $post;
                //break;
            }
            $users[] = $user;
            //break;
        }

        // shuffle posts
        foreach ($posts as $post) {
            $ago = rand(1, 100);
            $post->created_at = Carbon::now('UTC')->subHours($ago);
            $post->save();
            $l = rand(3, 12);
            for ($i = 0; $i < $l; $i++) {
                $user = $users[rand(0, count($users) - 1)];
                $post->likes()->toggle([$user->id]);

                $comment = $post->comments()->create([
                    'user_id' => $user->id,
                    'message' => $faker->realText(50)
                ]);
                $noti = $post->user->notifications()->create([
                    'type' => Notification::TYPE_COMMENT,
                    'info' => [
                        'comment_id' => $comment->id,
                        'user_id' => $comment->user_id,
                        'post_id' => $post->id
                    ]
                ]);
                $noti->created_at = Carbon::now('UTC')->subMinutes(rand(0, $ago * 24 * 60));
                $noti->save();
            }
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
}
