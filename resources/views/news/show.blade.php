@extends('layouts.app')
@section('title', $content->title.' - '.__('public.brand.name'))
@section('description', $content->excerpt)
@section('content')
<article class="news-detail">
    <header><a href="{{ route('nieuws') }}">&larr; {{ app()->getLocale() === 'en' ? 'All articles' : 'Alle nieuwsberichten' }}</a><span class="eyebrow"><i></i> {{ data_get($content->meta, 'source', 'CONNECT NEXT NEWS') }}</span><h1>{{ $content->title }}</h1><p>{{ $content->excerpt }}</p><small>{{ $content->published_at->translatedFormat('j F Y') }} @if($content->author) &middot; door {{ $content->author->name }} @endif</small></header>
    @if($content->cover_image)<img class="news-detail-cover" src="{{ $content->cover_image }}" alt="{{ $content->title }}">@endif
    @if(data_get($content->meta, 'external') && data_get($content->meta, 'source_url'))
        <p class="external-news-source">Bron: <a href="{{ data_get($content->meta, 'source_url') }}" target="_blank" rel="noopener noreferrer">{{ data_get($content->meta, 'source') }}</a></p>
    @endif
    <div class="news-detail-body">{!! $content->body !!}</div>
</article>
@endsection
