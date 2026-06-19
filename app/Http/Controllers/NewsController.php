<?php

namespace App\Http\Controllers;

use App\Models\Content;
use App\Services\ExternalNewsService;
use Illuminate\View\View;

class NewsController extends Controller
{
    public function index(ExternalNewsService $externalNews): View
    {
        $externalNews->syncIfStale();

        $featured = Content::published()->where('type', 'news')->latest('published_at')->first();
        $articles = Content::published()
            ->where('type', 'news')
            ->when($featured, fn ($query) => $query->whereKeyNot($featured->id))
            ->latest('published_at')
            ->paginate(9);

        return view('news.index', compact('featured', 'articles'));
    }

    public function show(Content $content): View
    {
        abort_unless($content->type === 'news' && $content->status === 'published' && $content->published_at?->lte(now()), 404);
        $content->load('author');

        return view('news.show', compact('content'));
    }
}
