<?php

namespace App\Repositories;

use App\Models\Content;
use App\Services\ExternalNewsService;

class ContentRepository
{
    public function __construct(private readonly ExternalNewsService $externalNews)
    {
    }

    public function latestNews(int $limit = 6)
    {
        $this->externalNews->syncIfStale();

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
