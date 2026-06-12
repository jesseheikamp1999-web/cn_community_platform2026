<?php

namespace App\Repositories;

use App\Models\Content;

class ContentRepository
{
    public function latestNews(int $limit = 6)
    {
        return Content::published()
            ->where('type', 'news')
            ->latest('published_at')
            ->limit($limit)
            ->get();
    }

    public function upcomingEvents(int $limit = 5)
    {
        return Content::published()
            ->where('type', 'event')
            ->where('meta->starts_at', '>=', now()->toIso8601String())
            ->orderBy('meta->starts_at')
            ->limit($limit)
            ->get();
    }
}
