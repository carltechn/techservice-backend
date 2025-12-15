<?php

namespace App\Http\Controllers\Api;

use App\Events\MessageDeleted;
use App\Events\MessageSent;
use App\Events\MessageUpdated;
use App\Events\NewMessageNotification;
use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MessageController extends Controller
{
    public function index(Request $request, Ticket $ticket): JsonResponse
    {
        $user = $request->user();

        if ($user->isUser() && $ticket->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($user->isIncharge() && $ticket->assigned_to !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $messages = $ticket->messages()
            ->with(['user.role'])
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($message) {
                if ($message->user && $message->user->relationLoaded('role')) {
                    $role = $message->user->getRelation('role');
                    $message->user->setAttribute('role', $role?->name);
                }
                return $message;
            });

        $ticket->messages()
            ->where('user_id', '!=', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['messages' => $messages]);
    }

    public function store(Request $request, Ticket $ticket): JsonResponse
    {
        $user = $request->user();

        if ($user->isUser() && $ticket->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($user->isIncharge() && $ticket->assigned_to !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (in_array($ticket->status, [Ticket::STATUS_RESOLVED, Ticket::STATUS_CLOSED])) {
            return response()->json(['message' => 'Cannot send messages to closed tickets'], 403);
        }

        $validated = $request->validate([
            'content' => 'nullable|string|max:5000',
            'attachments' => 'nullable|array|max:10',
            'attachments.*' => 'file|max:20480',
            'urls' => 'nullable|array|max:5',
            'urls.*' => 'url|max:2000',
        ]);

        $attachments = [];

        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $mimeType = $file->getMimeType();
                $path = $file->store('attachments/' . $ticket->id, 'public');

                $attachments[] = [
                    'type' => Message::getAttachmentType($mimeType),
                    'path' => $path,
                    'url' => url('storage/' . $path),
                    'name' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                    'mime' => $mimeType,
                ];
            }
        }

        if (!empty($validated['urls'])) {
            foreach ($validated['urls'] as $url) {
                $attachments[] = [
                    'type' => 'url',
                    'url' => $url,
                    'name' => $url,
                ];
            }
        }

        if (empty($validated['content']) && empty($attachments)) {
            return response()->json(['message' => 'Message content or attachment required'], 422);
        }

        $message = Message::create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'content' => $validated['content'] ?? '',
            'attachments' => !empty($attachments) ? $attachments : null,
        ]);

        if ($user->isStaff() && $ticket->status === Ticket::STATUS_OPEN) {
            $ticket->update(['status' => Ticket::STATUS_IN_PROGRESS]);
        }

        if ($user->isUser() && in_array($ticket->status, [Ticket::STATUS_RESOLVED, Ticket::STATUS_PENDING])) {
            $ticket->update(['status' => Ticket::STATUS_IN_PROGRESS]);
            Message::createSystemMessage($ticket, 'Ticket reopened by user response');
        }

        $message->load('user');

        broadcast(new MessageSent($message))->toOthers();
        $this->notifyParticipants($ticket, $message, $user);

        return response()->json(['message' => $message], 201);
    }

    public function update(Request $request, Ticket $ticket, Message $message): JsonResponse
    {
        $user = $request->user();

        if ($user->isUser() && $ticket->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($user->isIncharge() && $ticket->assigned_to !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Only the message author can edit
        if ($message->user_id !== $user->id) {
            return response()->json(['message' => 'You can only edit your own messages'], 403);
        }

        // Can't edit system messages
        if ($message->is_system_message) {
            return response()->json(['message' => 'Cannot edit system messages'], 403);
        }

        $validated = $request->validate([
            'content' => 'required|string|max:5000',
        ]);

        $message->editContent($validated['content']);
        $message->load('user');

        broadcast(new MessageUpdated($message))->toOthers();

        return response()->json(['message' => $message]);
    }

    public function destroy(Request $request, Ticket $ticket, Message $message): JsonResponse
    {
        $user = $request->user();

        if ($user->isUser() && $ticket->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($user->isIncharge() && $ticket->assigned_to !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Only the message author or admin can delete
        if ($message->user_id !== $user->id && !$user->isAdmin()) {
            return response()->json(['message' => 'You can only delete your own messages'], 403);
        }

        // Can't delete system messages
        if ($message->is_system_message) {
            return response()->json(['message' => 'Cannot delete system messages'], 403);
        }

        $messageId = $message->id;
        $ticketId = $message->ticket_id;

        // Delete attachments from storage
        if ($message->attachments) {
            foreach ($message->attachments as $attachment) {
                if (isset($attachment['path'])) {
                    Storage::disk('public')->delete($attachment['path']);
                }
            }
        }

        $message->delete();

        broadcast(new MessageDeleted($messageId, $ticketId))->toOthers();

        return response()->json(['message' => 'Message deleted']);
    }

    public function download(Request $request, string $path): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $fullPath = storage_path('app/public/attachments/' . $path);

        if (!file_exists($fullPath)) {
            abort(404, 'File not found');
        }

        $fileName = basename($path);
        $mimeType = mime_content_type($fullPath) ?: 'application/octet-stream';

        return response()->download($fullPath, $fileName, [
            'Content-Type' => $mimeType,
        ]);
    }

    public function markAsRead(Request $request, Ticket $ticket): JsonResponse
    {
        $user = $request->user();

        if ($user->isUser() && $ticket->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($user->isIncharge() && $ticket->assigned_to !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $ticket->messages()
            ->where('user_id', '!=', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['message' => 'Messages marked as read']);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $user = $request->user();

        $ticketIds = $user->isUser()
            ? $user->tickets()->pluck('id')
            : $user->assignedTickets()->pluck('id');

        $count = Message::whereIn('ticket_id', $ticketIds)
            ->where('user_id', '!=', $user->id)
            ->whereNull('read_at')
            ->count();

        return response()->json(['unread_count' => $count]);
    }

    private function notifyParticipants(Ticket $ticket, Message $message, $sender): void
    {
        $recipientIds = [];

        if ($sender->isStaff() && $ticket->user_id !== $sender->id) {
            $recipientIds[] = $ticket->user_id;
        }

        if ($sender->isUser() && $ticket->assigned_to && $ticket->assigned_to !== $sender->id) {
            $recipientIds[] = $ticket->assigned_to;
        }

        foreach ($recipientIds as $recipientId) {
            broadcast(new NewMessageNotification($message, $recipientId));
        }
    }
}
