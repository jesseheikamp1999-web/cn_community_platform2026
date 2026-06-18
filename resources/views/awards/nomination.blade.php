@extends('layouts.app')
@section('title', $nomination->nominee_name.' - '.$nomination->category->edition->name)

@section('content')
<section class="nominee-profile-hero" @if($nomination->banner_url) style="background-image:linear-gradient(90deg,rgba(16,17,21,.96),rgba(16,17,21,.68)),url('{{ $nomination->banner_url }}')" @endif>
    <div class="nominee-profile-inner">
        <div class="nominee-profile-logo">@if($nomination->logo_url)<img src="{{ $nomination->logo_url }}" alt="{{ $nomination->nominee_name }}">@else{{ strtoupper(substr($nomination->nominee_name,0,2)) }}@endif</div>
        <div><span>{{ $nomination->category->name }}</span><h1>{{ $nomination->nominee_name }}</h1><p>{{ $nomination->is_verified ? 'Geverifieerde finalist' : 'Officieel genomineerd' }}</p></div>
    </div>
</section>
<main class="nominee-profile-page">
    <article>
        <span class="eyebrow"><i></i> NOMINATIEPROFIEL</span>
        <h2>Waarom deze nominatie?</h2>
        {!! \App\Support\SafeMarkdown::render($nomination->motivation) !!}
        @if($nomination->evidence_text)
            <section class="nominee-evidence">
                <h3>Bewijs en historie</h3>
                {!! \App\Support\SafeMarkdown::render($nomination->evidence_text) !!}
            </section>
        @endif
        <div class="nominee-links">
            @if($nomination->website_url)<a class="button button-secondary" href="{{ $nomination->website_url }}" target="_blank" rel="noopener">Website</a>@endif
            @if($nomination->discord_invite)<a class="button button-secondary" href="{{ $nomination->discord_invite }}" target="_blank" rel="noopener">Discord</a>@endif
            @if($nomination->evidence_url)<a class="button button-secondary" href="{{ $nomination->evidence_url }}" target="_blank" rel="noopener">Bewijs bekijken</a>@endif
        </div>
    </article>
    <aside class="nominee-score-card">
        <span>AWARDS STATUS</span>
        <h2>{{ ucfirst($nomination->status) }}</h2>
        <div><strong>{{ $validVotes }}</strong><small>community stemmen</small></div>
        <div><strong>{{ $juryScore ?: '–' }}{{ $juryScore ? '%' : '' }}</strong><small>jury gemiddelde</small></div>
        <div><strong>{{ round($nomination->reputation_score) }}</strong><small>reputatie</small></div>
        @auth
            @if($activeVoteRound)
                <form method="post" action="{{ route('awards.vote',$nomination) }}">@csrf<input type="hidden" name="round_id" value="{{ $activeVoteRound->id }}"><button class="button button-primary">{{ $hasVoted ? 'Jouw stem' : 'Stem op deze kandidaat' }}</button></form>
            @endif
        @endauth
    </aside>
</main>
@endsection
