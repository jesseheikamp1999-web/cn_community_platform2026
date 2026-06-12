@extends('layouts.dashboard')
@section('title', 'MijnCN — Persoonlijk dashboard')

@section('content')
@php
    $level = max(1, intdiv($user->xp, 500) + 1);
    $levelXp = $user->xp % 500;
    $levelProgress = (int) round($levelXp / 500 * 100);
    $hour = now()->hour;
    $greeting = $hour < 12 ? 'Goedemorgen' : ($hour < 18 ? 'Goedemiddag' : 'Goedenavond');
@endphp

<div class="dashboard-shell product-dashboard">
    <section class="dashboard-welcome">
        <div>
            <span class="dashboard-kicker">MIJN CN · {{ now()->translatedFormat('l d F') }}</span>
            <h1>{{ $greeting }}, {{ $user->name }}.</h1>
            <p>Dit is jouw persoonlijke overzicht. Pak verder waar je gebleven was.</p>
        </div>
        <div class="welcome-actions">
            <a class="button button-secondary" href="{{ route('academy.index') }}">Verder leren</a>
            <a class="button button-primary" href="{{ route('awards') }}">Naar de Awards</a>
        </div>
    </section>

    <section class="overview-grid">
        <article class="overview-card overview-primary">
            <div class="overview-icon">@include('components.icon', ['name' => 'spark'])</div>
            <div><span>JOUW NIVEAU</span><strong>Level {{ $level }}</strong><small>{{ 500 - $levelXp }} XP tot level {{ $level + 1 }}</small></div>
            <div class="overview-progress"><i style="width:{{ $levelProgress }}%"></i></div>
        </article>
        <article class="overview-card">
            <div class="overview-icon red">@include('components.icon', ['name' => 'bolt'])</div>
            <div><span>TOTALE XP</span><strong>{{ number_format($user->xp, 0, ',', '.') }}</strong><small>Blijf bijdragen en leren</small></div>
        </article>
        <article class="overview-card">
            <div class="overview-icon violet">@include('components.icon', ['name' => 'ranking'])</div>
            <div><span>COMMUNITY RANK</span><strong>#{{ $ranking ?? '—' }}</strong><small>Op basis van XP</small></div>
        </article>
        <article class="overview-card">
            <div class="overview-icon green">@include('components.icon', ['name' => 'award'])</div>
            <div><span>BADGES</span><strong>{{ $badges->count() }}</strong><small>{{ $certificatesCount }} certificaten</small></div>
        </article>
    </section>

    <div class="product-grid">
        <div class="product-main">
            @if($activeEdition)
                <section class="award-callout">
                    <div>
                        <span class="live-label"><i></i> {{ strtoupper($activeEdition->status) }}</span>
                        <h2>{{ $activeEdition->name }}</h2>
                        <p>
                            @if($activeEdition->status === 'nominations')
                                Wie maakt verschil binnen CN? Geef die persoon het podium met een sterke nominatie.
                            @elseif($activeEdition->status === 'voting')
                                De stemronde is geopend. Laat jouw stem meetellen.
                            @else
                                De Awards zijn in volle gang. Volg de laatste stand en finale-updates.
                            @endif
                        </p>
                    </div>
                    <a href="{{ route('awards') }}">Open Awards <span>→</span></a>
                </section>
            @endif

            <section class="panel product-panel">
                <div class="panel-heading">
                    <div><span class="panel-label">PERSOONLIJK</span><h2>Recente activiteit</h2></div>
                    <a href="#">Alles bekijken</a>
                </div>
                <div class="activity-list">
                    @forelse($activity as $item)
                        <article class="activity-item">
                            <div class="activity-icon {{ $item['type'] }}">@include('components.icon', ['name' => $item['type']])</div>
                            <div><strong>{{ $item['title'] }}</strong><p>{{ $item['subtitle'] }}</p></div>
                            <div class="activity-meta"><span class="status status-{{ $item['status'] }}">{{ ucfirst($item['status']) }}</span><time>{{ $item['date']?->diffForHumans() ?? 'Onlangs' }}</time></div>
                        </article>
                    @empty
                        <div class="purpose-empty">
                            <div class="empty-icon">@include('components.icon', ['name' => 'activity'])</div>
                            <h3>Jouw activiteit begint hier</h3>
                            <p>Nomineer iemand, stem bij de Awards of start een Academy-les. Je voortgang verschijnt daarna automatisch hier.</p>
                            <div><a class="button button-primary button-small" href="{{ route('awards') }}">Bekijk Awards</a><a class="button button-secondary button-small" href="{{ route('academy.index') }}">Ontdek Academy</a></div>
                        </div>
                    @endforelse
                </div>
            </section>

            <section class="panel product-panel" id="awards">
                <div class="panel-heading">
                    <div><span class="panel-label">CN AWARDS</span><h2>Mijn deelname</h2></div>
                    <a href="{{ route('awards') }}">Awards openen</a>
                </div>
                <div class="participation-summary">
                    <div><strong>{{ $nominations->count() }}</strong><span>Nominaties</span></div>
                    <div><strong>{{ $votes->count() }}</strong><span>Stemmen</span></div>
                    <div><strong>{{ $nominations->where('status', 'winner')->count() }}</strong><span>Resultaten</span></div>
                </div>
                @forelse($nominations as $nomination)
                    <article class="nomination-row">
                        <div class="nominee-avatar">{{ strtoupper(substr($nomination->nominee_name, 0, 1)) }}</div>
                        <div><strong>{{ $nomination->nominee_name }}</strong><p>{{ $nomination->category->name }} · {{ $nomination->category->edition->name }}</p></div>
                        <span class="status status-{{ $nomination->status }}">{{ ucfirst($nomination->status) }}</span>
                    </article>
                @empty
                    <div class="compact-empty"><p>Je hebt nog niemand genomineerd.</p><a href="{{ route('awards') }}">Plaats je eerste nominatie →</a></div>
                @endforelse
            </section>
        </div>

        <aside class="product-side">
            <section class="profile-card">
                <div class="profile-cover"></div>
                <div class="profile-content">
                    <div class="profile-avatar large">@include('components.user-avatar', ['user' => $user])<i></i></div>
                    <span class="role-chip">{{ $user->role->label() }}</span>
                    <h2>{{ $user->name }}</h2>
                    <p>{{ '@'.($user->discord_username ?: 'Discord niet gekoppeld') }}</p>
                    <dl><div><dt>Lid sinds</dt><dd>{{ $user->created_at->translatedFormat('M Y') }}</dd></div><div><dt>Laatste bezoek</dt><dd>{{ optional($user->last_seen_at)->diffForHumans() ?? 'Vandaag' }}</dd></div></dl>
                    <a href="{{ route('mijncn.module', 'profile') }}">Profiel bekijken <span>→</span></a>
                </div>
            </section>

            <section class="panel product-panel academy-panel" id="academy">
                <div class="panel-heading"><div><span class="panel-label">ONTWIKKELING</span><h2>Academy</h2></div><span>{{ $completedLessons }} voltooid</span></div>
                @forelse($paths as $path)
                    <article class="learning-path">
                        <div class="learning-path-top"><div class="path-icon">@include('components.icon', ['name' => 'book'])</div><div><strong>{{ $path->name }}</strong><p>{{ $path->completed_lessons }} van {{ $path->lessons_count }} lessen</p></div><b>{{ $path->progress_percentage }}%</b></div>
                        <div class="path-progress"><i style="width:{{ $path->progress_percentage }}%"></i></div>
                    </article>
                @empty
                    <div class="compact-empty"><p>Er zijn nog geen leerpaden gepubliceerd.</p></div>
                @endforelse
                <a class="panel-action" href="{{ route('academy.index') }}">Naar Academy Wereld <span>→</span></a>
            </section>

            <section class="panel product-panel">
                <div class="panel-heading"><div><span class="panel-label">PLANNING</span><h2>Aankomende events</h2></div></div>
                @forelse($events as $event)
                    <article class="event-row">
                        <div class="event-date"><strong>{{ data_get($event->meta, 'starts_at') ? \Carbon\Carbon::parse(data_get($event->meta, 'starts_at'))->format('d') : $event->published_at->format('d') }}</strong><span>{{ data_get($event->meta, 'starts_at') ? \Carbon\Carbon::parse(data_get($event->meta, 'starts_at'))->translatedFormat('M') : $event->published_at->translatedFormat('M') }}</span></div>
                        <div><strong>{{ $event->title }}</strong><p>{{ data_get($event->meta, 'location', 'CN Community') }}</p></div>
                    </article>
                @empty
                    <div class="compact-empty"><p>Geen events gepland. Nieuwe events verschijnen hier automatisch.</p></div>
                @endforelse
            </section>

            @if($tasks->isNotEmpty())
                <section class="panel product-panel">
                    <div class="panel-heading"><div><span class="panel-label">WERKRUIMTE</span><h2>Mijn taken</h2></div><span>{{ $tasks->count() }} open</span></div>
                    @foreach($tasks as $task)
                        <article class="personal-task"><i class="priority-{{ $task->priority }}"></i><div><strong>{{ $task->title }}</strong><p>{{ $task->deadline?->translatedFormat('d M') ?? 'Geen deadline' }}</p></div><span>→</span></article>
                    @endforeach
                </section>
            @endif
        </aside>
    </div>
</div>
@endsection
