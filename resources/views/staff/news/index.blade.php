@extends('layouts.dashboard')
@section('title', 'Nieuwsbeheer - MijnCN')
@section('content')
<main class="module-shell">
    <header class="module-header"><div><span class="dashboard-kicker">REDACTIE</span><h1>Nieuwsbeheer</h1><p>Schrijf, plan, publiceer en importeer verhalen voor CN Community.</p></div><div class="header-actions"><form method="post" action="{{ route('staff.news.sync-external') }}">@csrf<button class="button button-secondary">NU.nl / NOS ophalen</button></form><a class="button button-primary" href="{{ route('staff.news.create') }}">Nieuw bericht</a></div></header>
    <section class="module-card"><div class="module-list">
        @forelse($articles as $article)<article><div><strong>{{ $article->title }}</strong><p>{{ data_get($article->meta, 'source', 'CN') }} &middot; {{ ucfirst($article->status) }} &middot; {{ $article->published_at?->translatedFormat('j F Y H:i') ?? 'geen publicatiemoment' }} @if($article->author) &middot; {{ $article->author->name }} @endif</p></div>@if(data_get($article->meta, 'external'))<a class="button button-secondary button-small" href="{{ data_get($article->meta, 'source_url') }}" target="_blank" rel="noopener noreferrer">Bron bekijken</a>@else<a class="button button-secondary button-small" href="{{ route('staff.news.edit', $article) }}">Bewerken</a>@endif</article>
        @empty<div class="module-empty"><h3>Nog geen berichten</h3><p>Maak het eerste nieuwsbericht voor CN Community.</p></div>@endforelse
    </div><div class="padded">{{ $articles->links() }}</div></section>
</main>
@endsection
