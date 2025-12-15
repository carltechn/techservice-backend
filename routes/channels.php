<?php

use App\Models\Ticket;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
*/

// User-specific private channel for notifications
Broadcast::channel('user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Private ticket channel - allows ticket owner and assigned staff
Broadcast::channel('ticket.{ticketId}', function ($user, $ticketId) {
    $ticket = Ticket::find($ticketId);

    if (!$ticket) {
        return false;
    }

    // Owner can access
    if ($ticket->user_id === $user->id) {
        return true;
    }

    // Assigned staff can access
    if ($ticket->assignee_id === $user->id) {
        return true;
    }

    // Admin or incharge can access any ticket
    if ($user->isStaff()) {
        return true;
    }

    return false;
});

// Presence channel for ticket viewing - shows who's online
Broadcast::channel('presence-ticket.{ticketId}', function ($user, $ticketId) {
    $ticket = Ticket::find($ticketId);

    if (!$ticket) {
        return false;
    }

    // Owner can access
    if ($ticket->user_id === $user->id) {
        return [
            'id' => $user->id,
            'name' => $user->full_name,
            'role' => $user->role->display_name ?? 'User',
        ];
    }

    // Assigned staff can access
    if ($ticket->assignee_id === $user->id) {
        return [
            'id' => $user->id,
            'name' => $user->full_name,
            'role' => $user->role->display_name ?? 'Staff',
        ];
    }

    // Admin or incharge can access any ticket
    if ($user->isStaff()) {
        return [
            'id' => $user->id,
            'name' => $user->full_name,
            'role' => $user->role->display_name ?? 'Staff',
        ];
    }

    return false;
});

// All tickets channel (for staff to receive updates)
Broadcast::channel('tickets', function ($user) {
    return $user->isStaff();
});
