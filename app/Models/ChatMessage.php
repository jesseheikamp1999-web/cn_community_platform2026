<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatMessage extends Model
{
    protected $fillable = [
        'conversation_id', 'sender_id', 'reply_to_id', 'task_id', 'body',
        'is_announcement', 'requires_ack', 'pinned_at', 'pinned_by',
        'edited_at', 'deleted_at',
    ];

    protected function casts(): array
    {
        return [
            'is_announcement' => 'boolean',
            'requires_ack' => 'boolean',
            'pinned_at' => 'datetime',
            'edited_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'conversation_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reply_to_id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ChatMessageAttachment::class, 'message_id');
    }

    public function readers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'chat_message_reads', 'message_id', 'user_id')
            ->withPivot('read_at');
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(ChatMessageReaction::class, 'message_id');
    }

    public function acknowledgements(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'chat_message_acknowledgements', 'message_id', 'user_id')
            ->withPivot('acknowledged_at');
    }
}
