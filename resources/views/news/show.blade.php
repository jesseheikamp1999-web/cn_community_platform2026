@extends('layouts.app')
@section('title', $content->title.' - CN Community')
@section('description', $content->excerpt)
@section('content')
<article class="news-detail">
    <header><a href="{{ route('nieuws') }}">&larr; Alle nieuwsberichten</a><span class="eyebrow"><i></i> CN NIEUWS</span><h1>{{ $content->title }}</h1><p>{{ $content->excerpt }}</p><small>{{ $content->published_at->translatedFormat('j F Y') }} @if($content->author) &middot; door {{ $content->author->name }} @endif</small></header>
    @if($content->cover_image)<img class="news-detail-cover" src="{{ $content->cover_image }}" alt="{{ $content->title }}">@endif
    <div class="news-detail-body">{!! $content->body !!}</div>
</article>
@endsection
