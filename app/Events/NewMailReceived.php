<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewMailReceived implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $emailLog;

    /**
     * Create a new event instance.
     */
    public function __construct($emailLog)
    {
        $this->emailLog = $emailLog;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->emailLog->user_id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->emailLog->id,
            'alias_id' => $this->emailLog->alias_id,
            'sender' => $this->emailLog->sender,
            'subject' => $this->emailLog->subject,
            'received_at' => $this->emailLog->created_at->toISOString(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'MailReceived';
    }
}
