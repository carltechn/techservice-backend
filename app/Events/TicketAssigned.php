<?php

namespace App\Events;

use App\Models\Ticket;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TicketAssigned implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Ticket $ticket;
    public int $assigneeId;

    public function __construct(Ticket $ticket, int $assigneeId)
    {
        $this->ticket = $ticket;
        $this->assigneeId = $assigneeId;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->assigneeId),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'ticket_id' => $this->ticket->id,
            'ticket_number' => $this->ticket->ticket_number,
            'title' => $this->ticket->title,
            'category' => $this->ticket->category,
            'priority' => $this->ticket->priority,
            'status' => $this->ticket->status,
            'user_name' => $this->ticket->user->full_name,
        ];
    }
}

