@extends('layouts.app')
@section('title', 'Nieuws - CN Community')
@section('description', 'Nieuws, verhalen en ontwikkelingen uit CN Community.')
@section('content')
<section class="news-page-hero">
    <span class="eyebrow"><i></i> CN COMMUNITY</span>
    <h1>Nieuws</h1>
    <p>Verhalen, updates en ontwikkelingen uit de hele CN Community.</p>
</section>
<section class="news-archive section">
    @if($featured)
        <a class="news-featured" href="{{ route('news.show', $featured) }}">
            <div class="news-featured-image" @if($featured->cover_image) style="background-image:url('{{ $featured->cover_image }}')" @endif></div>
            <div><span>UITGELICHT</span><small>{{ $featured->published_at->translatedFormat('j F Y') }}</small><h2>{{ $featured->title }}</h2><p>{{ $featured->excerpt }}</p><strong>Lees het verhaal &rarr;</strong></div>
        </a>
    @endif
    <div class="news-archive-grid">
        @forelse($articles as $article)
            <a class="news-archive-card" href="{{ route('news.show', $article) }}">
                <div @if($article->cover_image) style="background-image:url('{{ $article->cover_image }}')" @endif></div>
                <span>{{ $article->published_at->translatedFormat('j M Y') }}</span><h2>{{ $article->title }}</h2><p>{{ $article->excerpt }}</p><strong>Lees verder &rarr;</strong>
            </a>
        @empty
            @unless($featured)<div class="empty-state"><h3>Nog geen nieuws gepubliceerd</h3><p>Nieuwe verhalen verschijnen hier zodra de redactie ze publiceert.</p></div>@endunless
        @endforelse
    </div>
    {{ $articles->links() }}
</section>
@endsection
