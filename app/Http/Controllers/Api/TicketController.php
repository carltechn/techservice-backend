<?php

namespace App\Http\Controllers\Api;

use App\Events\TicketAssigned;
use App\Events\TicketUpdated;
use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    /**
     * Get all tickets based on user role.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Ticket::with(['user', 'assignee', 'latestMessage']);

        // Filter based on role
        if ($user->isUser()) {
            // Regular users can only see their own tickets
            $query->where('user_id', $user->id);
        } elseif ($user->isIncharge()) {
            // Incharge only sees tickets explicitly assigned to them
            $query->where('assigned_to', $user->id);
        }
        // Admins can see all tickets

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        if ($request->has('priority')) {
            $query->where('priority', $request->priority);
        }
        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        $tickets = $query->orderBy('created_at', 'desc')->paginate(15);

        return response()->json($tickets);
    }

    /**
     * Create a new ticket.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'category' => 'required|in:software,hardware,network,account,other',
            'priority' => 'sometimes|in:low,medium,high,critical',
        ]);

        $ticket = Ticket::create([
            'user_id' => $request->user()->id,
            'title' => $validated['title'],
            'description' => $validated['description'],
            'category' => $validated['category'],
            'priority' => $validated['priority'] ?? 'medium',
            'status' => Ticket::STATUS_OPEN,
        ]);

        // Create initial system message
        Message::createSystemMessage($ticket, 'Ticket created');

        return response()->json([
            'message' => 'Ticket created successfully',
            'ticket' => $ticket->load(['user', 'assignee']),
        ], 201);
    }

    /**
     * Get a specific ticket.
     */
    public function show(Request $request, Ticket $ticket): JsonResponse
    {
        $user = $request->user();

        // Check access
        if ($user->isUser() && $ticket->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($user->isIncharge() && $ticket->assigned_to !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'ticket' => $ticket->load(['user', 'assignee', 'messages.user']),
        ]);
    }

    /**
     * Update a ticket.
     */
    public function update(Request $request, Ticket $ticket): JsonResponse
    {
        $user = $request->user();

        // Only staff can update tickets beyond basic info
        if (!$user->isStaff() && $ticket->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $rules = [
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'category' => 'sometimes|in:software,hardware,network,account,other',
        ];

        // Staff can update more fields
        if ($user->isStaff()) {
            $rules['priority'] = 'sometimes|in:low,medium,high,critical';
            $rules['status'] = 'sometimes|in:open,in_progress,pending,resolved,closed';
            $rules['assigned_to'] = 'sometimes|nullable|exists:users,id';
        }

        $validated = $request->validate($rules);

        // Track status changes
        $oldStatus = $ticket->status;

        $ticket->update($validated);

        // Create system message for status changes
        if (isset($validated['status']) && $oldStatus !== $validated['status']) {
            Message::createSystemMessage($ticket, "Status changed from {$oldStatus} to {$validated['status']}");

            if ($validated['status'] === Ticket::STATUS_RESOLVED) {
                $ticket->update(['resolved_at' => now()]);
            } elseif ($validated['status'] === Ticket::STATUS_CLOSED) {
                $ticket->update(['closed_at' => now()]);
            }
        }

        // Create system message for assignment changes
        if (isset($validated['assigned_to'])) {
            $assignee = User::find($validated['assigned_to']);
            $assigneeName = $assignee ? $assignee->full_name : 'Unassigned';
            Message::createSystemMessage($ticket, "Ticket assigned to {$assigneeName}");

            // Notify the new assignee
            if ($assignee && $assignee->id !== $user->id) {
                broadcast(new TicketAssigned($ticket, $assignee->id));
            }
        }

        broadcast(new TicketUpdated($ticket))->toOthers();

        return response()->json([
            'message' => 'Ticket updated successfully',
            'ticket' => $ticket->fresh()->load(['user', 'assignee']),
        ]);
    }

    /**
     * Assign ticket to a staff member.
     */
    public function assign(Request $request, Ticket $ticket): JsonResponse
    {
        $user = $request->user();

        if (!$user->isStaff()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'assigned_to' => 'required|exists:users,id',
        ]);

        // Verify assignee is staff
        $assignee = User::find($validated['assigned_to']);
        if (!$assignee->isStaff()) {
            return response()->json(['message' => 'Can only assign to staff members'], 422);
        }

        $ticket->update([
            'assigned_to' => $validated['assigned_to'],
            'status' => Ticket::STATUS_IN_PROGRESS,
        ]);

        Message::createSystemMessage($ticket, "Ticket assigned to {$assignee->full_name}");

        broadcast(new TicketUpdated($ticket))->toOthers();

        // Notify the assignee
        if ($assignee->id !== $user->id) {
            broadcast(new TicketAssigned($ticket->load('user'), $assignee->id));
        }

        return response()->json([
            'message' => 'Ticket assigned successfully',
            'ticket' => $ticket->fresh()->load(['user', 'assignee']),
        ]);
    }

    /**
     * Get available staff for assignment.
     */
    public function getStaff(): JsonResponse
    {
        $staff = User::whereHas('role', function ($query) {
            $query->whereIn('name', [Role::ADMIN, Role::INCHARGE]);
        })->get();

        return response()->json(['staff' => $staff]);
    }

    /**
     * Get ticket statistics.
     */
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Ticket::query();

        if ($user->isUser()) {
            $query->where('user_id', $user->id);
        } elseif ($user->isIncharge()) {
            $query->where('assigned_to', $user->id);
        }

        $stats = [
            'total' => (clone $query)->count(),
            'open' => (clone $query)->where('status', Ticket::STATUS_OPEN)->count(),
            'in_progress' => (clone $query)->where('status', Ticket::STATUS_IN_PROGRESS)->count(),
            'pending' => (clone $query)->where('status', Ticket::STATUS_PENDING)->count(),
            'resolved' => (clone $query)->where('status', Ticket::STATUS_RESOLVED)->count(),
            'closed' => (clone $query)->where('status', Ticket::STATUS_CLOSED)->count(),
        ];

        // Admin gets additional stats
        if ($user->isAdmin()) {
            $stats['unassigned'] = Ticket::whereNull('assigned_to')->active()->count();
            $stats['critical'] = Ticket::where('priority', Ticket::PRIORITY_CRITICAL)->active()->count();
        }

        return response()->json(['stats' => $stats]);
    }

    /**
     * Delete a ticket (admin only).
     */
    public function destroy(Request $request, Ticket $ticket): JsonResponse
    {
        $user = $request->user();

        if (!$user->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Remove stored attachments before deleting
        foreach ($ticket->messages as $message) {
            if ($message->attachments) {
                foreach ($message->attachments as $attachment) {
                    if (isset($attachment['path'])) {
                        \Illuminate\Support\Facades\Storage::disk('public')->delete($attachment['path']);
                    }
                }
            }
        }

        $ticket->delete();

        return response()->json(['message' => 'Ticket deleted successfully']);
    }
}

