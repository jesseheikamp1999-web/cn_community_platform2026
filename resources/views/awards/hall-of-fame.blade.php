@extends('layouts.app')
@section('title', 'Hall of Fame - CN Awards')

@section('content')
<section class="awards-page-hero"><div><span class="eyebrow light"><i></i> HALL OF FAME</span><h1>De geschiedenis van CN Awards.</h1><p>Alle gepubliceerde winnaars, geordend per editie en categorie.</p></div></section>
<main class="awards-page hall-page">
    @forelse($years as $year => $winners)
        <section class="hall-year"><header><span>EDITIE</span><h2>{{ $year }}</h2></header><div class="award-nominee-grid">
            @foreach($winners as $winner)<article class="award-nominee-card winner"><div class="award-nominee-mark">@if($winner->logo_url)<img src="{{ $winner->logo_url }}" alt="">@else{{ strtoupper(substr($winner->nominee_name,0,2)) }}@endif</div><div><span>WINNAAR</span><h3>{{ $winner->nominee_name }}</h3><p>{{ $winner->category_name }}</p></div><footer><small>Eindscore {{ round($winner->final_score,1) }}%</small></footer></article>@endforeach
        </div></section>
    @empty<div class="award-empty">Nog geen gepubliceerde winnaars.</div>@endforelse
</main>
@endsection
