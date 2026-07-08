@extends('layouts.app')

@php
    $meta = $content ?? [];
    $title = $meta['title'] ?? ucfirst($page);
    $description = $meta['description'] ?? __('public.brand.description');
    $eyebrow = $meta['eyebrow'] ?? strtoupper($page);
    $locale = app()->getLocale();
    $localizedRoute = fn (string $name, array $parameters = []) => route($name, ['locale' => $locale] + $parameters);
@endphp

@section('title', $title.' - '.__('public.brand.name'))
@section('description', $description)

@section('content')
<section class="page-hero connect-page-hero">
    <span class="eyebrow"><i></i> {{ $eyebrow }}</span>
    <h1>{{ $title }}</h1>
    <p>{{ $description }}</p>
</section>

<section class="page-content connect-page-content">
    @if(! empty($meta['bullets']))
        <section class="connect-block">
            <div class="feature-grid">
                @foreach($meta['bullets'] as $bullet)
                    <article class="feature-card compact">
                        <div class="feature-icon">&#10022;</div>
                        <h3>{{ $bullet }}</h3>
                        <p>{{ __('public.brand.name') }} builds this service inside a scalable, premium and future-ready delivery model.</p>
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    @if($page === 'communities')
        <section class="connect-block">
            <div class="section-top">
                <div><span class="eyebrow"><i></i> COMMUNITIES</span><h2>Existing CN community power,<br><em>refreshed under Connect Next.</em></h2></div>
                <a class="text-link" href="{{ $localizedRoute('staff') }}">Staff page &rarr;</a>
            </div>
            <div class="content-grid">
                <article class="content-card">
                    <h3>CN Community</h3>
                    <p>The current community ecosystem stays active, but receives a more premium international identity and clearer positioning inside Connect Next.</p>
                </article>
                <article class="content-card">
                    <h3>Discord &amp; Events</h3>
                    <p>From moderation and events to partnerships and growth campaigns, the community layer remains a strategic product instead of an afterthought.</p>
                </article>
                <article class="content-card">
                    <h3>Awards as flagship</h3>
                    <p>The awards platform remains fully functional and becomes one of the strongest public pillars of the entire brand.</p>
                </article>
            </div>
        </section>
        @if(! empty($staff))
            <section class="connect-block">
                <div class="section-top">
                    <div><span class="eyebrow"><i></i> STAFF</span><h2>People building the experience.</h2></div>
                    <a class="text-link" href="{{ $localizedRoute('staff') }}">Open full staff page &rarr;</a>
                </div>
                <div class="people-grid">
                    @foreach($staff as $member)
                        <article>
                            <div class="portrait" @if($member->staffProfile?->cover_image || $member->discord_avatar_url) style="background-image:url('{{ $member->staffProfile?->cover_image ?: $member->discord_avatar_url }}'); background-size:cover; background-position:center;" @endif>
                                @if(! $member->staffProfile?->cover_image && ! $member->discord_avatar_url)
                                    {{ strtoupper(substr($member->name, 0, 2)) }}
                                @endif
                            </div>
                            <h3>{{ $member->name }}</h3>
                            <p>{{ $member->publicPosition() }}</p>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif
    @endif

    @if(in_array($page, ['projects', 'partners'], true) && isset($items))
        <section class="connect-block">
            <div class="section-top">
                <div><span class="eyebrow"><i></i> PROJECTS</span><h2>Partners, products and communities<br><em>that live inside the network.</em></h2></div>
            </div>
            <div class="content-grid">
                @forelse($items as $item)
                    <a class="content-card partner-card-link" href="{{ $item->website_url ?: $item->discord_invite ?: '#' }}" target="_blank" rel="noopener">
                        <h3>{{ $item->name }}</h3>
                        <p>{{ $item->description ?: 'Active within the Connect Next ecosystem.' }}</p>
                    </a>
                @empty
                    <div class="empty-state"><h3>No projects published yet</h3><p>New partners and products will appear here.</p></div>
                @endforelse
            </div>
        </section>
    @endif

    @if($page === 'about' && isset($staff))
        <section class="connect-block">
            <div class="section-top">
                <div><span class="eyebrow"><i></i> PEOPLE</span><h2>Technology becomes stronger<br><em>when real people stay visible.</em></h2></div>
            </div>
            <div class="people-grid">
                @foreach($staff as $member)
                    <article>
                        <div class="portrait" @if($member->staffProfile?->cover_image || $member->discord_avatar_url) style="background-image:url('{{ $member->staffProfile?->cover_image ?: $member->discord_avatar_url }}'); background-size:cover; background-position:center;" @endif>
                            @if(! $member->staffProfile?->cover_image && ! $member->discord_avatar_url)
                                {{ strtoupper(substr($member->name, 0, 2)) }}
                            @endif
                        </div>
                        <h3>{{ $member->name }}</h3>
                        <p>{{ $member->publicPosition() }}</p>
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    @if($page === 'staff' && isset($items))
        <section class="connect-block">
            <div class="staff-filter-row">
                @foreach($staffFilters as $filterKey => $filterLabel)
                    <a class="{{ request('team', 'all') === $filterKey ? 'active' : '' }}" href="{{ $filterKey === 'all' ? $localizedRoute('staff') : $localizedRoute('staff', ['team' => $filterKey]) }}">{{ $filterLabel }}</a>
                @endforeach
            </div>
            <div class="content-grid staff-grid">
                @forelse($items as $member)
                    <article class="content-card staff-public-card">
                        <div class="staff-public-head">
                            <div class="staff-public-avatar">
                                @if($member->staffProfile?->cover_image || $member->discord_avatar_url)
                                    <img src="{{ $member->staffProfile?->cover_image ?: $member->discord_avatar_url }}" alt="{{ $member->name }}">
                                @else
                                    <span>{{ strtoupper(substr($member->name, 0, 2)) }}</span>
                                @endif
                            </div>
                            <div>
                                <small class="dashboard-kicker">CONNECT NEXT TEAM</small>
                                <h3>{{ $member->name }}</h3>
                                <p>{{ $member->publicPosition() }}</p>
                            </div>
                        </div>
                        <p>{{ $member->staffProfile?->bio ?: 'Building better experiences, stronger communities and steady progress inside Connect Next.' }}</p>
                        <span class="status {{ $member->is_currently_absent ? 'status-rejected' : 'status-approved' }}">{{ $member->is_currently_absent ? 'Afwezig' : 'Beschikbaar' }}</span>
                    </article>
                @empty
                    <div class="empty-state"><h3>No staff available</h3><p>The team overview will appear here once profiles are published.</p></div>
                @endforelse
            </div>
        </section>
    @endif

    @if(in_array($page, ['contact', 'apply', 'partner'], true))
        <section class="connect-block">
            <div class="module-card">
                <div class="module-card-heading">
                    <div><span>{{ strtoupper($eyebrow) }}</span><h2>{{ $title }}</h2></div>
                </div>
                <form class="module-form" method="post" action="{{ route('forms.store', $meta['form_type'] ?? 'contact') }}">
                    @csrf
                    <div class="module-form-grid">
                        <label>Name<input type="text" name="name" required></label>
                        <label>Email<input type="email" name="email" required></label>
                    </div>
                    <label>Subject<input type="text" name="subject" required></label>
                    <label>Message<textarea name="message" rows="6" required></textarea></label>
                    <button class="button button-primary">{{ app()->getLocale() === 'en' ? 'Send request' : 'Verstuur aanvraag' }}</button>
                </form>
            </div>
        </section>
    @endif
</section>
@endsection
