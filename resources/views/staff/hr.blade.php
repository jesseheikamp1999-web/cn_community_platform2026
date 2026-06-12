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
        <article><span>AFWEZIG</span><strong>{{ $staff->where('is_currently_absent', true)->count() }}</strong><small>niet beschikbaar</small></article>
    </section>

    <div class="hr-layout">
        <section class="module-card hr-applications">
            <div class="module-card-heading">
                <div><span>WERVING</span><h2>Sollicitaties</h2></div>
                <nav class="hr-filters">
                    <a class="{{ !request('status') ? 'active' : '' }}" href="{{ route('staff.hr') }}">Alles</a>
                    <a class="{{ request('status') === 'new' ? 'active' : '' }}" href="{{ route('staff.hr', ['status' => 'new']) }}">Nieuw</a>
                    <a class="{{ request('status') === 'interview' ? 'active' : '' }}" href="{{ route('staff.hr', ['status' => 'interview']) }}">Gesprek</a>
                </nav>
            </div>
            <div class="hr-application-list">
                @forelse($applications as $application)
                    <article>
                        <div class="hr-application-head">
                            <div class="list-avatar">{{ strtoupper(substr($application->name, 0, 2)) }}</div>
                            <div><span>{{ $application->position }}</span><h3>{{ $application->name }}</h3><p>{{ $application->email }} &middot; {{ $application->created_at->diffForHumans() }}</p></div>
                            <span class="status status-{{ $application->status }}">{{ ucfirst($application->status) }}</span>
                        </div>
                        <div class="hr-motivation">{{ data_get($application->answers, 'motivation', 'Geen motivatie opgeslagen.') }}</div>
                        <div class="hr-candidate-facts">
                            <span><b>Leeftijd</b>{{ data_get($application->answers, 'age', 'Onbekend') }}</span>
                            <span><b>Ervaring</b>{{ \Illuminate\Support\Str::limit(data_get($application->answers, 'experience', 'Niet opgegeven'), 140) }}</span>
                            <span><b>Beschikbaar</b>{{ data_get($application->answers, 'availability', 'Niet opgegeven') }}</span>
                        </div>
                        <form class="module-form hr-review-form" method="post" action="{{ route('staff.hr.applications.update', $application) }}">
                            @csrf @method('PATCH')
                            <label>Interne notitie<textarea name="internal_note" rows="3" placeholder="Afspraken, aandachtspunten of vervolgactie...">{{ $application->internal_note }}</textarea></label>
                            <div class="hr-review-actions">
                                <select name="status">
                                    @foreach(['new' => 'Nieuw', 'screening' => 'Screening', 'interview' => 'Gesprek', 'accepted' => 'Aangenomen', 'rejected' => 'Afgewezen'] as $value => $label)
                                        <option value="{{ $value }}" @selected($application->status === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <button class="button button-primary button-small">Bijwerken</button>
                            </div>
                        </form>
                    </article>
                @empty
                    <div class="module-empty"><h3>Geen sollicitaties in deze selectie</h3><p>Nieuwe inzendingen via de publieke sollicitatiepagina verschijnen hier automatisch.</p></div>
                @endforelse
            </div>
            {{ $applications->links() }}
        </section>

        <aside class="hr-side">
            <section class="module-card">
                <div class="module-card-heading"><div><span>TEAMSTATUS</span><h2>Beschikbaarheid</h2></div></div>
                <div class="hr-team-list">
                    @foreach($staff as $staffMember)
                        <article>
                            <div class="birthday-avatar">@include('components.user-avatar', ['user' => $staffMember])</div>
                            <div><strong>{{ $staffMember->name }}</strong><small>{{ $staffMember->publicPosition() }}</small></div>
                            <span class="availability {{ $staffMember->is_currently_absent ? 'unavailable' : '' }}">{{ $staffMember->is_currently_absent ? 'Afwezig' : 'Actief' }}</span>
                        </article>
                    @endforeach
                </div>
            </section>

            <section class="module-card">
                <div class="module-card-heading"><div><span>KALENDER</span><h2>Verjaardagen</h2></div></div>
                <div class="hr-birthday-list">
                    @forelse($upcomingBirthdays as $birthdayUser)
                        <article><strong>{{ $birthdayUser->birth_date->translatedFormat('d M') }}</strong><span>{{ $birthdayUser->name }}</span></article>
                    @empty
                        <p class="muted">Geen zichtbare verjaardagen ingesteld.</p>
                    @endforelse
                </div>
            </section>
        </aside>
    </div>
</main>
@endsection
