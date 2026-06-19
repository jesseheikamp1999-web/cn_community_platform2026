@extends('layouts.app')

@php
    $titles = [
        'awards' => ['CN Awards', 'Erken de mensen die onze community bijzonder maken. Nomineer, stem en beleef de finale.'],
        'mini-awards' => ['Mini Awards', 'Kleine momenten, grote waardering. Bekijk actuele rondes en het volledige archief.'],
        'nieuws' => ['Nieuws', 'Verhalen, updates en ontwikkelingen uit de hele CN Community.'],
        'partners' => ['Onze partners', 'Organisaties die samen met ons waarde toevoegen aan de community.'],
        'staff' => ['Het CN team', 'De mensen die elke dag klaarstaan voor leden, partners en elkaar.'],
        'contact' => ['Neem contact op', 'Een vraag, idee of iets dat je met ons wilt delen? We luisteren.'],
        'solliciteren' => ['Bouw met ons mee', 'Ontwikkel jezelf, help anderen en maak onderdeel uit van het CN team.'],
        'partner-worden' => ['Partner worden', 'Werk duurzaam met CN Community samen en bereik een betrokken doelgroep.'],
    ];
    [$title, $subtitle] = $titles[$page];
@endphp

@section('title', $title.' - CN Community')

@section('content')
<section class="page-hero">
    <span class="eyebrow"><i></i> CN COMMUNITY</span>
    <h1>{{ $title }}</h1>
    <p>{{ $subtitle }}</p>
</section>
<section class="page-content">
    @if(in_array($page, ['contact', 'solliciteren', 'partner-worden'], true))
        <div class="install-card">
            <h2>{{ $title }}</h2>
            @php($formType = $page === 'solliciteren' ? 'application' : ($page === 'partner-worden' ? 'partnership' : 'contact'))
            <form method="post" action="{{ route('forms.store', $formType) }}">
                @csrf
                <div class="form-row"><label>Naam</label><input name="name" required></div>
                <div class="form-row"><label>E-mailadres</label><input type="email" name="email" required></div>
                <div class="form-row"><label>{{ $page === 'solliciteren' ? 'Gewenste rol' : 'Onderwerp' }}</label><input name="subject"></div>
                @if($page === 'solliciteren')
                    <div class="form-row"><label>Leeftijd</label><input type="number" name="age" min="16" max="99" required></div>
                    <div class="form-row"><label>Relevante ervaring</label><textarea name="experience" rows="4" required placeholder="Vertel over communitywerk, moderatie, support of andere relevante ervaring."></textarea></div>
                    <div class="form-row"><label>Beschikbaarheid</label><textarea name="availability" rows="3" required placeholder="Op welke dagen en momenten ben je meestal beschikbaar?"></textarea></div>
                @endif
                <div class="form-row"><label>{{ $page === 'solliciteren' ? 'Motivatie' : 'Vertel ons meer' }}</label><textarea name="message" rows="6" required></textarea></div>
                <button class="button button-primary" type="submit">Versturen <span>&rarr;</span></button>
            </form>
        </div>
    @else
        @if($page === 'staff')
            <div class="staff-filter-bar">
                @foreach($staffFilters ?? [] as $filterKey => $filterLabel)
                    <a class="{{ request('team', 'all') === $filterKey ? 'active' : '' }}" href="{{ $filterKey === 'all' ? route('staff') : route('staff', ['team' => $filterKey]) }}">{{ $filterLabel }}</a>
                @endforeach
            </div>
            @if($teamMemberOfMonth && request('team', 'all') === 'all')
                <article class="staff-month-card">
                    <div class="staff-public-avatar">@include('components.user-avatar', ['user' => $teamMemberOfMonth])</div>
                    <div>
                        <span class="eyebrow"><i></i> TEAM VAN DE MAAND</span>
                        <h2>{{ $teamMemberOfMonth->name }}</h2>
                        <p>{{ $teamMemberOfMonth->staffProfile?->bio ?: $teamMemberOfMonth->profile_bio ?: 'Een zichtbaar voorbeeld voor de community.' }}</p>
                    </div>
                    <span class="staff-role-badge role-{{ $teamMemberOfMonth->role->value }}">{{ $teamMemberOfMonth->publicPosition() }}</span>
                </article>
            @endif
        @endif
        <div class="content-grid {{ $page === 'staff' ? 'staff-public-grid' : '' }}">
            @forelse($items as $item)
                @if($page === 'staff')
                    @php($statusKey = $item->staffStatusKey())
                    @php($specialties = collect($item->staffProfile?->specialties ?? [])->filter())
                    <details class="content-card staff-public-card">
                        <summary>
                            <div class="staff-public-head">
                                <div class="staff-public-avatar">@include('components.user-avatar', ['user' => $item])</div>
                                <div>
                                    <span class="eyebrow"><i></i> CN STAFF</span>
                                    <h3>{{ $item->name }}</h3>
                                    <div class="staff-public-role">{{ $item->publicPosition() }}</div>
                                </div>
                            </div>
                            <span class="staff-role-badge role-{{ $item->role->value }}">{{ $item->role->label() }}</span>
                        </summary>
                        <p>{{ $item->staffProfile?->bio ?: $item->profile_bio ?: 'Staat klaar voor de community en helpt CN verder groeien.' }}</p>
                        @if($specialties->isNotEmpty())
                            <div class="staff-specialties">@foreach($specialties as $specialty)<span>{{ $specialty }}</span>@endforeach</div>
                        @endif
                        <div class="staff-mini-profile">
                            <div>
                                <strong>Functie</strong>
                                <span>{{ $item->publicPosition() }}</span>
                            </div>
                            <div>
                                <strong>Discord</strong>
                                @if($item->staffProfile?->discord_url)<a href="{{ $item->staffProfile->discord_url }}" target="_blank" rel="noopener noreferrer">Open profiel</a>@else<span>{{ '@'.($item->discord_username ?: $item->name) }}</span>@endif
                            </div>
                        </div>
                        <span class="availability staff-status status-{{ $statusKey }}" title="{{ $item->staffStatusLabel() }}">{{ $item->staffStatusLabel() }}</span>
                    </details>
                @elseif($page === 'partners')
                    <article class="content-card partner-public-card">
                        @if($item->logo_url)
                            <img class="content-partner-logo" src="{{ $item->logo_url }}" alt="">
                        @else
                            <div class="content-partner-mark">{{ strtoupper(substr($item->name, 0, 1)) }}</div>
                        @endif
                        <span class="eyebrow"><i></i> CN PARTNER</span>
                        <h3>{{ $item->name }}</h3>
                        <p>{{ $item->description ?: 'Samenwerking binnen de CN Community.' }}</p>
                        <div class="partner-public-score"><span>#{{ $item->position ?? $loop->iteration }}</span><b>{{ $item->score ?? 0 }}/100</b><em>{{ ucfirst($item->category ?? 'partner') }}</em></div>
                        @if($item->destination_url)<a class="text-link" href="{{ $item->destination_url }}" target="_blank" rel="noopener noreferrer">Bezoek {{ $item->name }} &rarr;</a>@endif
                    </article>
                @else
                    <article class="content-card">
                        <span class="eyebrow"><i></i> {{ strtoupper($item->type ?? 'CN') }}</span>
                        <h3>{{ $item->title ?? $item->name }}</h3>
                        <p>{{ $item->excerpt ?? $item->description ?? 'Samen bouwen we aan een sterke en betrokken community.' }}</p>
                    </article>
                @endif
            @empty
                <div class="empty-state"><h3>Binnenkort meer</h3><p>Deze pagina wordt op dit moment gevuld.</p></div>
            @endforelse
        </div>
    @endif
</section>
@endsection
