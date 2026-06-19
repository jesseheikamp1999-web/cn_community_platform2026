<?php

namespace App\Http\Controllers;

use App\Models\AwardEdition;
use App\Models\Content;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class PageController extends Controller
{
    public function show(string $page)
    {
        abort_unless(in_array($page, ['awards', 'mini-awards', 'nieuws', 'partners', 'staff', 'contact', 'solliciteren', 'partner-worden']), 404);

        $data = match ($page) {
            'awards' => ['items' => AwardEdition::where('type', 'cn_awards')->latest('year')->get()],
            'mini-awards' => ['items' => AwardEdition::where('type', 'mini_awards')->latest('year')->get()],
            'nieuws' => ['items' => Content::published()->where('type', 'news')->latest('published_at')->paginate(9)],
            'partners' => ['items' => tap(Partner::where('status', 'active'), function ($query): void {
                Schema::hasColumn('partners', 'position')
                    ? $query->orderBy('position')->orderByDesc('score')
                    : $query->orderBy('name');
            })->get()],
            'staff' => ['items' => User::whereNot('role', 'member')
                ->with(['staffProfile', 'currentAbsence'])
                ->withExists(['absenceRequests as is_currently_absent' => fn ($query) => $query->current()])
                ->when(request('team'), function ($query, string $team): void {
                    $roles = match ($team) {
                        'management' => ['owner', 'management', 'admin'],
                        'moderatie' => ['moderator'],
                        'jury' => ['jury'],
                        'helpers' => ['helper'],
                        default => [],
                    };
                    if ($roles !== []) {
                        $query->whereIn('role', $roles);
                    }
                })
                ->orderByRaw("CASE role
                    WHEN 'owner' THEN 1
                    WHEN 'management' THEN 2
                    WHEN 'admin' THEN 3
                    WHEN 'moderator' THEN 4
                    WHEN 'helper' THEN 5
                    WHEN 'jury' THEN 6
                    WHEN 'partner_manager' THEN 7
                    ELSE 8 END")
                ->orderBy('name')
                ->get(),
                'teamMemberOfMonth' => Schema::hasColumn('staff_profiles', 'is_team_member_of_month')
                    ? User::whereNot('role', 'member')
                    ->with(['staffProfile', 'currentAbsence'])
                    ->whereHas('staffProfile', fn ($query) => $query->where('is_team_member_of_month', true))
                    ->first()
                    : null,
                'staffFilters' => [
                    'all' => 'Iedereen',
                    'management' => 'Management',
                    'moderatie' => 'Moderatie',
                    'jury' => 'Jury',
                    'helpers' => 'Helpers',
                ]],
            default => ['items' => collect()],
        };

        return view('pages.generic', compact('page') + $data);
    }

    public function search(Request $request)
    {
        $query = trim((string) $request->string('q'));
        $results = strlen($query) >= 2
            ? Content::published()->where(fn ($q) => $q->where('title', 'like', "%{$query}%")->orWhere('body', 'like', "%{$query}%"))->limit(20)->get()
            : collect();

        return view('pages.search', compact('query', 'results'));
    }
}
