@extends('layouts.dashboard')
@section('title', 'Academy beheer - MijnCN')

@section('content')
<main class="module-shell academy-management">
    <header class="module-header"><div><span class="dashboard-kicker">MANAGEMENT &middot; ACADEMY</span><h1>Academy beheer</h1><p>Wijs staffopleidingen toe en beoordeel praktijkopdrachten.</p></div></header>
    <div class="module-columns">
        <section class="module-card">
            <div class="module-card-heading"><div><span>TOEGANG</span><h2>Deelnemer toevoegen</h2></div></div>
            <form class="module-form padded" method="post" action="{{ route('staff.academy.enroll') }}">@csrf
                <label>Deelnemer<select name="user_id" required>@foreach($users as $user)<option value="{{ $user->id }}">{{ $user->name }} ({{ $user->role->label() }})</option>@endforeach</select></label>
                <label>Staffopleiding<select name="learning_path_id" required>@foreach($paths as $path)<option value="{{ $path->id }}">{{ $path->name }}</option>@endforeach</select></label>
                <button class="button button-primary">Toegang geven</button>
            </form>
            <div class="module-list">
                @forelse($enrollments as $enrollment)<article><div class="list-avatar">{{ strtoupper(substr($enrollment->user_name, 0, 2)) }}</div><div><strong>{{ $enrollment->user_name }}</strong><p>{{ $enrollment->path_name }}</p></div><span class="status status-approved">Actief</span></article>@empty<div class="module-empty"><p>Nog geen handmatige toewijzingen.</p></div>@endforelse
            </div>
        </section>
        <section class="module-card">
            <div class="module-card-heading"><div><span>MENTOREN</span><h2>Praktijkbeoordelingen</h2></div></div>
            <div class="academy-review-list">
                @forelse($submissions as $submission)
                    <article><span>{{ $submission->lesson->path->name }}</span><h3>{{ $submission->lesson->title }}</h3><p><strong>{{ $submission->user->name }}</strong>: {{ $submission->submission }}</p>
                        <form class="module-form" method="post" action="{{ route('staff.academy.review', [$submission->lesson, $submission->user]) }}">@csrf<label>Feedback<textarea name="feedback" rows="3"></textarea></label><div class="review-actions"><button class="button button-primary button-small" name="decision" value="passed">Goedkeuren</button><button class="button button-secondary button-small" name="decision" value="failed">Terugsturen</button></div></form>
                    </article>
                @empty<div class="module-empty"><h3>Alles beoordeeld</h3><p>Nieuwe praktijkinzendingen verschijnen hier.</p></div>@endforelse
            </div>
        </section>
    </div>
</main>
@endsection
