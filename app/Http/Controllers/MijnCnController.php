<?php

namespace App\Http\Controllers;

use App\Models\Badge;
use App\Models\AbsenceRequest;
use App\Models\DiscordMember;
use App\Models\LearningPath;
use App\Models\Lesson;
use App\Models\Task;
use App\Models\User;
use App\Services\NomiAiService;
use App\Services\TaskWorkflowService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use RuntimeException;

class MijnCnController extends Controller
{
    public function show(Request $request, string $module): View
    {
        abort_unless(in_array($module, $this->modules(), true), 404);

        $user = $request->user();
        $data = ['module' => $module, 'user' => $user];

        if ($module === 'notifications') {
            $data['notifications'] = $user->notifications()->latest()->paginate(20);
        } elseif ($module === 'inbox') {
            DB::table('messages')->where('recipient_id', $user->id)->whereNull('read_at')->update(['read_at' => now()]);
            $data['messages'] = DB::table('messages')
                ->leftJoin('users as senders', 'senders.id', '=', 'messages.sender_id')
                ->where(fn ($query) => $query->where('messages.recipient_id', $user->id)->orWhere('messages.sender_id', $user->id))
                ->select('messages.*', 'senders.name as sender_name')
                ->latest('messages.created_at')
                ->paginate(20);
        } elseif ($module === 'nominations') {
            $data['nominations'] = $user->nominations()->with('category.edition')->latest()->paginate(20);
        } elseif ($module === 'votes') {
            $data['votes'] = $user->votes()->with('nomination.category.edition')->latest()->paginate(20);
        } elseif ($module === 'results') {
            $data['results'] = DB::table('award_winners')
                ->join('nominations', 'nominations.id', '=', 'award_winners.nomination_id')
                ->join('award_categories', 'award_categories.id', '=', 'award_winners.award_category_id')
                ->join('award_editions', 'award_editions.id', '=', 'award_categories.award_edition_id')
                ->where('nominations.user_id', $user->id)
                ->select('award_winners.*', 'nominations.nominee_name', 'award_categories.name as category_name', 'award_editions.name as edition_name')
                ->latest('award_winners.created_at')
                ->paginate(20);
        } elseif (in_array($module, ['lessons', 'exams'], true)) {
            $types = $module === 'exams' ? ['quiz', 'exam', 'scenario'] : ['lesson', 'assignment'];
            $data['lessons'] = Lesson::with('path')
                ->whereIn('type', $types)
                ->whereHas('path', fn ($query) => $query->where('is_published', true))
                ->orderBy('learning_path_id')
                ->orderBy('position')
                ->paginate(20);
            $data['progress'] = DB::table('lesson_progress')->where('user_id', $user->id)->get()->keyBy('lesson_id');
        } elseif ($module === 'certificates') {
            $data['certificates'] = DB::table('certificates')
                ->join('learning_paths', 'learning_paths.id', '=', 'certificates.learning_path_id')
                ->where('certificates.user_id', $user->id)
                ->select('certificates.*', 'learning_paths.name as path_name')
                ->latest('issued_at')
                ->paginate(20);
        } elseif ($module === 'badges') {
            $data['badges'] = Badge::orderBy('xp_required')->get();
            $data['earnedBadgeIds'] = $user->badges()->pluck('badges.id')->all();
        } elseif ($module === 'tasks') {
            $data['tasks'] = Task::with('creator')
                ->whereNull('archived_at')
                ->where(function ($query) use ($user) {
                    $query->where('is_public', true)
                        ->orWhere('claimed_by', $user->id)
                        ->orWhereHas('assignees', fn ($assignees) => $assignees->whereKey($user->id));
                })
                ->orderByRaw("CASE status WHEN 'in_progress' THEN 1 WHEN 'open' THEN 2 WHEN 'waiting' THEN 3 WHEN 'completed' THEN 4 ELSE 5 END")
                ->orderByRaw("CASE priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'normal' THEN 3 ELSE 4 END")
                ->paginate(30);
        } elseif ($module === 'profile') {
            $data['loginHistory'] = DB::table('login_histories')->where('user_id', $user->id)->latest('logged_in_at')->limit(8)->get();
        } elseif ($module === 'settings') {
            $data['paths'] = LearningPath::where('is_published', true)->orderBy('name')->get();
        } elseif ($module === 'absences') {
            abort_unless($user->role->value !== 'member', 403);
            $data['absences'] = $user->absenceRequests()->latest()->paginate(20);
            $data['currentAbsence'] = $user->absenceRequests()->current()->first();
        } elseif ($module === 'birthdays') {
            $isStaff = $user->role->value !== 'member';
            $data['birthdays'] = User::whereNotNull('birth_date')
                ->where(function ($query) use ($isStaff, $user) {
                    $query->where('id', $user->id)
                        ->orWhere('birthday_visibility', 'community');
                    if ($isStaff) {
                        $query->orWhere('birthday_visibility', 'staff');
                    }
                })
                ->get()
                ->map(function (User $birthdayUser) {
                    $nextBirthday = today()->setDate(today()->year, $birthdayUser->birth_date->month, $birthdayUser->birth_date->day);
                    if ($nextBirthday->isBefore(today())) {
                        $nextBirthday->addYear();
                    }
                    $birthdayUser->next_birthday = $nextBirthday;
                    $birthdayUser->days_until_birthday = today()->diffInDays($nextBirthday);
                    $birthdayUser->next_age = $nextBirthday->year - $birthdayUser->birth_date->year;

                    return $birthdayUser;
                })
                ->sortBy('days_until_birthday')
                ->values();
        } elseif ($module === 'community') {
            $search = trim($request->string('q')->toString());
            $membersQuery = DiscordMember::query()
                ->where('is_active', true)
                ->where('is_bot', false);

            $data['communityStats'] = [
                'members' => (clone $membersQuery)->count(),
                'online' => (clone $membersQuery)->whereHas('mijncnUser', fn ($query) => $query->where('last_seen_at', '>=', now()->subMinutes(5)))->count(),
                'staff' => (clone $membersQuery)->whereNot('platform_role', 'member')->count(),
                'badges' => DB::table('badge_user')
                    ->join('users', 'users.id', '=', 'badge_user.user_id')
                    ->join('discord_members', 'discord_members.discord_id', '=', 'users.discord_id')
                    ->where('discord_members.is_active', true)
                    ->where('discord_members.is_bot', false)
                    ->count(),
            ];
            $data['members'] = $membersQuery
                ->with(['mijncnUser' => fn ($query) => $query->withCount(['badges', 'nominations'])])
                ->when($search !== '', fn ($query) => $query->where(function ($memberQuery) use ($search) {
                    $memberQuery->where('display_name', 'like', '%'.$search.'%')
                        ->orWhere('username', 'like', '%'.$search.'%');
                }))
                ->orderByRaw("CASE platform_role
                    WHEN 'owner' THEN 1
                    WHEN 'management' THEN 2
                    WHEN 'admin' THEN 3
                    WHEN 'moderator' THEN 4
                    WHEN 'helper' THEN 5
                    WHEN 'jury' THEN 6
                    ELSE 7 END")
                ->orderBy('display_name')
                ->paginate(24)
                ->withQueryString();
            $data['communitySearch'] = $search;
        }

        return view('dashboard.module', $data);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'profile_bio' => ['nullable', 'string', 'max:280'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'birthday_visibility' => ['required', 'in:private,staff,community'],
            'birthday_notifications' => ['nullable', 'boolean'],
        ]);
        $data['birthday_notifications'] = $request->boolean('birthday_notifications');
        $request->user()->update($data);

        return back()->with('success', 'Je MijnCN-profiel is bijgewerkt.');
    }

    public function markNotificationsRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('success', 'Alle meldingen zijn als gelezen gemarkeerd.');
    }

    public function askNomi(Request $request, NomiAiService $nomi): RedirectResponse
    {
        $data = $request->validate([
            'question' => ['required', 'string', 'min:4', 'max:1000'],
            'context' => ['required', 'in:community,academy,staff'],
        ]);

        try {
            $answer = $nomi->ask($data['question'], $data['context']);
        } catch (RuntimeException $exception) {
            return back()->withInput()->withErrors(['question' => $exception->getMessage()]);
        }

        return back()->withInput()->with('nomi_answer', $answer);
    }

    public function claimTask(Request $request, Task $task, TaskWorkflowService $workflow): RedirectResponse
    {
        $workflow->claim($task, $request->user());

        return back()->with('success', 'De taak staat nu op jouw naam.');
    }

    public function completeTask(Request $request, Task $task, TaskWorkflowService $workflow): RedirectResponse
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:1000']]);
        $workflow->complete($task, $request->user(), $data['note'] ?? null);

        return back()->with('success', 'De taak is voltooid.');
    }

    public function reportAbsence(Request $request): RedirectResponse
    {
        abort_unless($request->user()->role->value !== 'member', 403);
        $data = $request->validate([
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $startsAt = Carbon::parse($data['starts_at']);
        $endsAt = Carbon::parse($data['ends_at']);
        $absence = [
            'starts_on' => $startsAt->toDateString(),
            'ends_on' => $endsAt->toDateString(),
            'reason' => $data['reason'],
            'status' => 'approved',
        ];
        if (Schema::hasColumns('absence_requests', ['starts_at', 'ends_at'])) {
            $absence['starts_at'] = $startsAt;
            $absence['ends_at'] = $endsAt;
        }
        $request->user()->absenceRequests()->create($absence);
        if ($startsAt->lte(now()) && $endsAt->gte(now())) {
            $request->user()->staffProfile()->updateOrCreate(
                ['user_id' => $request->user()->id],
                [
                    'position' => $request->user()->role->label(),
                    'status' => 'absent',
                    'joined_at' => $request->user()->created_at->toDateString(),
                ]
            );
        }

        return back()->with('success', 'Je staat voor deze periode op niet beschikbaar.');
    }

    public function cancelAbsence(Request $request, AbsenceRequest $absence): RedirectResponse
    {
        abort_unless($absence->user_id === $request->user()->id, 403);
        $absence->update(['status' => 'rejected']);

        if (!$request->user()->isCurrentlyAbsent()) {
            $request->user()->staffProfile()->update(['status' => 'active']);
        }

        return back()->with('success', 'Je afwezigheidsmelding is ingetrokken.');
    }

    private function modules(): array
    {
        return ['profile', 'notifications', 'inbox', 'nominations', 'votes', 'results', 'lessons', 'exams', 'certificates', 'badges', 'tasks', 'nomi', 'settings', 'absences', 'birthdays', 'community'];
    }
}
