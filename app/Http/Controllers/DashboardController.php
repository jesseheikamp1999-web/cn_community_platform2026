<?php

namespace App\Http\Controllers;

use App\Models\AwardEdition;
use App\Models\Content;
use App\Models\LearningPath;
use App\Models\Task;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $user = request()->user();

        try {
        $nominations = Schema::hasTable('nominations')
            ? $user->nominations()->with('category.edition')->latest()->limit(5)->get()
            : collect();
        $votes = Schema::hasTable('votes')
            ? $user->votes()->with('nomination.category')->latest()->limit(5)->get()
            : collect();
        $badges = Schema::hasTable('badges') && Schema::hasTable('badge_user')
            ? $user->badges()->latest('badge_user.awarded_at')->limit(6)->get()
            : collect();

        $progressByPath = Schema::hasTable('lesson_progress') && Schema::hasTable('lessons')
            ? DB::table('lesson_progress')
                ->join('lessons', 'lessons.id', '=', 'lesson_progress.lesson_id')
                ->where('lesson_progress.user_id', $user->id)
                ->selectRaw('lessons.learning_path_id, COUNT(*) as started, SUM(CASE WHEN lesson_progress.status = ? THEN 1 ELSE 0 END) as completed', ['passed'])
                ->groupBy('lessons.learning_path_id')
                ->get()
                ->keyBy('learning_path_id')
            : collect();

        $paths = Schema::hasTable('learning_paths') && Schema::hasTable('lessons')
            ? LearningPath::where('is_published', true)
                ->withCount('lessons')
                ->limit(3)
                ->get()
                ->map(function (LearningPath $path) use ($progressByPath) {
                    $progress = $progressByPath->get($path->id);
                    $path->completed_lessons = (int) ($progress->completed ?? 0);
                    $path->progress_percentage = $path->lessons_count > 0
                        ? (int) round($path->completed_lessons / $path->lessons_count * 100)
                        : 0;

                    return $path;
                })
            : collect();

        $ranking = UserRanking::forUser($user->id);
        $activity = $this->activity($nominations, $votes, $badges);

        $html = view('dashboard.index', [
            'user' => $user,
            'nominations' => $nominations,
            'votes' => $votes,
            'badges' => $badges,
            'paths' => $paths,
            'notifications' => Schema::hasTable('notifications') ? $user->notifications()->latest()->limit(5)->get() : collect(),
            'activity' => $activity,
            'ranking' => $ranking,
            'activeEdition' => Schema::hasTable('award_editions')
                ? AwardEdition::whereIn('status', ['nominations', 'voting', 'jury', 'finale'])->latest('year')->first()
                : null,
            'events' => Schema::hasTable('contents')
                ? Content::published()->where('type', 'event')->latest('published_at')->limit(3)->get()
                : collect(),
            'tasks' => Schema::hasTable('tasks') && Schema::hasTable('task_assignees')
                ? Task::whereHas('assignees', fn ($query) => $query->whereKey($user->id))
                    ->whereNot('status', 'completed')
                    ->orderByRaw("CASE priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'normal' THEN 3 ELSE 4 END")
                    ->limit(4)
                    ->get()
                : collect(),
            'certificatesCount' => Schema::hasTable('certificates')
                ? DB::table('certificates')->where('user_id', $user->id)->count()
                : 0,
            'completedLessons' => Schema::hasTable('lesson_progress')
                ? DB::table('lesson_progress')->where('user_id', $user->id)->where('status', 'passed')->count()
                : 0,
        ])->render();

        return response($html);
        } catch (Throwable $exception) {
            report($exception);

            return response()
                ->view('dashboard.recovery', [
                    'user' => $user,
                    'reference' => now()->format('Ymd-His'),
                    'technicalMessage' => $user->role === \App\Enums\UserRole::Owner
                        ? $exception->getMessage()
                        : null,
                ], 200);
        }
    }

    private function activity(Collection $nominations, Collection $votes, Collection $badges): Collection
    {
        return collect()
            ->merge($nominations->map(fn ($item) => [
                'type' => 'nomination',
                'title' => 'Nominatie voor '.$item->nominee_name,
                'subtitle' => $item->category->name,
                'status' => $item->status,
                'date' => $this->asDate($item->updated_at),
            ]))
            ->merge($votes->map(fn ($item) => [
                'type' => 'vote',
                'title' => 'Stem uitgebracht',
                'subtitle' => $item->nomination->category->name.' · '.$item->nomination->nominee_name,
                'status' => $item->is_valid ? 'geldig' : 'controle',
                'date' => $this->asDate($item->created_at),
            ]))
            ->merge($badges->map(fn ($item) => [
                'type' => 'badge',
                'title' => 'Badge behaald: '.$item->name,
                'subtitle' => $item->description,
                'status' => 'behaald',
                'date' => $this->asDate($item->pivot->awarded_at),
            ]))
            ->sortByDesc('date')
            ->take(6)
            ->values();
    }

    private function asDate(mixed $value): ?Carbon
    {
        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value);
        }

        return $value ? Carbon::parse($value) : null;
    }
}

final class UserRanking
{
    public static function forUser(int $userId): ?int
    {
        if (!Schema::hasTable('users') || !Schema::hasColumn('users', 'xp')) {
            return null;
        }

        $userXp = DB::table('users')->where('id', $userId)->value('xp');

        return $userXp === null
            ? null
            : DB::table('users')->where('xp', '>', $userXp)->count() + 1;
    }
}
