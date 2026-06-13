@extends('layouts.app')

@section('title', ($edition?->name ?? 'CN Awards').' - CN Community')

@section('content')
<section class="awards-page-hero">
    <div>
        <span class="eyebrow light"><i></i> CN COMMUNITY AWARDS</span>
        <h1>{{ $edition?->name ?? 'CN Awards' }}</h1>
        <p>Een professionele community-awardshow met nominaties, publieksstemmen, juryrapporten, finalisten en live reveal.</p>
    </div>
    @if($edition)
        <div class="awards-phase"><span>HUIDIGE FASE</span><strong>{{ $phaseStatus[0] ?? ucfirst($edition->status) }}</strong><small>{{ $edition->categories->count() }} categorieën</small></div>
    @endif
</section>

<section class="awards-page">
    @if($errors->any())<div class="module-alert error">{{ $errors->first() }}</div>@endif
    @if(session('success'))<div class="module-alert success">{{ session('success') }}</div>@endif
    @if(!$edition)
        <div class="empty-state"><h3>Nog geen Award-editie</h3><p>De volgende editie wordt voorbereid.</p></div>
    @else
        <section class="awards-deadline-card">
            <div>
                <span class="eyebrow">Awards planning</span>
                <h2>{{ $phaseStatus[0] ?? 'CN Awards 2026' }}</h2>
                @if($phaseDeadline)
                    <p>{{ $phaseStatus[1] ?? 'Volgende fase' }} op {{ $phaseDeadline->translatedFormat('j F Y \o\m H:i') }}.</p>
                @else
                    <p>De planning voor de volgende fase wordt binnenkort bekendgemaakt.</p>
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

            <div class="awards-next-phase">
                @if($nextRound)
                    <small>Daarna</small>
                    <strong>{{ ucfirst(str_replace('_', ' ', $nextRound->type)) }}</strong>
                    <span>{{ $nextRound->starts_at->translatedFormat('j F Y') }}</span>
                @elseif($edition->finale_at)
                    <small>Finale</small>
                    <strong>{{ $edition->finale_at->translatedFormat('j F Y') }}</strong>
                    <span>{{ $edition->finale_at->format('H:i') }} uur</span>
                @endif
            </div>
        </section>

        <div class="award-show-timeline">
            @foreach(['nominations'=>'Nominaties','voting'=>'Stemmen','jury'=>'Jury','finale'=>'Finale','published'=>'Hall of Fame'] as $phase => $label)
                @if($phase === 'finale')<a class="{{ $edition->status === $phase ? 'active' : '' }}" href="{{ route('awards.finale') }}">{{ $label }}</a>
                @elseif($phase === 'published')<a class="{{ $edition->status === $phase ? 'active' : '' }}" href="{{ route('awards.hall') }}">{{ $label }}</a>
                @else<span class="{{ $edition->status === $phase ? 'active' : '' }}">{{ $label }}</span>@endif
            @endforeach
        </div>

        @if($activeVoteRound)
            <section class="awards-voting-ready">
                <div><span>STEMRONDE ACTIEF</span><h2>Jouw stem telt mee</h2><p>Kies per categorie één kandidaat. Je kunt je keuze aanpassen zolang de ronde open is; iedere wijziging blijft veilig in de stemhistorie staan.</p></div>
                <strong>Open tot {{ $activeVoteRound->ends_at->translatedFormat('d F Y \o\m H:i') }}</strong>
            </section>
        @endif

        @if($selectedCategory)
            <section class="award-category-picker">
                <div>
                    <span>CATEGORIE</span>
                    <strong>Kies welke nominaties je wilt bekijken</strong>
                </div>
                <form method="get" action="{{ route('awards') }}">
                    <label for="award-category-select" class="sr-only">Awards-categorie</label>
                    <select id="award-category-select" name="categorie" data-category-select>
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
            <section class="award-category-section compact" id="categorie-{{ $category->id }}">
                <header>
                    <div><span>{{ str_pad((string) $categoryNumber, 2, '0', STR_PAD_LEFT) }}</span><h2>{{ $category->name }}</h2><p>{{ $category->description }}</p></div>
                    <strong>{{ $category->nominations->count() }} kandidaten</strong>
                </header>

                <div class="award-nominee-grid">
                    @php($voteTotal = max(1, $category->nominations->sum('votes_count')))
                    @forelse($category->nominations as $nomination)
                        @php($votePercent = round($nomination->votes_count / $voteTotal * 100, 1))
                        <article class="award-nominee-card {{ $nomination->status === 'finalist' ? 'finalist' : '' }} {{ $nomination->status === 'winner' ? 'winner' : '' }}">
                            <div class="award-nominee-mark">{{ strtoupper(substr($nomination->nominee_name, 0, 2)) }}</div>
                            <div>
                                <span>{{ $nomination->status === 'winner' ? 'WINNAAR' : ($nomination->status === 'finalist' ? 'FINALIST' : 'GENOMINEERD') }}</span>
                                <h3>{{ $nomination->nominee_name }}</h3>
                                <p>{{ \Illuminate\Support\Str::limit($nomination->motivation, 180) }}</p>
                                <a class="award-profile-link" href="{{ route('awards.nomination', $nomination) }}">Bekijk profiel &rarr;</a>
                            </div>
                            <div class="award-score-strip"><i style="width:{{ $votePercent }}%"></i></div>
                            <div class="award-score-meta"><b>{{ $votePercent }}%</b><span>community share</span><em>⭐ {{ round($nomination->reputation_score ?? 0) }} reputatie</em></div>
                            <footer>
                                <small>{{ $nomination->votes_count }} geldige stemmen</small>
                                @auth
                                    @if($activeVoteRound && in_array($nomination->status, ['approved', 'finalist'], true))
                                        <form method="post" action="{{ route('awards.vote', $nomination) }}">
                                            @csrf
                                            <input type="hidden" name="round_id" value="{{ $activeVoteRound->id }}">
                                            <input type="hidden" name="categorie" value="{{ $category->slug }}">
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
                        <div class="award-empty">Nog geen goedgekeurde nominaties in deze categorie.</div>
                    @endforelse
                </div>

                @if($edition->status === 'nominations')
                    @auth
                        <details class="award-nominate-panel">
                            <summary>Iemand nomineren voor {{ $category->name }}</summary>
                            <form method="post" action="{{ route('awards.nominate', $category) }}">@csrf
                                <div><label>Naam kandidaat<input name="nominee_name" required maxlength="100"></label><label>Discord ID <small>optioneel</small><input name="nominee_discord_id" maxlength="30"></label></div>
                                <label>Motivatie<textarea name="motivation" rows="5" minlength="40" maxlength="2000" required placeholder="Vertel concreet waarom deze kandidaat erkenning verdient."></textarea></label>
                                <div><label>Bewijslink <small>optioneel</small><input name="evidence_url" type="url" maxlength="255" placeholder="https://..."></label><label>Bewijs / historie <small>optioneel</small><input name="evidence_text" maxlength="2000" placeholder="Projectlink, Discord-context of korte historie"></label></div>
                                <button class="button button-primary">Nominatie insturen</button>
                            </form>
                        </details>
                    @else
                        <a class="award-login-callout" href="{{ route('discord.login') }}">Log in met Discord om iemand te nomineren &rarr;</a>
                    @endauth
                @endif
            </section>
        @else
            <div class="award-empty">Er zijn nog geen actieve categorieën voor deze editie.</div>
        @endif
    @endif
</section>
@push('scripts')
<script>
    document.querySelectorAll('[data-countdown]').forEach((countdown) => {
        const deadline = new Date(countdown.dataset.countdown).getTime();
        const fields = {
            days: countdown.querySelector('[data-days]'),
            hours: countdown.querySelector('[data-hours]'),
            minutes: countdown.querySelector('[data-minutes]'),
            seconds: countdown.querySelector('[data-seconds]'),
        };
        const updateCountdown = () => {
            const remaining = Math.max(0, deadline - Date.now());
            const totalSeconds = Math.floor(remaining / 1000);
            fields.days.textContent = String(Math.floor(totalSeconds / 86400)).padStart(2, '0');
            fields.hours.textContent = String(Math.floor((totalSeconds % 86400) / 3600)).padStart(2, '0');
            fields.minutes.textContent = String(Math.floor((totalSeconds % 3600) / 60)).padStart(2, '0');
            fields.seconds.textContent = String(totalSeconds % 60).padStart(2, '0');
        };
        updateCountdown();
        window.setInterval(updateCountdown, 1000);
    });

    document.querySelectorAll('[data-category-select]').forEach((select) => {
        select.addEventListener('change', () => select.form.submit());
    });
</script>
@endpush
@endsection
