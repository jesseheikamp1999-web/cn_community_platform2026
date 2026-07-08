@extends('layouts.app')
@section('title', 'Zoeken — Connect Next')

@section('content')
<section class="page-hero connect-page-hero">
    <span class="eyebrow"><i></i> ZOEKEN</span>
    <h1>Vind wat je zoekt.</h1>
    <p>Doorzoek nieuws, projecten, communities en publieke pagina's van Connect Next.</p>
</section>
<section class="page-content">
    <form class="search-box">
        <input type="search" name="q" value="{{ $query }}" placeholder="Zoek op titel of onderwerp..." autofocus>
        <button>Zoeken</button>
    </form>
    <div class="content-grid">
        @foreach($results as $result)
            <article class="content-card">
                <span class="eyebrow"><i></i> {{ strtoupper($result->type) }}</span>
                <h3>{{ $result->title }}</h3>
                <p>{{ $result->excerpt }}</p>
            </article>
        @endforeach
    </div>
    @if($query && $results->isEmpty())
        <div class="empty-state">Geen resultaten voor "{{ $query }}".</div>
    @endif
</section>
@endsection
