<?php

namespace App\Jobs;

use App\Events\MassMessageCompleteEvent;
use App\Events\MessageEvent;
use App\Models\CustomList;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class MassMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $user, $message, $include, $exclude;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($user, $message, $include, $exclude)
    {
        $this->user = $user;
        $this->message = $message;
        $this->include = $include;
        $this->exclude = $exclude;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // all included recipients
        $query = User::where('id', '<>', $this->user->id)->where(function ($query) {
            foreach ($this->include as $inc) {
                switch ($inc) {
                    case CustomList::DEFAULT_RECENT:
                        $query->orWhereIn('id', function ($q) {
                            $q->select('user_id')->from('messages')->where('created_at', '>', Carbon::now('UTC')->subDay(7))->where('mass', 0)->whereIn('id', function ($q) {
                                $q->select('message_id')->from('message_user')->where(function ($q) {
                                    $q->where('user_id', $this->user->id)->orWhere('party_id', $this->user->id);
                                });
                            });
                        });
                        break;
                    case CustomList::DEFAULT_FANS:
                        $query->orWhereHas('subscriptions', function ($q) {
                            $q->where('sub_id', $this->user->id);
                        });
                        break;
                    case CustomList::DEFAULT_FOLLOWING:
                        $query->orWhereHas('subscriptions', function ($q) {
                            $q->where('user_id', $this->user->id);
                        });
                        break;
                    default:
                        $query->orWhereIn('id', function ($q) use ($inc) {
                            $q->select('listee_id')->from('lists')->where('user_id', $this->user->id)->whereJsonContains('list_ids', $inc * 1);
                        });
                        break;
                }
            }
        });

        // all excluded recipients
        foreach ($this->exclude as $inc) {
            switch ($inc) {
                case CustomList::DEFAULT_RECENT:
                    $query->whereNotIn(
                        'id',
                        function ($q) {
                            $q->select('user_id')->from('messages')->where('created_at', '>', Carbon::now('UTC')->subDay(7))->where('mass', 0)->whereIn('id', function ($q) {
                                $q->select('message_id')->from('message_user')->where(function ($q) {
                                    $q->where('user_id', $this->user->id)->orWhere('party_id', $this->user->id);
                                });
                            });
                        }
                    );
                    break;
                case CustomList::DEFAULT_FANS:
                    $query->whereDoesntHave('subscriptions', function ($q) {
                        $q->where(
                            'sub_id',
                            $this->user->id
                        );
                    });
                    break;
                case CustomList::DEFAULT_FOLLOWING:
                    $query->whereDoesntHave('subscriptions', function ($q) {
                        $q->where('user_id', $this->user->id);
                    });
                    break;
                default:
                    $query->whereNotIn(
                        'id',
                        function ($q) use ($inc) {
                            $q->select('listee_id')->from('lists')->where('user_id', $this->user->id)->whereJsonContains('list_ids', $inc * 1);
                        }
                    );
                    break;
            }
        }

        $recipients = $query->get();
        foreach ($recipients as $rec) {
            $rec->mailbox()->attach($this->message, ['party_id' => $this->user->id]);
            MessageEvent::dispatch($rec, $this->message);
        }

        MassMessageCompleteEvent::dispatch($this->message);
    }
}
