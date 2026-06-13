@extends('layouts.app')

@section('title', ($edition?->name ?? 'Mini Awards').' - CN Community')

@section('content')
<section class="awards-page-hero mini-awards-hero">
    <div>
        <span class="eyebrow light"><i></i> CN MINI AWARDS</span>
        <h1>Kleine momenten.<br>Grote waardering.</h1>
        <p>Korte community-edities met eigen categorieën, eigen nominaties en een snelle publieksstemronde.</p>
    </div>
    @if($edition)
        <div class="awards-phase">
            <span>ACTUELE EDITIE</span>
            <strong>{{ $edition->name }}</strong>
            <small>{{ $edition->categories->count() }} mini-categorieën</small>
        </div>
    @endif
</section>

<section class="awards-page mini-awards-page">
    @if($errors->any())<div class="module-alert error">{{ $errors->first() }}</div>@endif
    @if(session('success'))<div class="module-alert success">{{ session('success') }}</div>@endif

    @if(!$edition)
        <div class="empty-state"><h3>Nog geen Mini Awards-editie</h3><p>De volgende korte communityronde wordt voorbereid.</p></div>
    @else
        <section class="mini-awards-overview">
            <div>
                <span class="eyebrow">SNELLE COMMUNITYRONDE</span>
                <h2>{{ $phaseStatus[0] ?? ucfirst($edition->status) }}</h2>
                @if($phaseDeadline)
                    <p>{{ $phaseStatus[1] ?? 'Volgende fase' }} op {{ $phaseDeadline->translatedFormat('j F Y \o\m H:i') }}.</p>
                @else
                    <p>De planning van deze editie wordt binnenkort bekendgemaakt.</p>
                @endif
            </div>
            @if($phaseDeadline)
                <div class="awards-countdown" data-countdown="{{ $phaseDeadline->toIso8601String() }}">
                    <span><strong data-days>00</strong><small>dagen</small></span>
                    <span><strong data-hours>00</strong><small>uren</small></span>
                    <span><strong data-minutes>00</strong><small>minuten</small></span>
                    <span><strong data-seconds>00</strong><small>seconden</small></span>
                </div>
            @endif
            <a class="button button-secondary" href="{{ route('mini.awards.archive') }}">Bekijk archief</a>
        </section>

        <div class="mini-awards-timeline">
            <span class="{{ $edition->status === 'nominations' ? 'active' : '' }}">1. Nomineren</span>
            <span class="{{ $edition->status === 'voting' ? 'active' : '' }}">2. Stemmen</span>
            <span class="{{ in_array($edition->status, ['published', 'archived'], true) ? 'active' : '' }}">3. Uitslag</span>
        </div>

        @if($activeVoteRound)
            <section class="awards-voting-ready mini">
                <div><span>MINI STEMRONDE ACTIEF</span><h2>Kies jouw favoriet</h2><p>Je hebt per mini-categorie één geldige stem en kunt die aanpassen tot de ronde sluit.</p></div>
                <strong>Open tot {{ $activeVoteRound->ends_at->translatedFormat('d F Y \o\m H:i') }}</strong>
            </section>
        @endif

        @if($selectedCategory)
            <section class="award-category-picker">
                <div><span>MINI-CATEGORIE</span><strong>Bekijk één categorie tegelijk</strong></div>
                <form method="get" action="{{ route('mini.awards') }}">
                    <label for="mini-category-select" class="sr-only">Mini Awards-categorie</label>
                    <select id="mini-category-select" name="categorie" data-category-select>
                        @foreach($edition->categories as $category)
                            <option value="{{ $category->slug }}" @selected($selectedCategory->is($category))>
                                {{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }} · {{ $category->name }} ({{ $category->nominations_count }})
                            </option>
                        @endforeach
                    </select>
                    <button class="button button-secondary button-small">Bekijken</button>
                </form>
            </section>

            @php($category = $selectedCategory)
            @php($categoryNumber = $edition->categories->search(fn ($item) => $item->is($category)) + 1)
            <section class="award-category-section compact">
                <header>
                    <div>
                        <span>{{ str_pad((string) $categoryNumber, 2, '0', STR_PAD_LEFT) }}</span>
                        <h2>{{ $category->name }}</h2>
                        <p>{{ $category->description }}</p>
                    </div>
                    <strong>{{ $category->nominations->count() }} kandidaten</strong>
                </header>

                <div class="award-nominee-grid">
                    @php($voteTotal = max(1, $category->nominations->sum('votes_count')))
                    @forelse($category->nominations as $nomination)
                        @php($votePercent = round($nomination->votes_count / $voteTotal * 100, 1))
                        <article class="award-nominee-card {{ $nomination->status === 'winner' ? 'winner' : '' }}">
                            <div class="award-nominee-mark">{{ strtoupper(substr($nomination->nominee_name, 0, 2)) }}</div>
                            <div>
                                <span>{{ $nomination->status === 'winner' ? 'MINI-WINNAAR' : 'GENOMINEERD' }}</span>
                                <h3>{{ $nomination->nominee_name }}</h3>
                                <p>{{ \Illuminate\Support\Str::limit($nomination->motivation, 160) }}</p>
                            </div>
                            @if($edition->status !== 'nominations')
                                <div class="award-score-strip"><i style="width:{{ $votePercent }}%"></i></div>
                                <div class="award-score-meta"><b>{{ $votePercent }}%</b><span>van de geldige stemmen</span></div>
                            @endif
                            <footer>
                                <small>{{ $nomination->votes_count }} geldige stemmen</small>
                                @auth
                                    @if($activeVoteRound && in_array($nomination->status, ['approved', 'finalist'], true))
                                        <form method="post" action="{{ route('awards.vote', $nomination) }}">
                                            @csrf
                                            <input type="hidden" name="round_id" value="{{ $activeVoteRound->id }}">
                                            <button class="button button-small {{ $currentVoteId === $nomination->id ? 'button-secondary' : 'button-primary' }}">
                                                {{ $currentVoteId === $nomination->id ? 'Jouw stem' : ($currentVoteId ? 'Stem aanpassen' : 'Stemmen') }}
                                            </button>
                                        </form>
                                    @endif
                                @else
                                    @if($activeVoteRound)<a class="button button-primary button-small" href="{{ route('discord.login') }}">Login om te stemmen</a>@endif
                                @endauth
                            </footer>
                        </article>
                    @empty
                        <div class="award-empty">Nog geen goedgekeurde mini-nominaties in deze categorie.</div>
                    @endforelse
                </div>

                @if($edition->status === 'nominations')
                    @auth
                        <details class="award-nominate-panel">
                            <summary>Iemand nomineren voor {{ $category->name }}</summary>
                            <form method="post" action="{{ route('awards.nominate', $category) }}">
                                @csrf
                                <div><label>Naam kandidaat<input name="nominee_name" required maxlength="100"></label><label>Discord ID <small>optioneel</small><input name="nominee_discord_id" maxlength="30"></label></div>
                                <label>Waarom verdient deze kandidaat dit?<textarea name="motivation" rows="4" minlength="40" maxlength="2000" required></textarea></label>
                                <button class="button button-primary">Mini-nominatie insturen</button>
                            </form>
                        </details>
                    @else
                        <a class="award-login-callout" href="{{ route('discord.login') }}">Log in met Discord om te nomineren →</a>
                    @endauth
                @endif
            </section>
        @else
            <div class="award-empty">Er zijn nog geen actieve mini-categorieën.</div>
        @endif
    @endif
</section>

@push('scripts')
<script>
    document.querySelectorAll('[data-category-select]').forEach((select) => {
        select.addEventListener('change', () => select.form.submit());
    });
    document.querySelectorAll('[data-countdown]').forEach((countdown) => {
        const deadline = new Date(countdown.dataset.countdown).getTime();
        const update = () => {
            const seconds = Math.floor(Math.max(0, deadline - Date.now()) / 1000);
            countdown.querySelector('[data-days]').textContent = String(Math.floor(seconds / 86400)).padStart(2, '0');
            countdown.querySelector('[data-hours]').textContent = String(Math.floor((seconds % 86400) / 3600)).padStart(2, '0');
            countdown.querySelector('[data-minutes]').textContent = String(Math.floor((seconds % 3600) / 60)).padStart(2, '0');
            countdown.querySelector('[data-seconds]').textContent = String(seconds % 60).padStart(2, '0');
        };
        update();
        window.setInterval(update, 1000);
    });
</script>
@endpush
@endsection
