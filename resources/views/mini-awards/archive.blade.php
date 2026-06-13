@extends('layouts.app')

@section('title', 'Mini Awards Archief - CN Community')

@section('content')
<section class="awards-page-hero mini-awards-hero">
    <div>
        <span class="eyebrow light"><i></i> MINI AWARDS ARCHIEF</span>
        <h1>Kleine prijzen.<br>Blijvende herinneringen.</h1>
        <p>Alle afgeronde Mini Awards-edities en hun winnaars, volledig gescheiden van CN Awards.</p>
    </div>
    <a class="button button-secondary" href="{{ route('mini.awards') }}">Actuele editie</a>
</section>

<section class="awards-page mini-archive">
    @forelse($editions as $edition)
        <section class="mini-archive-edition">
            <header><span>{{ $edition->year }}</span><h2>{{ $edition->name }}</h2></header>
            <div class="award-nominee-grid">
                @foreach($edition->categories as $category)
                    @forelse($category->nominations as $winner)
                        <article class="award-nominee-card winner">
                            <div class="award-nominee-mark">{{ strtoupper(substr($winner->nominee_name, 0, 2)) }}</div>
                            <div><span>MINI-WINNAAR</span><h3>{{ $winner->nominee_name }}</h3><p>{{ $category->name }}</p></div>
                        </article>
                    @empty
                    @endforelse
                @endforeach
            </div>
        </section>
    @empty
        <div class="award-empty">Er zijn nog geen afgeronde Mini Awards-edities.</div>
    @endforelse
</section>
@endsection
