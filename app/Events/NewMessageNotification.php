<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewMessageNotification implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Message $message;
    public int $recipientId;

    public function __construct(Message $message, int $recipientId)
    {
        $this->message = $message;
        $this->recipientId = $recipientId;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->recipientId),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'message_id' => $this->message->id,
            'ticket_id' => $this->message->ticket_id,
            'ticket_number' => $this->message->ticket->ticket_number,
            'sender_id' => $this->message->user_id,
            'sender_name' => $this->message->user->full_name,
            'preview' => substr($this->message->content, 0, 100),
        ];
    }
}

