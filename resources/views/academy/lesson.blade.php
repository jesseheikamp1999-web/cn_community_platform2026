@extends('layouts.dashboard')
@section('title', $lesson->title.' - CN Academy')

@section('content')
<main class="module-shell academy-lesson-page">
    <a class="module-back" href="{{ route('academy.path', $lesson->path) }}">&larr; {{ $lesson->path->name }}</a>
    <div class="academy-lesson-layout">
        <article class="academy-reading">
            <header><span>{{ strtoupper($lesson->type) }} &middot; {{ $lesson->xp_reward }} XP</span><h1>{{ $lesson->title }}</h1><p>{{ $lesson->settings['module'] ?? $lesson->path->name }}</p></header>
            <div class="academy-reading-content">{!! $lesson->content !!}</div>
        </article>

        <aside class="academy-action-card">
            @if($lesson->type === 'assignment')
                <span>PRAKTIJKOPDRACHT</span><h2>Mentorbeoordeling</h2><p>Jesse, Melvin, Stan of Management beoordeelt jouw onderbouwing.</p>
                <form class="module-form" method="post" action="{{ route('academy.assignment.submit', $lesson) }}">@csrf<label>Jouw uitwerking<textarea name="submission" rows="10" minlength="80" required>{{ old('submission', $progress?->submission) }}</textarea></label><button class="button button-primary">Opdracht inleveren</button></form>
            @else
                <span>{{ $lesson->type === 'lesson' ? 'KENNISCONTROLE' : ($lesson->type === 'exam' ? 'EINDEXAMEN' : 'TUSSENTIJDSE TOETS') }}</span>
                <h2>{{ $lesson->type === 'lesson' ? 'Controleer je kennis' : (($lesson->settings['timer_minutes'] ?? 15).' minuten') }}</h2><p>Minimaal {{ $lesson->settings['knowledge_check']['pass_score'] ?? $lesson->settings['pass_score'] ?? 80 }}% is nodig om te slagen.</p>
                <form class="academy-assessment" method="post" action="{{ $lesson->type === 'lesson' ? route('academy.lesson.complete', $lesson) : route('academy.assessment.submit', $lesson) }}">@csrf
                    <input type="hidden" name="tab_switches" value="0" data-academy-tab-switches>
                    @foreach($questions as $question)
                        @php($options = $question->display_options)
                        <fieldset><legend>{{ $loop->iteration }}. {{ $question->question }}</legend>@foreach($options as $key => $option)<label><input type="radio" name="answers[{{ $question->id }}]" value="{{ $key }}" required><span>{{ $key }}</span>{{ $option }}</label>@endforeach</fieldset>
                    @endforeach
                    <p class="assessment-warning" data-assessment-warning>Beantwoord eerst alle vragen.</p>
                    <button class="button button-primary" data-assessment-submit disabled>Antwoorden inleveren</button>
                </form>
                <script>
                    (() => {
                        const input = document.querySelector('[data-academy-tab-switches]');
                        if (!input) return;
                        const form = input.closest('form');
                        const submit = form.querySelector('[data-assessment-submit]');
                        const warning = form.querySelector('[data-assessment-warning]');
                        const groups = [...form.querySelectorAll('fieldset')];
                        const update = () => {
                            const complete = groups.every(group => group.querySelector('input:checked'));
                            submit.disabled = !complete;
                            warning.hidden = complete;
                        };
                        form.addEventListener('change', update);
                        form.addEventListener('submit', (event) => {
                            update();
                            if (submit.disabled) {
                                event.preventDefault();
                                warning.hidden = false;
                                warning.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            }
                        });
                        document.addEventListener('visibilitychange', () => {
                            if (document.hidden) input.value = String(Number(input.value) + 1);
                        });
                        update();
                    })();
                </script>
            @endif
        </aside>
    </div>
</main>
@endsection
