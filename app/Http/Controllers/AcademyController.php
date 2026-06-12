<?php

namespace App\Http\Controllers;

use App\Models\LearningPath;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\QuestionBank;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Services\Academy2026Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AcademyController extends Controller
{
    public function index(Request $request, Academy2026Service $academy): View
    {
        $progress = LessonProgress::where('user_id', $request->user()->id)->get()->keyBy('lesson_id');
        $paths = LearningPath::where('is_published', true)
            ->whereIn('target_role', ['helper', 'moderator', 'admin', 'management'])
            ->with('lessons')
            ->get()
            ->sortBy(fn ($path) => array_search($path->target_role, ['helper', 'moderator', 'admin', 'management'], true))
            ->map(function (LearningPath $path) use ($request, $academy, $progress) {
                $path->is_unlocked = $academy->isPathUnlocked($request->user(), $path);
                $path->passed_count = $path->lessons->filter(fn ($lesson) => $progress->get($lesson->id)?->status === 'passed')->count();
                $path->progress_percentage = $path->lessons->count()
                    ? (int) round($path->passed_count / $path->lessons->count() * 100)
                    : 0;

                return $path;
            });

        return view('academy.index', compact('paths', 'progress'));
    }

    public function path(Request $request, LearningPath $path, Academy2026Service $academy): View
    {
        abort_unless($path->is_published && $academy->isPathUnlocked($request->user(), $path), 403);
        $path->load('lessons');
        $progress = LessonProgress::where('user_id', $request->user()->id)
            ->whereIn('lesson_id', $path->lessons->pluck('id'))
            ->get()
            ->keyBy('lesson_id');

        return view('academy.path', compact('path', 'progress', 'academy'));
    }

    public function lesson(Request $request, Lesson $lesson, Academy2026Service $academy): View
    {
        $lesson->load('path');
        abort_unless($lesson->path->is_published && $academy->isLessonUnlocked($request->user(), $lesson), 403);
        $progress = LessonProgress::where('lesson_id', $lesson->id)->where('user_id', $request->user()->id)->first();
        $questions = collect();
        if (in_array($lesson->type, ['lesson', 'quiz', 'exam'], true)) {
            $retryDays = (int) ($lesson->settings['retry_days'] ?? 0);
            if ($progress?->status === 'failed' && $retryDays > 0 && $progress->updated_at->addDays($retryDays)->isFuture()) {
                throw ValidationException::withMessages([
                    'exam' => 'Je kunt dit examen opnieuw proberen op '.$progress->updated_at->addDays($retryDays)->translatedFormat('d F Y H:i').'.',
                ]);
            }
            $questionCount = (int) ($lesson->settings['question_count'] ?? 5);
            $questions = QuestionBank::where('lesson_id', $lesson->id)
                ->where('is_active', true)
                ->inRandomOrder()
                ->limit($questionCount)
                ->get()
                ->unique('question')
                ->values();
            abort_if($questions->count() < $questionCount, 503, 'De vragenbank voor deze toets is nog niet volledig opgebouwd.');
            $questions->each(function (QuestionBank $question) {
                $question->display_options = collect($question->options)->shuffle()->all();
            });
            $quizAttempt = QuizAttempt::create([
                'user_id' => $request->user()->id,
                'course_id' => $lesson->learning_path_id,
                'module_id' => $lesson->settings['module_number'] ?? null,
                'lesson_id' => $lesson->id,
                'type' => $lesson->type,
                'started_at' => now(),
            ]);
            session(['academy_attempt_'.$lesson->id => [
                'started_at' => now()->timestamp,
                'question_ids' => $questions->pluck('id')->all(),
                'attempt_id' => $quizAttempt->id,
            ]]);
        }

        return view('academy.lesson', compact('lesson', 'progress', 'questions'));
    }

    public function completeLesson(Request $request, Lesson $lesson, Academy2026Service $academy): RedirectResponse
    {
        return $this->submitAssessment($request, $lesson, $academy);
    }

    public function submitAssignment(Request $request, Lesson $lesson, Academy2026Service $academy): RedirectResponse
    {
        abort_unless($lesson->type === 'assignment' && $academy->isLessonUnlocked($request->user(), $lesson), 403);
        $data = $request->validate(['submission' => ['required', 'string', 'min:80', 'max:5000']]);
        $academy->submitAssignment($request->user(), $lesson, $data['submission']);

        return redirect()->route('academy.path', $lesson->path)->with('success', 'Praktijkopdracht ingeleverd voor mentorbeoordeling.');
    }

    public function submitAssessment(Request $request, Lesson $lesson, Academy2026Service $academy): RedirectResponse
    {
        abort_unless(in_array($lesson->type, ['lesson', 'quiz', 'exam'], true) && $academy->isLessonUnlocked($request->user(), $lesson), 403);
        $attempt = session()->pull('academy_attempt_'.$lesson->id);
        abort_unless($attempt, 419);
        $timeSpentSeconds = max(0, now()->timestamp - $attempt['started_at']);
        $timerMinutes = (int) ($lesson->settings['timer_minutes'] ?? 0);
        if ($timerMinutes > 0 && $timeSpentSeconds > ($timerMinutes * 60)) {
            throw ValidationException::withMessages(['assessment' => 'De tijd voor deze toets is verstreken.']);
        }
        if ($lesson->type === 'lesson' && $timeSpentSeconds < $academy->requiredReadSeconds($lesson)) {
            throw ValidationException::withMessages(['assessment' => 'Neem eerst voldoende tijd om de volledige les door te nemen.']);
        }

        $questions = QuestionBank::whereIn('id', $attempt['question_ids'])->get()->keyBy('id');
        $answers = $request->input('answers', []);
        Validator::make(['answers' => $answers], [
            'answers' => ['required', 'array', 'size:'.count($attempt['question_ids'])],
            'answers.*' => ['required', 'string', 'max:20'],
        ], [
            'answers.required' => 'Beantwoord eerst alle vragen.',
            'answers.size' => 'Beantwoord eerst alle vragen.',
        ])->validate();
        $expectedIds = collect($attempt['question_ids'])->map(fn ($id) => (string) $id)->sort()->values();
        $answeredIds = collect(array_keys($answers))->map(fn ($id) => (string) $id)->sort()->values();
        if ($expectedIds->all() !== $answeredIds->all()) {
            throw ValidationException::withMessages(['answers' => 'De ingeleverde vragen horen niet bij deze toetspoging. Start de toets opnieuw.']);
        }
        $earned = $questions->sum(fn ($question) => ($answers[$question->id] ?? null) === $question->correct_answer ? 1 : 0);
        $possible = max(1, $questions->count());
        $score = round($earned / $possible * 100, 2);
        $passScore = $academy->passScore($lesson);
        $passed = $score >= $passScore;
        DB::transaction(function () use ($request, $lesson, $academy, $attempt, $questions, $answers, $score, $passed, $timeSpentSeconds) {
            $quizAttempt = QuizAttempt::whereKey($attempt['attempt_id'])->where('user_id', $request->user()->id)->lockForUpdate()->firstOrFail();
            abort_if($quizAttempt->submitted_at, 409, 'Deze toetspoging is al ingeleverd.');
            $quizAttempt->update([
                'score' => $score,
                'passed' => $passed,
                'time_spent_seconds' => $timeSpentSeconds,
                'tab_switches' => (int) $request->input('tab_switches', 0),
                'submitted_at' => now(),
            ]);
            foreach ($questions as $question) {
                $quizAttempt->answers()->create([
                    'question_id' => $question->id,
                    'selected_answer' => $answers[$question->id],
                    'correct_answer' => $question->correct_answer,
                    'is_correct' => $answers[$question->id] === $question->correct_answer,
                ]);
            }
            $academy->completeAssessment($request->user(), $lesson, $score, $answers, $timeSpentSeconds, (int) $request->input('tab_switches', 0));
        });

        return redirect()->route('academy.attempt.result', $attempt['attempt_id']);
    }

    public function result(Request $request, QuizAttempt $attempt): View
    {
        abort_unless($attempt->user_id === $request->user()->id && $attempt->submitted_at, 403);
        $attempt->load('answers');
        $questions = QuestionBank::whereIn('id', $attempt->answers->pluck('question_id'))->get()->keyBy('id');
        $lesson = Lesson::with('path')->findOrFail($attempt->lesson_id);

        return view('academy.result', compact('attempt', 'questions', 'lesson'));
    }

    public function manage(Request $request): View
    {
        $this->authorizeManagement($request->user());
        $paths = LearningPath::where('is_published', true)->where('target_role', '!=', 'lid')->orderBy('name')->get();
        $users = User::orderBy('name')->get();
        $enrollments = DB::table('academy_enrollments')
            ->join('users', 'users.id', '=', 'academy_enrollments.user_id')
            ->join('learning_paths', 'learning_paths.id', '=', 'academy_enrollments.learning_path_id')
            ->select('academy_enrollments.*', 'users.name as user_name', 'learning_paths.name as path_name')
            ->latest('academy_enrollments.enrolled_at')
            ->get();
        $submissions = LessonProgress::with(['lesson.path', 'user'])
            ->where('status', 'submitted')
            ->latest()
            ->get();

        return view('staff.academy', compact('paths', 'users', 'enrollments', 'submissions'));
    }

    public function enroll(Request $request): RedirectResponse
    {
        $this->authorizeManagement($request->user());
        $data = $request->validate([
            'learning_path_id' => ['required', 'exists:learning_paths,id'],
            'user_id' => ['required', 'exists:users,id'],
        ]);
        $path = LearningPath::findOrFail($data['learning_path_id']);
        abort_if($path->target_role === 'lid', 422);
        DB::table('academy_enrollments')->updateOrInsert(
            ['learning_path_id' => $path->id, 'user_id' => $data['user_id']],
            [
                'assigned_by' => $request->user()->id,
                'status' => 'active',
                'enrolled_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        return back()->with('success', 'De deelnemer is aan de opleiding toegevoegd.');
    }

    public function review(Request $request, Lesson $lesson, User $student, Academy2026Service $academy): RedirectResponse
    {
        abort_unless($this->canMentor($request->user()), 403);
        $data = $request->validate([
            'decision' => ['required', 'in:passed,failed'],
            'feedback' => ['nullable', 'string', 'max:3000'],
        ]);
        $academy->reviewAssignment($request->user(), $lesson, $student, $data['decision'] === 'passed', $data['feedback'] ?? null);

        return back()->with('success', 'De praktijkopdracht is beoordeeld.');
    }

    private function authorizeManagement(User $user): void
    {
        abort_unless(in_array($user->role->value, ['management', 'owner'], true), 403);
    }

    private function canMentor(User $user): bool
    {
        return in_array($user->role->value, ['management', 'owner'], true)
            || in_array(mb_strtolower($user->name), ['jesse', 'melvin', 'stan'], true);
    }
}
