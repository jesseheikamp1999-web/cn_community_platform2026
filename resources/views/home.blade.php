@extends('layouts.app')

@section('title', 'CN Community Platform 2026 — Samen zijn we één')

@section('content')
<section class="home-hero">
    <div class="home-hero-inner">
        <div class="home-product-stage">
            <div class="stage-orbit orbit-a"></div><div class="stage-orbit orbit-b"></div>
            <div class="product-window">
                <div class="product-window-side">
                    <span class="brand-mark small">CN</span>
                    <i class="active"></i><i></i><i></i><i></i><i></i>
                </div>
                <div class="product-window-main">
                    <div class="window-top"><span></span><div><i></i><i></i><b>J</b></div></div>
                    <div class="window-greeting"><small>GOEDEMIDDAG, JESSE</small><strong>Klaar om verder te groeien?</strong><p>Dit gebeurt er vandaag binnen jouw CN.</p></div>
                    <div class="window-metrics"><article><small>XP TOTAAL</small><strong>2.840</strong><em>+120 deze week</em></article><article><small>RANKING</small><strong>#24</strong><em>Top 2%</em></article><article><small>BADGES</small><strong>12</strong><em>2 nieuw</em></article></div>
                    <div class="window-content"><article><small>ACTIEVE LEERROUTE</small><strong>Moderator Academy</strong><div><i style="width:68%"></i></div><span>68% voltooid</span></article><article><small>VOLGENDE EVENT</small><strong>Awards Finale</strong><span>14 juni · 20:00</span></article></div>
                </div>
            </div>
            <div class="stage-card stage-award"><span>◆</span><div><small>CN AWARDS 2026</small><strong>Nominaties geopend</strong></div></div>
            <div class="stage-card stage-community"><i></i><div><small>COMMUNITY</small><strong>{{ number_format($stats['members']) }} leden actief</strong></div></div>
        </div>
        <div class="home-hero-copy">
            <span class="eyebrow"><i></i> DE COMMUNITY, OPNIEUW UITGEVONDEN</span>
            <h1>Meer dan een server.<br><em>Eén community.</em></h1>
            <p>CN Community brengt Awards, ontwikkeling, samenwerking en verhalen samen in één professioneel platform. Discord is waar we praten. Hier bouwen we verder.</p>
            <div class="hero-actions">
                @auth
                    <a class="button button-primary" href="{{ route('dashboard') }}">Open MijnCN <span>→</span></a>
                @else
                    <a class="button button-primary" href="{{ route('discord.login') }}">Word onderdeel van CN <span>→</span></a>
                @endauth
                <a class="text-link" href="#ontdek">Ontdek het platform <span>↓</span></a>
            </div>
            <div class="hero-proof-row">
                <div class="avatar-stack"><span>J</span><span>M</span><span>S</span><span>+{{ max(0, $stats['members'] - 3) }}</span></div>
                <div><strong>{{ number_format($stats['members']) }} leden</strong><small>bouwen samen aan CN</small></div>
                <i></i>
                <div><strong>{{ number_format($stats['votes']) }} stemmen</strong><small>veilig uitgebracht</small></div>
            </div>
        </div>
    </div>
</section>

@if($partners->isNotEmpty())
<section class="partner-showcase">
    <div class="partner-showcase-copy">
        <span class="eyebrow"><i></i> ONZE PARTNERS</span>
        <strong>Samen maken we meer mogelijk.</strong>
    </div>
    <div class="partner-showcase-list ranked">
        @foreach($partners as $partner)
            @if($partner->destination_url)
                <a href="{{ $partner->destination_url }}" target="_blank" rel="noopener noreferrer" title="Bezoek {{ $partner->name }}">
                    <span class="partner-logo">
                        @if($partner->logo_url)
                            <img src="{{ $partner->logo_url }}" alt="">
                        @else
                            {{ strtoupper(substr($partner->name, 0, 1)) }}
                        @endif
                    </span>
                    <b>{{ $partner->name }}</b><small>#{{ $partner->position ?? $loop->iteration }} · {{ $partner->score ?? 0 }}</small><em>&nearr;</em>
                </a>
            @else
                <div class="partner-name">
                    <span class="partner-logo">
                        @if($partner->logo_url)
                            <img src="{{ $partner->logo_url }}" alt="">
                        @else
                            {{ strtoupper(substr($partner->name, 0, 1)) }}
                        @endif
                    </span>
                    <b>{{ $partner->name }}</b><small>#{{ $partner->position ?? $loop->iteration }} · {{ $partner->score ?? 0 }}</small>
                </div>
            @endif
        @endforeach
    </div>
    <a class="partner-showcase-all" href="{{ route('partners') }}">Alle partners &rarr;</a>
</section>
@endif

<section class="section intro" id="ontdek">
    <div class="section-label">01 / HET PLATFORM</div>
    <div class="intro-heading"><span class="eyebrow"><i></i> ALLES OP ÉÉN PLEK</span><h2>Gebouwd voor de community.<br><em>Gedreven door mensen.</em></h2></div>
    <p class="lead">Geen losse tools of vergeten kanalen. Eén plek voor erkenning, ontwikkeling en samenwerking.</p>
    <div class="feature-grid">
        <article class="feature-card featured"><span class="feature-number">01</span><div class="feature-icon">◆</div><h3>CN Awards</h3><p>Nomineer, stem en vier de mensen die onze community bijzonder maken.</p><a href="{{ route('awards') }}">Ontdek de Awards →</a><div class="card-art award-art"><span>CN</span><strong>AWARDS</strong><small>2026</small></div></article>
        <article class="feature-card"><span class="feature-number">02</span><div class="feature-icon">⌁</div><h3>Academy</h3><p>Ontwikkel je skills via leerpaden, opdrachten en certificaten.</p><a href="{{ route('dashboard') }}">Start met leren →</a><div class="path-art"><span></span><span></span><span></span><span></span></div></article>
        <article class="feature-card"><span class="feature-number">03</span><div class="feature-icon">✓</div><h3>Samenwerken</h3><p>Van ideeën naar resultaat met takenborden, teams en heldere voortgang.</p><a href="{{ route('dashboard') }}">Bekijk MijnCN →</a><div class="task-art"><i></i><i></i><i></i></div></article>
    </div>
</section>

<section class="awards-banner">
    <div class="awards-copy"><span class="eyebrow light"><i></i> CN AWARDS 2026</span><h2>Wie verdient<br><em>het podium?</em></h2><p>De nominaties zijn geopend. Zet iemand in het licht die impact maakt, anderen helpt of de community elke dag een beetje beter maakt.</p><div class="deadline"><span>12</span><small>DAGEN</small><b>:</b><span>08</span><small>UREN</small><b>:</b><span>34</span><small>MIN</small></div><a class="button button-light" href="{{ route('awards') }}">Nomineer iemand <span>→</span></a></div>
    <div class="award-trophy"><div class="trophy-glow"></div><div class="trophy"><span>CN</span><strong>AWARDS</strong><small>2026</small></div><p><i></i> Nominatieronde actief</p></div>
</section>

<section class="section news-section">
    <div class="section-top"><div><span class="eyebrow"><i></i> UIT DE COMMUNITY</span><h2>Verhalen die<br><em>het delen waard zijn.</em></h2></div><a class="text-link" href="{{ route('nieuws') }}">Bekijk al het nieuws →</a></div>
    <div class="news-grid">
        @forelse($news as $index => $item)
            <article class="news-card {{ $index === 0 ? 'large' : '' }}">
                <div class="news-image gradient-{{ $index + 1 }}" @if($item->cover_image) style="background-image:url('{{ $item->cover_image }}')" @endif><span>{{ data_get($item->meta, 'source', $item->type === 'event' ? 'EVENT' : 'CN NIEUWS') }}</span></div>
                <div><small>{{ optional($item->published_at)->translatedFormat('d M Y') }}</small><h3>{{ $item->title }}</h3><p>{{ $item->excerpt }}</p><a href="{{ route('news.show', $item) }}">Lees verder &rarr;</a></div>
            </article>
        @empty
            <article class="news-card large"><div class="news-image gradient-1"><span>NIEUWS</span></div><div><small>10 JUN 2026</small><h3>Een nieuw hoofdstuk voor CN Community</h3><p>Ontdek waarom we een zelfstandig platform bouwen en wat dit voor jou betekent.</p><a href="#">Lees het verhaal →</a></div></article>
            <article class="news-card"><div class="news-image gradient-2"><span>ACADEMY</span></div><div><small>08 JUN 2026</small><h3>De Moderator Academy is vernieuwd</h3><p>Nieuwe scenario's, mentorfeedback en een helder leerpad.</p><a href="#">Lees verder →</a></div></article>
            <article class="news-card"><div class="news-image gradient-3"><span>COMMUNITY</span></div><div><small>04 JUN 2026</small><h3>Maak kennis met onze partners</h3><p>Samen maken we meer mogelijk voor iedere member.</p><a href="#">Lees verder →</a></div></article>
        @endforelse
    </div>
</section>

<section class="stats-section">
    <span class="eyebrow light"><i></i> SAMEN IN CIJFERS</span><h2>Dit bouwen we.<br><em>Met elkaar.</em></h2>
    <div class="stats-grid"><div><strong>{{ number_format($stats['members']) }}+</strong><span>COMMUNITY LEDEN</span></div><div><strong>{{ number_format($stats['votes']) }}</strong><span>STEMMEN UITGEBRACHT</span></div><div><strong>{{ $stats['awards'] }}</strong><span>AWARDS UITGEREIKT</span></div><div><strong>{{ $stats['lessons'] }}</strong><span>LESSEN BESCHIKBAAR</span></div></div>
</section>

<section class="section people-section">
    <div class="section-top"><div><span class="eyebrow"><i></i> DE MENSEN ACHTER CN</span><h2>Hier voor jou.<br><em>Elke dag opnieuw.</em></h2></div><a class="text-link" href="{{ route('staff') }}">Ontmoet het hele team →</a></div>
    <div class="people-grid">
        @forelse($staff as $staffMember)
            @php($statusKey = $staffMember->staffStatusKey())
            <article>
                <div class="portrait">
                    @include('components.user-avatar', ['user' => $staffMember])
                </div>
                <h3>{{ $staffMember->name }}</h3>
                <p>{{ $staffMember->publicPosition() }}</p>
                <b class="staff-role-badge compact role-{{ $staffMember->role->value }}">{{ $staffMember->role->label() }}</b>
                <span class="{{ $statusKey === 'active' ? '' : 'unavailable' }}" title="{{ $staffMember->staffStatusLabel() }}"><i></i> {{ $staffMember->staffStatusLabel() }}</span>
            </article>
        @empty
            <div class="empty-state"><h3>Team wordt bijgewerkt</h3><p>De publieke staffprofielen verschijnen hier binnenkort.</p></div>
        @endforelse
    </div>
</section>

<section class="cta-section"><div><span class="eyebrow light"><i></i> JOUW PLEK IS HIER</span><h2>Klaar om onderdeel<br>te worden van <em>CN?</em></h2><p>Sluit je aan, ontdek wat er speelt en bouw mee aan de community.</p><a class="button button-light" href="{{ route('discord.login') }}">Inloggen met Discord <span>→</span></a></div><div class="cta-mark">CN</div></section>
@endsection
