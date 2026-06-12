@extends('layouts.app')
@section('title', 'Finale - '.$edition->name)

@section('content')
<section class="awards-page-hero finale-hero"><div><span class="eyebrow light"><i></i> LIVE REVEAL</span><h1>{{ $edition->name }} Finale</h1><p>De finalisten worden per positie onthuld. Verborgen plaatsen blijven geheim tot Management de reveal vrijgeeft.</p></div></section>
<main class="awards-page finale-page">
    @forelse($finalists as $category => $entries)
        <section class="finale-category"><header><span>FINALISTEN</span><h2>{{ $category }}</h2></header><div class="finale-grid">
            @foreach($entries as $entry)
                <article class="{{ $entry->revealed_position_at ? 'revealed' : 'secret' }}"><div class="finale-position">#{{ $entry->position }}</div>@if($entry->revealed_position_at)<div class="award-nominee-mark">@if($entry->logo_url)<img src="{{ $entry->logo_url }}" alt="">@else{{ strtoupper(substr($entry->nominee_name,0,2)) }}@endif</div><h3>{{ $entry->nominee_name }}</h3><p>Totaalscore {{ round($entry->final_score,1) }}%</p>@else<h3>Nog geheim</h3><p>Deze positie is nog niet onthuld.</p>@endif</article>
            @endforeach
        </div></section>
    @empty<div class="award-empty">De finale wordt voorbereid.</div>@endforelse
</main>
@endsection
