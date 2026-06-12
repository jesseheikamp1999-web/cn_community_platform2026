@extends('layouts.dashboard')
@section('title', 'Nieuwsbeheer - MijnCN')
@section('content')
<main class="module-shell">
    <header class="module-header"><div><span class="dashboard-kicker">REDACTIE</span><h1>Nieuwsbeheer</h1><p>Schrijf, plan en publiceer verhalen voor CN Community.</p></div><a class="button button-primary" href="{{ route('staff.news.create') }}">Nieuw bericht</a></header>
    <section class="module-card"><div class="module-list">
        @forelse($articles as $article)<article><div><strong>{{ $article->title }}</strong><p>{{ ucfirst($article->status) }} &middot; {{ $article->published_at?->translatedFormat('j F Y H:i') ?? 'geen publicatiemoment' }} &middot; {{ $article->author?->name }}</p></div><a class="button button-secondary button-small" href="{{ route('staff.news.edit', $article) }}">Bewerken</a></article>
        @empty<div class="module-empty"><h3>Nog geen berichten</h3><p>Maak het eerste nieuwsbericht voor CN Community.</p></div>@endforelse
    </div><div class="padded">{{ $articles->links() }}</div></section>
</main>
@endsection
