<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Content;
use App\Services\CommunityAutomationService;
use App\Services\ExternalNewsService;
use App\Services\NomiKnowledgeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ContentController extends Controller
{
    public function index(): View
    {
        $articles = Content::where('type', 'news')->with('author')->latest()->paginate(20);
        $weekStart = now()->startOfWeek();
        $weekDays = collect(range(0, 6))->map(function (int $offset) use ($weekStart) {
            $date = $weekStart->copy()->addDays($offset);

            return [
                'date' => $date,
                'items' => Content::where('type', 'news')
                    ->whereIn('status', ['scheduled', 'published'])
                    ->whereBetween('published_at', [$date->copy()->startOfDay(), $date->copy()->endOfDay()])
                    ->orderBy('published_at')
                    ->get(),
            ];
        });
        $stats = [
            'published' => Content::where('type', 'news')->where('status', 'published')->count(),
            'scheduled' => Content::where('type', 'news')->where('status', 'scheduled')->count(),
            'external' => Content::where('type', 'news')->get()->filter(fn (Content $content) => (bool) data_get($content->meta, 'external'))->count(),
        ];

        return view('staff.news.index', compact('articles', 'weekDays', 'stats'));
    }

    public function syncExternal(ExternalNewsService $externalNews, CommunityAutomationService $automation): RedirectResponse
    {
        $result = $externalNews->sync(force: true);
        $pushed = $automation->processPublishedNews();
        $message = $result['created'].' externe nieuwsberichten toegevoegd, '.$result['updated'].' bijgewerkt.';
        $message .= ' Discord-pushes: '.$pushed.'.';
        if (!empty($result['errors'])) {
            return back()->with('error', $message.' Niet alle feeds konden worden gelezen: '.implode(' | ', $result['errors']));
        }

        return back()->with('success', $message);
    }

    public function create(): View
    {
        return view('staff.news.form', ['article' => new Content()]);
    }

    public function store(Request $request, NomiKnowledgeService $knowledge, CommunityAutomationService $automation): RedirectResponse
    {
        $article = Content::create($this->validated($request) + [
            'type' => 'news',
            'author_id' => $request->user()->id,
        ]);
        $knowledge->indexContent($article);
        $automation->announceNews($article);

        return redirect()->route('staff.news.edit', $article)->with('success', 'Nieuwsbericht opgeslagen.');
    }

    public function edit(Content $article): View
    {
        abort_unless($article->type === 'news', 404);

        return view('staff.news.form', compact('article'));
    }

    public function update(Request $request, Content $article, NomiKnowledgeService $knowledge, CommunityAutomationService $automation): RedirectResponse
    {
        abort_unless($article->type === 'news', 404);
        $article->update($this->validated($request, $article));
        $article = $article->fresh();
        $knowledge->indexContent($article);
        $automation->announceNews($article);

        return back()->with('success', 'Nieuwsbericht bijgewerkt.');
    }

    public function destroy(Content $article): RedirectResponse
    {
        abort_unless($article->type === 'news', 404);
        $article->delete();

        return redirect()->route('staff.news.index')->with('success', 'Nieuwsbericht verwijderd.');
    }

    private function validated(Request $request, ?Content $article = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'slug' => ['nullable', 'string', 'max:180', Rule::unique('contents', 'slug')->ignore($article)],
            'excerpt' => ['required', 'string', 'max:500'],
            'body' => ['required', 'string', 'min:80'],
            'cover_image' => ['nullable', 'url', 'max:500'],
            'status' => ['required', 'in:draft,scheduled,published,archived'],
            'published_at' => ['nullable', 'date'],
        ]);
        $data['slug'] = Str::slug(($data['slug'] ?? null) ?: $data['title']);
        if ($data['status'] === 'published' && empty($data['published_at'])) {
            $data['published_at'] = now();
        }
        if ($data['status'] === 'scheduled' && empty($data['published_at'])) {
            abort(422, 'Kies een publicatiemoment voor een ingepland bericht.');
        }

        return $data;
    }
}
