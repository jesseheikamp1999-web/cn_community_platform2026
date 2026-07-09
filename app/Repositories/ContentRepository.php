<?php

namespace App\Repositories;

use App\Models\Content;
use App\Services\ExternalNewsService;
use Illuminate\Support\Collection;
use Throwable;

class ContentRepository
{
    public function __construct(private readonly ExternalNewsService $externalNews)
    {
    }

    public function latestNews(int $limit = 6): Collection
    {
        try {
            $this->externalNews->syncIfStale();

            return Content::published()
                ->where('type', 'news')
                ->latest('published_at')
                ->limit($limit)
                ->get();
        } catch (Throwable) {
            return collect();
        }
    }

    public function upcomingEvents(int $limit = 5): Collection
    {
        try {
            return Content::published()
                ->where('type', 'event')
                ->where('meta->starts_at', '>=', now()->toIso8601String())
                ->orderBy('meta->starts_at')
                ->limit($limit)
                ->get();
        } catch (Throwable) {
            return collect();
        }
    }
}
