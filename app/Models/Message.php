<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Message extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'ticket_id',
        'user_id',
        'content',
        'attachments',
        'edit_history',
        'edited_at',
        'is_system_message',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'is_system_message' => 'boolean',
            'read_at' => 'datetime',
            'edited_at' => 'datetime',
            'attachments' => 'array',
            'edit_history' => 'array',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    public function markAsRead(): void
    {
        if (!$this->isRead()) {
            $this->update(['read_at' => now()]);
        }
    }

    public function hasAttachments(): bool
    {
        return !empty($this->attachments);
    }

    public function isEdited(): bool
    {
        return $this->edited_at !== null;
    }

    /**
     * Edit the message and save history.
     */
    public function editContent(string $newContent): void
    {
        $history = $this->edit_history ?? [];

        // Add current content to history
        $history[] = [
            'content' => $this->content,
            'edited_at' => now()->toISOString(),
        ];

        $this->update([
            'content' => $newContent,
            'edit_history' => $history,
            'edited_at' => now(),
        ]);
    }

    public static function createSystemMessage(Ticket $ticket, string $content): self
    {
        return self::create([
            'ticket_id' => $ticket->id,
            'user_id' => $ticket->user_id,
            'content' => $content,
            'is_system_message' => true,
        ]);
    }

    public static function getAttachmentType(string $mimeType): string
    {
        if (str_starts_with($mimeType, 'image/')) {
            return 'image';
        }
        if (str_starts_with($mimeType, 'video/')) {
            return 'video';
        }
        return 'document';
    }
}
