<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Message $message
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('ticket.' . $this->message->ticket_id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'message' => [
                'id' => $this->message->id,
                'ticket_id' => $this->message->ticket_id,
                'user_id' => $this->message->user_id,
                'content' => $this->message->content,
                'attachments' => $this->message->attachments,
                'is_system_message' => $this->message->is_system_message,
                'created_at' => $this->message->created_at->toISOString(),
                'user' => $this->message->user ? [
                    'id' => $this->message->user->id,
                    'full_name' => $this->message->user->full_name,
                    'profile_picture' => $this->message->user->profile_picture,
                    'role' => $this->message->user->role?->name,
                ] : null,
            ],
        ];
    }
}
