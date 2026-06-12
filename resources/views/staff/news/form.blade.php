@extends('layouts.dashboard')
@section('title', ($article->exists ? 'Nieuws bewerken' : 'Nieuws maken').' - MijnCN')
@section('content')
<main class="module-shell">
    <header class="module-header"><div><span class="dashboard-kicker">CN REDACTIE</span><h1>{{ $article->exists ? 'Bericht bewerken' : 'Nieuw nieuwsbericht' }}</h1><p>Publiceer direct of plan het bericht voor een later moment.</p></div><a class="button button-secondary" href="{{ route('staff.news.index') }}">Terug</a></header>
    <section class="module-card padded">
        <form class="module-form editorial-form" method="post" action="{{ $article->exists ? route('staff.news.update', $article) : route('staff.news.store') }}">
            @csrf @if($article->exists) @method('PUT') @endif
            <div class="form-grid"><label>Titel<input name="title" value="{{ old('title', $article->title) }}" required maxlength="180"></label><label>SEO-vriendelijke URL<input name="slug" value="{{ old('slug', $article->slug) }}" placeholder="wordt-automatisch-gemaakt"></label></div>
            <label>Samenvatting<textarea name="excerpt" rows="3" required maxlength="500">{{ old('excerpt', $article->excerpt) }}</textarea></label>
            <label>Artikelinhoud <small>HTML-opmaak wordt ondersteund</small><textarea name="body" rows="18" required minlength="80">{{ old('body', $article->body) }}</textarea></label>
            <label>Omslagafbeelding URL<input type="url" name="cover_image" value="{{ old('cover_image', $article->cover_image) }}"></label>
            <div class="form-grid"><label>Status<select name="status" required>@foreach(['draft'=>'Concept','scheduled'=>'Ingepland','published'=>'Gepubliceerd','archived'=>'Gearchiveerd'] as $value => $label)<option value="{{ $value }}" @selected(old('status', $article->status ?: 'draft') === $value)>{{ $label }}</option>@endforeach</select></label><label>Publicatiemoment<input type="datetime-local" name="published_at" value="{{ old('published_at', $article->published_at?->format('Y-m-d\TH:i')) }}"></label></div>
            <button class="button button-primary">{{ $article->exists ? 'Wijzigingen opslaan' : 'Bericht opslaan' }}</button>
        </form>
        @if($article->exists)<form method="post" action="{{ route('staff.news.destroy', $article) }}" onsubmit="return confirm('Bericht definitief verwijderen?')">@csrf @method('DELETE')<button class="button button-secondary">Verwijderen</button></form>@endif
    </section>
</main>
@endsection
