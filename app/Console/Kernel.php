<?php

namespace App\Console;

use App\Models\Media;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Storage;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];

    protected function scheduleTimezone()
    {
        return config('misc.tz');
    }

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        set_time_limit(0);

        $now = Carbon::now('UTC')->setSeconds(0)->setMilliseconds(0);

        // delete tmp medias
        $schedule->call(
            function () use ($now) {
                $media = Media::where('status', Media::STATUS_TMP)
                    ->where('created_at', '<', $now->copy()->subHour())->get();
                foreach ($media as $med) {
                    Storage::delete($med->path);
                    $med->forceDelete();
                }
            }
        )->hourly();

        // delete expired subscriptions
        $schedule->call(
            function () use ($now) {
                $subs = Subscription::where('expires', '>=', $now)->get();
                foreach ($subs as $s) {
                    $s->delete();
                }
            }
        )->daily();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
