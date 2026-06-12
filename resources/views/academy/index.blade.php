@extends('layouts.dashboard')
@section('title', 'CN Academy 2026 - MijnCN')

@section('content')
<main class="module-shell academy-world">
    <header class="module-header">
        <div><span class="dashboard-kicker">CN ACADEMY 2026</span><h1>Groeien binnen het CN-team</h1><p>Van Helper naar Management. Iedere staffopleiding bouwt verder op verantwoordelijkheid, praktijk en bewezen kennis.</p></div>
        <div class="academy-rule"><strong>80%</strong><span>minimale examenscore</span></div>
    </header>

    <section class="academy-philosophy">
        <span>OPLEIDINGSFILOSOFIE</span>
        <h2>Geen simpele training.<br>Een volledig groeisysteem.</h2>
        <p>50 lessen, vijf tussentoetsen, praktijkopdrachten en een eindexamen per opleiding. Staffopleidingen zijn beschikbaar op basis van je Discord-rol of een toewijzing door Management.</p>
    </section>

    <section class="academy-map" aria-label="Academy wereldkaart">
        @foreach($paths as $path)
            <article class="academy-map-node {{ $path->is_unlocked ? 'unlocked' : 'locked' }} {{ $path->progress_percentage === 100 ? 'completed' : '' }}">
                <div class="academy-map-index">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</div>
                <div class="academy-map-content">
                    <span>{{ strtoupper($path->target_role) }} GEBIED</span>
                    <h2>{{ $path->name }}</h2>
                    <p>{{ $path->description }}</p>
                    <div class="academy-map-meta"><b>{{ $path->lessons->where('type', 'lesson')->count() }} lessen</b><b>5 toetsen</b><b>2 praktijkopdrachten</b></div>
                    <div class="academy-map-progress"><i style="width:{{ $path->progress_percentage }}%"></i></div>
                    <small>{{ $path->passed_count }} van {{ $path->lessons->count() }} onderdelen behaald</small>
                </div>
                <div class="academy-map-action">
                    @if($path->is_unlocked)
                        <a class="button button-primary" href="{{ route('academy.path', $path) }}">{{ $path->progress_percentage ? 'Verder leren' : 'Start opleiding' }}</a>
                    @else
                        <span class="academy-locked">Vergrendeld voor jouw rol</span>
                    @endif
                </div>
            </article>
            @unless($loop->last)<div class="academy-map-line"><i></i></div>@endunless
        @endforeach
    </section>
</main>
@endsection
