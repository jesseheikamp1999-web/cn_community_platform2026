@extends('layouts.dashboard')
@section('title', $path->name.' - CN Academy')

@section('content')
<main class="module-shell academy-path-page">
    <header class="module-header">
        <div><a class="module-back" href="{{ route('academy.index') }}">&larr; Academy wereld</a><span class="dashboard-kicker">{{ strtoupper($path->target_role) }} OPLEIDING</span><h1>{{ $path->name }}</h1><p>{{ $path->description }}</p></div>
        <div class="academy-path-count"><strong>{{ $progress->where('status', 'passed')->count() }}</strong><span>van {{ $path->lessons->count() }} behaald</span></div>
    </header>

    @foreach($path->lessons->groupBy(fn($lesson) => $lesson->settings['module'] ?? 'Overig') as $module => $lessons)
        <section class="academy-module">
            <header><div><span>MODULE {{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span><h2>{{ $module }}</h2></div><strong>{{ $lessons->where('type', 'lesson')->count() }} lessen</strong></header>
            <div class="academy-lesson-list">
                @foreach($lessons as $lesson)
                    @php($itemProgress = $progress->get($lesson->id))
                    @php($unlocked = $academy->isLessonUnlocked(auth()->user(), $lesson))
                    <article class="{{ $unlocked ? '' : 'locked' }}">
                        <div class="academy-lesson-number">{{ $lesson->type === 'lesson' ? str_pad((string) $lesson->position, 2, '0', STR_PAD_LEFT) : strtoupper(substr($lesson->type, 0, 1)) }}</div>
                        <div><span>{{ ucfirst($lesson->type) }} &middot; {{ $lesson->xp_reward }} XP</span><h3>{{ $lesson->title }}</h3></div>
                        <div class="academy-lesson-state">
                            @if($itemProgress?->status === 'passed')<b class="status status-approved">Behaald</b>
                            @elseif($itemProgress?->status === 'submitted')<b class="status status-pending">In beoordeling</b>
                            @elseif($itemProgress?->status === 'failed')<b class="status status-rejected">Niet behaald</b>
                            @elseif(!$unlocked)<b class="status">Vergrendeld</b>
                            @else<a href="{{ route('academy.lesson', $lesson) }}">Openen &rarr;</a>@endif
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    @endforeach
</main>
@endsection
