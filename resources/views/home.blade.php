@extends('layouts.app')

@section('title', __('public.brand.name').' — '.__('public.brand.tagline'))
@section('description', __('public.brand.description'))

@section('content')
<section class="home-hero connect-home-hero">
    <div class="home-hero-inner">
        <div class="home-product-stage">
            <div class="stage-orbit orbit-a"></div>
            <div class="stage-orbit orbit-b"></div>
            <div class="product-window">
                <div class="product-window-side">
                    <span class="brand-mark">CN</span>
                    <i class="active"></i>
                    <i></i>
                    <i></i>
                    <i></i>
                </div>
                <div class="product-window-main">
                    <div class="window-top"><span></span><div><i></i><i></i><b>JH</b></div></div>
                    <div class="window-greeting">
                        <small>{{ __('public.hero.eyebrow') }}</small>
                        <strong>{{ __('public.brand.name') }}</strong>
                        <p>{{ __('public.brand.tagline') }}</p>
                    </div>
                    <div class="window-metrics">
                        <article><small>AI</small><strong>24/7</strong><em>Automation ready</em></article>
                        <article><small>SOFTWARE</small><strong>{{ $stats['projects'] }}</strong><em>Active projects</em></article>
                        <article><small>AWARDS</small><strong>{{ $stats['awards'] }}</strong><em>Published winners</em></article>
                    </div>
                    <div class="window-content">
                        <article>
                            <small>CN PULSE</small>
                            <strong>Live momentum across communities, products and events.</strong>
                            <div><i style="width:82%"></i></div>
                            <span>Cross-division visibility</span>
                        </article>
                        <article>
                            <small>COMMUNITY</small>
                            <strong>{{ number_format($stats['members'], 0, ',', '.') }} members connected</strong>
                            <div><i style="width:71%"></i></div>
                            <span>Growing ecosystem</span>
                        </article>
                    </div>
                </div>
            </div>
            <div class="stage-card stage-award"><span>◆</span><div><small>FLAGSHIP</small><strong>Connect Next Awards</strong></div></div>
            <div class="stage-card stage-community"><i></i><div><small>COMMUNITIES</small><strong>Discord, events & partnerships</strong></div></div>
        </div>

        <div class="home-hero-copy">
            <span class="eyebrow"><i></i> {{ __('public.hero.eyebrow') }}</span>
            <h1>{{ __('public.hero.title') }} <em>{{ __('public.hero.highlight') }}</em></h1>
            <p>{{ __('public.hero.description') }}</p>
            <div class="hero-actions">
                <a class="button button-primary" href="{{ route('contact') }}">{{ __('public.hero.primary') }}</a>
                <a class="text-link" href="#divisions">{{ __('public.hero.secondary') }} <span>↓</span></a>
            </div>
            <div class="hero-proof-row">
                <div class="avatar-stack"><span>AI</span><span>DEV</span><span>COM</span><span>AW</span></div>
                <i></i>
                <div>
                    <strong>{{ __('public.hero.proof_title') }}</strong>
                    <small>{{ __('public.hero.proof_text') }}</small>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section connect-section" id="divisions">
    <div class="section-top">
        <div>
            <span class="eyebrow"><i></i> {{ __('public.sections.divisions') }}</span>
            <h2>Connect Next,<br><em>organized for scale.</em></h2>
        </div>
    </div>
    <div class="feature-grid">
        @foreach($divisionCards as $card)
            <article class="feature-card {{ str_contains($card['eyebrow'], 'Awards') ? 'featured' : '' }}">
                <span class="feature-number">{{ str_pad((string) ($loop->iteration), 2, '0', STR_PAD_LEFT) }}</span>
                <div class="feature-icon">{{ $loop->iteration === 1 ? '✦' : ($loop->iteration === 2 ? '▣' : ($loop->iteration === 3 ? '◎' : '◆')) }}</div>
                <small class="dashboard-kicker">{{ $card['eyebrow'] }}</small>
                <h3>{{ $card['title'] }}</h3>
                <p>{{ $card['description'] }}</p>
                <a href="{{ $card['url'] }}">Open division →</a>
            </article>
        @endforeach
    </div>
</section>

<section class="awards-banner connect-awards-banner">
    <div class="awards-copy">
        <span class="eyebrow light"><i></i> CONNECT NEXT AWARDS</span>
        <h2>Recognition,<br><em>rebuilt as an event.</em></h2>
        <p>The awards platform stays fully alive inside Connect Next and grows into one of the flagship experiences across the company.</p>
        <div class="deadline">
            <span>{{ number_format($stats['votes'], 0, ',', '.') }}</span><small>VALID VOTES</small><b>:</b>
            <span>{{ number_format($stats['awards'], 0, ',', '.') }}</span><small>WINNERS</small><b>:</b>
            <span>{{ count($winners) }}</span><small>SPOTLIGHTS</small>
        </div>
        <a class="button button-light" href="{{ route('awards') }}">Open Awards <span>→</span></a>
    </div>
    <div class="award-trophy">
        <div class="trophy-glow"></div>
        <div class="trophy"><span>CN</span><strong>AWARDS</strong><small>EVENT SERIES</small></div>
        <p><i></i> Premium nominations, voting and final reveals</p>
    </div>
</section>

<section class="section news-section connect-section">
    <div class="section-top">
        <div>
            <span class="eyebrow"><i></i> {{ __('public.sections.stories') }}</span>
            <h2>Stories worth<br><em>sharing forward.</em></h2>
        </div>
        <a class="text-link" href="{{ route('nieuws') }}">{{ __('public.nav.news') }} →</a>
    </div>
    <div class="news-grid">
        @forelse($news as $article)
            <a class="news-card {{ $loop->first ? 'large' : '' }}" href="{{ route('news.show', $article) }}">
                <div class="news-image gradient-{{ ($loop->index % 3) + 1 }}" @if($article->cover_image) style="background-image:url('{{ $article->cover_image }}'); background-size:cover; background-position:center;" @endif>
                    <span>{{ strtoupper(data_get($article->meta, 'source', 'CONNECT NEXT')) }}</span>
                </div>
                <div>
                    <small>{{ $article->published_at?->translatedFormat('d M Y') }}</small>
                    <h3>{{ $article->title }}</h3>
                    <p>{{ $article->excerpt }}</p>
                    <strong>Read more →</strong>
                </div>
            </a>
        @empty
            <div class="empty-state"><h3>No stories published yet</h3><p>Fresh updates from Connect Next will appear here.</p></div>
        @endforelse
    </div>
</section>

<section class="stats-section">
    <div class="section-top">
        <div>
            <span class="eyebrow light"><i></i> {{ __('public.sections.stats') }}</span>
            <h2>Technology,<br><em>community and proof.</em></h2>
        </div>
    </div>
    <div class="stats-grid">
        <div><strong>{{ number_format($stats['members'], 0, ',', '.') }}</strong><span>CONNECTED MEMBERS</span></div>
        <div><strong>{{ number_format($stats['votes'], 0, ',', '.') }}</strong><span>VALID VOTES</span></div>
        <div><strong>{{ number_format($stats['awards'], 0, ',', '.') }}</strong><span>AWARDS PUBLISHED</span></div>
        <div><strong>{{ number_format($stats['projects'], 0, ',', '.') }}</strong><span>ACTIVE PROJECTS</span></div>
    </div>
</section>

<section class="section people-section connect-section">
    <div class="section-top">
        <div>
            <span class="eyebrow"><i></i> {{ __('public.sections.communities') }}</span>
            <h2>The people behind<br><em>the movement.</em></h2>
        </div>
        <a class="text-link" href="{{ route('staff') }}">Meet the team →</a>
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
                <span><i></i> {{ $member->is_currently_absent ? 'Unavailable' : 'Available' }}</span>
            </article>
        @endforeach
    </div>
</section>

<section class="section connect-section">
    <div class="section-top">
        <div>
            <span class="eyebrow"><i></i> {{ __('public.sections.testimonials') }}</span>
            <h2>Trusted by teams that<br><em>want to move forward.</em></h2>
        </div>
    </div>
    <div class="feature-grid">
        @foreach($testimonials as $testimonial)
            <article class="feature-card">
                <div class="feature-icon">“</div>
                <h3>{{ $testimonial['name'] }}</h3>
                <p>{{ $testimonial['quote'] }}</p>
                <a href="#">{{ $testimonial['role'] }}</a>
            </article>
        @endforeach
    </div>
</section>

<section class="cta-section">
    <div>
        <span class="eyebrow light"><i></i> {{ __('public.sections.cta') }}</span>
        <h2>Connect Next.<br><em>Built for what comes after today.</em></h2>
        <p>Whether you need AI execution, custom software, stronger communities or a premium awards experience, we can build the next step together.</p>
        <div class="hero-actions">
            <a class="button button-primary" href="{{ route('contact') }}">{{ __('public.hero.primary') }}</a>
            <a class="button button-light" href="{{ route('projects') }}">{{ __('public.nav.projects') }}</a>
        </div>
    </div>
    <div class="cta-mark">CN</div>
</section>
@endsection
