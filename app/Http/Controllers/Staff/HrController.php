<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\AbsenceRequest;
use App\Models\Application;
use App\Models\User;
use App\Services\DiscordMemberSyncService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class HrController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeManagement($request->user());
        $status = $request->string('status')->toString();
        $hasApplicationArchive = Schema::hasColumn('applications', 'archived_at');
        $hasAbsenceTimes = Schema::hasColumns('absence_requests', ['starts_at', 'ends_at']);

        $showArchive = $hasApplicationArchive && $status === 'archived';
        $applications = Application::with(['user', 'reviewer'])
            ->when($hasApplicationArchive && $showArchive, fn ($query) => $query->whereNotNull('archived_at'))
            ->when($hasApplicationArchive && !$showArchive, fn ($query) => $query->whereNull('archived_at'))
            ->when(in_array($status, ['new', 'screening', 'interview'], true), fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $staff = User::whereNot('role', 'member')
            ->with('staffProfile')
            ->withExists(['absenceRequests as is_currently_absent' => fn ($query) => $query->current()])
            ->orderByRaw("CASE role WHEN 'owner' THEN 1 WHEN 'management' THEN 2 WHEN 'admin' THEN 3 WHEN 'moderator' THEN 4 WHEN 'helper' THEN 5 ELSE 6 END")
            ->orderBy('name')
            ->get();

        $upcomingBirthdays = User::whereNotNull('birth_date')
            ->whereIn('birthday_visibility', ['staff', 'community'])
            ->get()
            ->map(function (User $user): User {
                $birthday = $this->nextBirthday($user);
                $user->next_birthday = $birthday;
                $user->days_until_birthday = (int) today()->diffInDays($birthday);
                $user->next_age = $birthday->year - $user->birth_date->year;

                return $user;
            })
            ->sortBy('days_until_birthday')
            ->take(8);

        $activeAbsences = AbsenceRequest::with('user')
            ->where('status', 'approved')
            ->when(
                $hasAbsenceTimes,
                fn ($query) => $query
                    ->where(function ($period) {
                        $period->where('ends_at', '>=', now())
                            ->orWhere(fn ($legacy) => $legacy->whereNull('ends_at')->whereDate('ends_on', '>=', today()));
                    })
                    ->orderByRaw('COALESCE(starts_at, starts_on)'),
                fn ($query) => $query->whereDate('ends_on', '>=', today())->orderBy('starts_on')
            )
            ->get();

        return view('staff.hr', [
            'applications' => $applications,
            'staff' => $staff,
            'upcomingBirthdays' => $upcomingBirthdays,
            'activeAbsences' => $activeAbsences,
            'calendarItems' => $this->calendarItems($activeAbsences, $upcomingBirthdays),
            'showArchive' => $showArchive,
            'applicationCounts' => Application::query()
                ->when($hasApplicationArchive, fn ($query) => $query->whereNull('archived_at'))
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status'),
            'archiveCount' => $hasApplicationArchive ? Application::whereNotNull('archived_at')->count() : 0,
            'hasApplicationArchive' => $hasApplicationArchive,
        ]);
    }

    public function updateApplication(Request $request, Application $application): RedirectResponse
    {
        $this->authorizeManagement($request->user());
        $data = $request->validate([
            'status' => ['required', 'in:new,screening,interview,accepted,rejected'],
            'internal_note' => ['nullable', 'string', 'max:3000'],
        ]);

        $update = $data + [
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ];
        if (Schema::hasColumn('applications', 'archived_at')) {
            $update['archived_at'] = in_array($data['status'], ['accepted', 'rejected'], true) ? now() : null;
        }
        $application->update($update);

        return redirect()
            ->route('staff.hr')
            ->with('success', in_array($data['status'], ['accepted', 'rejected'], true)
                ? 'Sollicitatie beoordeeld en naar het archief verplaatst.'
                : 'Sollicitatie bijgewerkt.');
    }

    public function syncDiscordMembers(Request $request, DiscordMemberSyncService $sync): RedirectResponse
    {
        $this->authorizeManagement($request->user());

        try {
            $count = $sync->sync();
        } catch (\RuntimeException $exception) {
            return back()->withErrors(['discord_sync' => $exception->getMessage()]);
        }

        return back()->with('success', $count.' Discord-leden zijn bijgewerkt.');
    }

    private function authorizeManagement(User $user): void
    {
        abort_unless(in_array($user->role->value, ['management', 'owner'], true), 403);
    }

    private function nextBirthday(User $user): Carbon
    {
        $birthday = today()->setDate(today()->year, $user->birth_date->month, $user->birth_date->day);
        if ($birthday->isBefore(today())) {
            $birthday->addYear();
        }

        return $birthday;
    }

    private function calendarItems($absences, $birthdays)
    {
        return $absences->map(function (AbsenceRequest $absence): array {
            $startsAt = $absence->starts_at ?? $absence->starts_on->startOfDay();
            $endsAt = $absence->ends_at ?? $absence->ends_on->endOfDay();

            return [
                'type' => 'absence',
                'date' => $startsAt,
                'title' => $absence->user->name.' is niet beschikbaar',
                'meta' => $startsAt->translatedFormat('d M H:i').' - '.$endsAt->translatedFormat('d M H:i'),
                'detail' => $absence->reason,
            ];
        })->concat($birthdays->map(fn (User $user): array => [
            'type' => 'birthday',
            'date' => $user->next_birthday,
            'title' => $user->name.' wordt '.$user->next_age,
            'meta' => $user->days_until_birthday === 0
                ? 'Vandaag'
                : 'Over '.$user->days_until_birthday.' dagen',
            'detail' => 'Verjaardag op '.$user->next_birthday->translatedFormat('d F'),
        ]))->sortBy('date')->values();
    }
}
