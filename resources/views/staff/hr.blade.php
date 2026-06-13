@extends('layouts.dashboard')
@section('title', 'HR & sollicitaties - MijnCN')

@section('content')
<main class="module-shell hr-workspace">
    <header class="module-header">
        <div><span class="dashboard-kicker">STAFF & HR</span><h1>Mensen, groei en beschikbaarheid</h1><p>Beheer sollicitaties en houd zicht op het team zonder losse documenten of oude beheerpagina's.</p></div>
        <a class="button button-secondary" href="{{ route('staff.dashboard') }}">Staff dashboard</a>
    </header>

    <section class="hr-metrics">
        <article><span>NIEUW</span><strong>{{ $applicationCounts['new'] ?? 0 }}</strong><small>sollicitaties</small></article>
        <article><span>IN GESPREK</span><strong>{{ ($applicationCounts['screening'] ?? 0) + ($applicationCounts['interview'] ?? 0) }}</strong><small>lopende kandidaten</small></article>
        <article><span>TEAM</span><strong>{{ $staff->count() }}</strong><small>staffleden</small></article>
        <article><span>AFWEZIG</span><strong>{{ $staff->where('is_currently_absent', true)->count() }}</strong><small>nu niet beschikbaar</small></article>
    </section>

    <div class="hr-layout">
        <section class="module-card hr-applications">
            <div class="module-card-heading">
                <div><span>WERVING</span><h2>Sollicitaties</h2></div>
                <nav class="hr-filters">
                    <a class="{{ !request('status') ? 'active' : '' }}" href="{{ route('staff.hr') }}">Actief</a>
                    <a class="{{ request('status') === 'new' ? 'active' : '' }}" href="{{ route('staff.hr', ['status' => 'new']) }}">Nieuw</a>
                    <a class="{{ request('status') === 'interview' ? 'active' : '' }}" href="{{ route('staff.hr', ['status' => 'interview']) }}">Gesprek</a>
                    @if($hasApplicationArchive)<a class="{{ request('status') === 'archived' ? 'active' : '' }}" href="{{ route('staff.hr', ['status' => 'archived']) }}">Archief <b>{{ $archiveCount }}</b></a>@endif
                </nav>
            </div>
            <div class="hr-application-list">
                @forelse($applications as $application)
                    <article>
                        <div class="hr-application-head">
                            <div class="list-avatar">{{ strtoupper(substr($application->name, 0, 2)) }}</div>
                            <div><span>{{ $application->position }}</span><h3>{{ $application->name }}</h3><p>{{ $application->email }} &middot; {{ $application->created_at->diffForHumans() }}</p></div>
                            <span class="status status-{{ $application->status }}">{{ ['new' => 'Nieuw', 'screening' => 'Screening', 'interview' => 'Gesprek', 'accepted' => 'Aangenomen', 'rejected' => 'Afgewezen'][$application->status] ?? ucfirst($application->status) }}</span>
                        </div>
                        <div class="hr-motivation">{{ data_get($application->answers, 'motivation', 'Geen motivatie opgeslagen.') }}</div>
                        <div class="hr-candidate-facts">
                            <span><b>Leeftijd</b>{{ data_get($application->answers, 'age', 'Onbekend') }}</span>
                            <span><b>Ervaring</b>{{ \Illuminate\Support\Str::limit(data_get($application->answers, 'experience', 'Niet opgegeven'), 140) }}</span>
                            <span><b>Beschikbaar</b>{{ data_get($application->answers, 'availability', 'Niet opgegeven') }}</span>
                        </div>
                        @if(!$showArchive)
                            <form class="module-form hr-review-form" method="post" action="{{ route('staff.hr.applications.update', $application) }}">
                                @csrf @method('PATCH')
                                <label>Interne notitie<textarea name="internal_note" rows="3" placeholder="Afspraken, aandachtspunten of vervolgactie...">{{ $application->internal_note }}</textarea></label>
                                <div class="hr-review-actions">
                                    <select name="status">
                                        @foreach(['new' => 'Nieuw', 'screening' => 'Screening', 'interview' => 'Gesprek', 'accepted' => 'Aannemen en archiveren', 'rejected' => 'Afwijzen en archiveren'] as $value => $label)
                                            <option value="{{ $value }}" @selected($application->status === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <button class="button button-primary button-small">Bijwerken</button>
                                </div>
                            </form>
                        @else
                            <div class="hr-archive-note"><strong>Afgerond {{ $application->reviewed_at?->diffForHumans() }}</strong><span>{{ $application->internal_note ?: 'Geen interne notitie toegevoegd.' }}</span></div>
                        @endif
                    </article>
                @empty
                    <div class="module-empty"><h3>{{ $showArchive ? 'Het archief is leeg' : 'Geen actieve sollicitaties' }}</h3><p>{{ $showArchive ? 'Afgeronde sollicitaties verschijnen hier.' : 'Nieuwe inzendingen verschijnen hier automatisch.' }}</p></div>
                @endforelse
            </div>
            {{ $applications->links() }}
        </section>

        <aside class="hr-side">
            <section class="module-card hr-team-card">
                <div class="module-card-heading"><div><span>TEAMSTATUS</span><h2>Beschikbaarheid</h2></div></div>
                <div class="hr-team-list">
                    @foreach($staff as $staffMember)
                        <article>
                            <div class="birthday-avatar">@include('components.user-avatar', ['user' => $staffMember])</div>
                            <div>
                                <strong>{{ $staffMember->name }}</strong>
                                <small>{{ $staffMember->publicPosition() }}</small>
                                <span class="availability {{ $staffMember->is_currently_absent ? 'unavailable' : '' }}">{{ $staffMember->is_currently_absent ? 'Afwezig' : 'Actief' }}</span>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>

            <section class="module-card hr-calendar-card">
                <div class="module-card-heading"><div><span>GEZAMENLIJKE KALENDER</span><h2>Planning & verjaardagen</h2></div></div>
                <div class="hr-calendar-list">
                    @forelse($calendarItems as $item)
                        <article class="{{ $item['type'] }}">
                            <div class="hr-calendar-date"><strong>{{ $item['date']->translatedFormat('d') }}</strong><span>{{ strtoupper($item['date']->translatedFormat('M')) }}</span></div>
                            <div><span>{{ $item['type'] === 'absence' ? 'AFWEZIGHEID' : 'VERJAARDAG' }}</span><h3>{{ $item['title'] }}</h3><p>{{ $item['meta'] }} &middot; {{ $item['detail'] }}</p></div>
                        </article>
                    @empty
                        <div class="module-empty"><p>Geen geplande afwezigheden of zichtbare verjaardagen.</p></div>
                    @endforelse
                </div>
            </section>
        </aside>
    </div>
</main>
@endsection
