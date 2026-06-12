<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Content extends Model
{
    protected $table = 'contents';
    protected $fillable = ['author_id', 'type', 'title', 'slug', 'excerpt', 'body', 'cover_image', 'status', 'published_at', 'meta'];

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'meta' => 'array'];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published')->where('published_at', '<=', now());
    }
}
