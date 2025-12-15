<?php

namespace App\Events;

use App\Models\Ticket;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TicketUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Ticket $ticket
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('ticket.' . $this->ticket->id),
            new PrivateChannel('tickets'), // For list updates
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'ticket' => [
                'id' => $this->ticket->id,
                'ticket_number' => $this->ticket->ticket_number,
                'title' => $this->ticket->title,
                'status' => $this->ticket->status,
                'priority' => $this->ticket->priority,
                'category' => $this->ticket->category,
                'assigned_to' => $this->ticket->assigned_to,
                'assignee' => $this->ticket->assignee ? [
                    'id' => $this->ticket->assignee->id,
                    'full_name' => $this->ticket->assignee->full_name,
                ] : null,
                'updated_at' => $this->ticket->updated_at,
            ],
        ];
    }
}

