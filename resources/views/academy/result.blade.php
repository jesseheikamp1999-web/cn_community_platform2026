@extends('layouts.dashboard')
@section('title', 'Toetsresultaat - CN Academy')
@section('content')
<main class="module-shell">
    <a class="module-back" href="{{ route('academy.path', $lesson->path) }}">&larr; Terug naar {{ $lesson->path->name }}</a>
    <header class="academy-result-hero {{ $attempt->passed ? 'passed' : 'failed' }}">
        <span>{{ $attempt->passed ? 'GESLAAGD' : 'NOG NIET GESLAAGD' }}</span>
        <h1>{{ number_format($attempt->score, 0) }}%</h1>
        <p>{{ $attempt->passed ? 'Je voortgang is bijgewerkt en het volgende onderdeel is ontgrendeld.' : 'Bekijk je fouten en probeer het onderdeel daarna opnieuw.' }}</p>
    </header>
    <section class="academy-feedback-list">
        @foreach($attempt->answers as $answer)
            @php($question = $questions->get($answer->question_id))
            <article class="{{ $answer->is_correct ? 'correct' : 'incorrect' }}">
                <span>{{ $answer->is_correct ? 'Goed' : 'Niet goed' }}</span>
                <h2>{{ $loop->iteration }}. {{ $question?->question }}</h2>
                <p>Jouw antwoord: <strong>{{ $question?->options[$answer->selected_answer] ?? $answer->selected_answer }}</strong></p>
                @unless($answer->is_correct)<p>Juiste antwoord: <strong>{{ $question?->options[$answer->correct_answer] ?? $answer->correct_answer }}</strong></p>@endunless
                <small>{{ $question?->explanation }}</small>
            </article>
        @endforeach
    </section>
</main>
@endsection
