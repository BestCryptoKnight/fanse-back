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

class MessageReadEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    private $user;
    private $recipient;

    public function __construct(User $recipient, User $user)
    {
        $this->user = $user;
        $this->recipient = $recipient;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('users.' . $this->recipient->id);
    }

    public function broadcastAs()
    {
        return 'message.read';
    }

    public function broadcastWith()
    {
        $this->recipient->loadCount(['notificationsNew', 'mailboxNew']);
        return [
            'id' => $this->user->id,
            'updates' => [
                'notifications' => $this->recipient->notifications_new_count,
                'messages' => $this->recipient->mailbox_new_count
            ]
        ];
    }
}
