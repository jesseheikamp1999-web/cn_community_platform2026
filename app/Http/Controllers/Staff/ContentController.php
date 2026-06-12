<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Content;
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

        return view('staff.news.index', compact('articles'));
    }

    public function create(): View
    {
        return view('staff.news.form', ['article' => new Content()]);
    }

    public function store(Request $request, NomiKnowledgeService $knowledge): RedirectResponse
    {
        $article = Content::create($this->validated($request) + [
            'type' => 'news',
            'author_id' => $request->user()->id,
        ]);
        $knowledge->indexContent($article);

        return redirect()->route('staff.news.edit', $article)->with('success', 'Nieuwsbericht opgeslagen.');
    }

    public function edit(Content $article): View
    {
        abort_unless($article->type === 'news', 404);

        return view('staff.news.form', compact('article'));
    }

    public function update(Request $request, Content $article, NomiKnowledgeService $knowledge): RedirectResponse
    {
        abort_unless($article->type === 'news', 404);
        $article->update($this->validated($request, $article));
        $knowledge->indexContent($article->fresh());

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
