<?php

namespace App\Events;

use App\Models\Message;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    private $message;
    private $user;

    public function __construct(User $user, Message $message)
    {
        $this->user = $user;
        $this->message = $message;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('users.' . $this->user->id);
    }

    public function broadcastAs()
    {
        return 'message';
    }

    public function broadcastWith()
    {
        $this->user->loadCount(['notificationsNew', 'mailboxNew']);
        $this->message->load('user');

        return [
            'message' => $this->message,
            'updates' => [
                'notifications' => $this->user->notifications_new_count,
                'messages' => $this->user->mailbox_new_count
            ]
        ];
    }
}
