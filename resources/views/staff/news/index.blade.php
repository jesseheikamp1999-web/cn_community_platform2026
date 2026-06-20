@extends('layouts.dashboard')
@section('title', 'Nieuwsbeheer - MijnCN')
@section('content')
<main class="module-shell editorial-dashboard">
    <header class="module-header">
        <div>
            <span class="dashboard-kicker">REDACTIE</span>
            <h1>Nieuwsbeheer</h1>
            <p>Schrijf, plan, publiceer en importeer verhalen voor CN Community.</p>
        </div>
        <div class="header-actions">
            <form method="post" action="{{ route('staff.news.sync-external') }}">
                @csrf
                <button class="button button-secondary">NU.nl / NOS ophalen</button>
            </form>
            <a class="button button-primary" href="{{ route('staff.news.create') }}">Nieuw bericht</a>
        </div>
    </header>

    <section class="editorial-stats">
        <article><span>Gepubliceerd</span><strong>{{ $stats['published'] }}</strong><small>live op de website</small></article>
        <article><span>Ingepland</span><strong>{{ $stats['scheduled'] }}</strong><small>wacht op publicatie</small></article>
        <article><span>Externe feed</span><strong>{{ $stats['external'] }}</strong><small>NU.nl / NOS</small></article>
    </section>

    <section class="module-card editorial-calendar">
        <div class="module-card-heading">
            <div><span>WEEKPLANNER</span><h2>Nieuwsagenda</h2></div>
            <a class="text-action" href="{{ route('staff.news.create') }}">Plan CN-post</a>
        </div>
        <div class="editorial-week">
            @foreach($weekDays as $day)
                <article class="{{ $day['date']->isToday() ? 'today' : '' }}">
                    <header>
                        <span>{{ $day['date']->translatedFormat('D') }}</span>
                        <strong>{{ $day['date']->format('d') }}</strong>
                    </header>
                    <div>
                        @forelse($day['items'] as $item)
                            <a href="{{ data_get($item->meta, 'external') ? data_get($item->meta, 'source_url') : route('staff.news.edit', $item) }}" @if(data_get($item->meta, 'external')) target="_blank" rel="noopener noreferrer" @endif>
                                <b>{{ $item->published_at?->format('H:i') }}</b>
                                <span>{{ $item->title }}</span>
                                <small>{{ data_get($item->meta, 'source', 'CN') }} · {{ $item->status }}</small>
                            </a>
                        @empty
                            <p>Geen post gepland.</p>
                        @endforelse
                    </div>
                </article>
            @endforeach
        </div>
    </section>

    <section class="module-card editorial-list-card">
        <div class="module-card-heading"><div><span>REDACTIEOVERZICHT</span><h2>Alle nieuwsberichten</h2></div></div>
        <div class="editorial-list">
            @forelse($articles as $article)
                <article>
                    <div class="editorial-thumb" @if($article->cover_image) style="background-image:url('{{ $article->cover_image }}')" @endif>
                        <span>{{ strtoupper(substr(data_get($article->meta, 'source', 'CN'), 0, 3)) }}</span>
                    </div>
                    <div>
                        <div class="editorial-meta">
                            <span>{{ data_get($article->meta, 'source', 'CN') }}</span>
                            <span>{{ ucfirst($article->status) }}</span>
                            <span>{{ $article->published_at?->translatedFormat('j F Y H:i') ?? 'geen publicatiemoment' }}</span>
                        </div>
                        <h3>{{ $article->title }}</h3>
                        <p>{{ $article->excerpt }}</p>
                    </div>
                    <div class="editorial-actions">
                        @if(data_get($article->meta, 'external'))
                            <a class="button button-secondary button-small" href="{{ data_get($article->meta, 'source_url') }}" target="_blank" rel="noopener noreferrer">Bron bekijken</a>
                        @else
                            <a class="button button-secondary button-small" href="{{ route('staff.news.edit', $article) }}">Bewerken</a>
                        @endif
                    </div>
                </article>
            @empty
                <div class="module-empty"><h3>Nog geen berichten</h3><p>Maak het eerste nieuwsbericht voor CN Community.</p></div>
            @endforelse
        </div>
        <div class="padded">{{ $articles->links() }}</div>
    </section>
</main>
@endsection
