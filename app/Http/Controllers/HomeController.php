<?php

namespace App\Http\Controllers;

use App\Models\AwardEdition;
use App\Models\Nomination;
use App\Models\Partner;
use App\Models\User;
use App\Repositories\ContentRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HomeController extends Controller
{
    public function __invoke(ContentRepository $content)
    {
        $partners = Partner::where('status', 'active');
        if (Schema::hasColumn('partners', 'is_featured')) {
            $partners->where('is_featured', true)
                ->orderBy('position')
                ->orderByDesc('score');
        } else {
            $partners->orderBy('name');
        }

        return view('home', [
            'news' => $content->latestNews(3),
            'edition' => AwardEdition::where('type', 'cn_awards')->latest('year')->first(),
            'winners' => Nomination::where('status', 'winner')->with('category')->latest('updated_at')->limit(4)->get(),
            'partners' => $partners->limit(Schema::hasColumn('partners', 'is_featured') ? 10 : 6)->get(),
            'staff' => User::whereIn('role', ['helper', 'moderator', 'admin', 'management', 'owner'])
                ->with('staffProfile')
                ->withExists(['absenceRequests as is_currently_absent' => fn ($query) => $query->current()])
                ->orderByRaw("CASE role
                    WHEN 'owner' THEN 1
                    WHEN 'management' THEN 2
                    WHEN 'admin' THEN 3
                    WHEN 'moderator' THEN 4
                    WHEN 'helper' THEN 5
                    ELSE 6 END")
                ->limit(4)
                ->get(),
            'stats' => [
                'members' => Schema::hasTable('discord_members')
                    ? DB::table('discord_members')->where('is_active', true)->where('is_bot', false)->count()
                    : User::whereNotNull('discord_id')->where('discord_id', '!=', '')->count(),
                'votes' => DB::table('votes')->where('is_valid', true)->count(),
                'awards' => DB::table('award_winners')->count(),
                'lessons' => DB::table('lessons')->count(),
            ],
        ]);
    }
}
