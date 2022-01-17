<?php

namespace App\Jobs;

use App\Events\MessageEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class MessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $message;
    protected $from;
    protected $to;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($message, $from, $to)
    {
        $this->message = $message;
        $this->from = $from;
        $this->to = $to;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // mailbox
        $this->from->mailbox()->attach($this->message, ['party_id' => $this->to->id]);
        $this->to->mailbox()->attach($this->message, ['party_id' => $this->from->id]);

        // notify
        MessageEvent::dispatch($this->to, $this->message);
    }
}
