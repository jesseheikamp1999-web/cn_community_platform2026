<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\AbsenceRequest;
use App\Models\Application;
use App\Models\User;
use App\Services\DiscordMemberSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HrController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeManagement($request->user());
        $status = $request->string('status')->toString();

        $applications = Application::with(['user', 'reviewer'])
            ->when(in_array($status, ['new', 'screening', 'interview', 'accepted', 'rejected'], true), fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $staff = User::whereNot('role', 'member')
            ->with('staffProfile')
            ->withExists(['absenceRequests as is_currently_absent' => fn ($query) => $query
                ->where('status', 'approved')
                ->whereDate('starts_on', '<=', today())
                ->whereDate('ends_on', '>=', today())])
            ->orderByRaw("CASE role WHEN 'owner' THEN 1 WHEN 'management' THEN 2 WHEN 'admin' THEN 3 WHEN 'moderator' THEN 4 WHEN 'helper' THEN 5 ELSE 6 END")
            ->orderBy('name')
            ->get();

        $upcomingBirthdays = User::whereNotNull('birth_date')
            ->whereIn('birthday_visibility', ['staff', 'community'])
            ->get()
            ->sortBy(fn (User $user) => $this->birthdayDistance($user))
            ->take(8);

        return view('staff.hr', [
            'applications' => $applications,
            'staff' => $staff,
            'upcomingBirthdays' => $upcomingBirthdays,
            'activeAbsences' => AbsenceRequest::with('user')
                ->where('status', 'approved')
                ->whereDate('ends_on', '>=', today())
                ->orderBy('starts_on')
                ->get(),
            'applicationCounts' => Application::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
        ]);
    }

    public function updateApplication(Request $request, Application $application): RedirectResponse
    {
        $this->authorizeManagement($request->user());
        $data = $request->validate([
            'status' => ['required', 'in:new,screening,interview,accepted,rejected'],
            'internal_note' => ['nullable', 'string', 'max:3000'],
        ]);

        $application->update($data + [
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return back()->with('success', 'Sollicitatie bijgewerkt.');
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

    private function birthdayDistance(User $user): int
    {
        $birthday = today()->setDate(today()->year, $user->birth_date->month, $user->birth_date->day);
        if ($birthday->isBefore(today())) {
            $birthday->addYear();
        }

        return (int) today()->diffInDays($birthday);
    }
}
