<?php

namespace App\Http\Controllers;

use App\Models\AwardEdition;
use App\Models\Nomination;
use App\Models\Partner;
use App\Models\User;
use App\Repositories\ContentRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class HomeController extends Controller
{
    public function __invoke(ContentRepository $content)
    {
        $locale = app()->getLocale();
        $localized = fn (string $route, array $parameters = []) => route($route, ['locale' => $locale] + $parameters);
        $safe = function (callable $resolver, mixed $fallback) {
            try {
                return $resolver();
            } catch (Throwable) {
                return $fallback;
            }
        };

        $divisionCards = [
            [
                'eyebrow' => 'Connect Next AI',
                'title' => $locale === 'en' ? 'AI strategy, automation and premium content.' : 'AI-strategie, automatisering en premium content.',
                'description' => $locale === 'en'
                    ? 'Consulting, prompt engineering, AI content, voice, photo and workflow automation.'
                    : 'Consulting, prompt engineering, AI-content, voice, fotografie en workflow-automatisering.',
                'url' => $localized('ai'),
            ],
            [
                'eyebrow' => 'Connect Next Development',
                'title' => $locale === 'en' ? 'Custom software that moves with your business.' : 'Maatwerksoftware die met je bedrijf meebeweegt.',
                'description' => $locale === 'en'
                    ? 'Websites, SaaS, portals, dashboards, apps and integrations built for growth.'
                    : 'Websites, SaaS, portals, dashboards, apps en integraties gebouwd voor groei.',
                'url' => $localized('development'),
            ],
            [
                'eyebrow' => 'Connect Next Communities',
                'title' => $locale === 'en' ? 'Communities, events and partnerships with real momentum.' : 'Communities, events en partnerships met echte energie.',
                'description' => $locale === 'en'
                    ? 'The CN Community platform continues under a stronger global brand while keeping its heart.'
                    : 'Het CN Community-platform leeft door onder een sterker internationaal merk, zonder de ziel te verliezen.',
                'url' => $localized('communities'),
            ],
            [
                'eyebrow' => 'Connect Next Awards',
                'title' => $locale === 'en' ? 'A premium annual event for recognition and reputation.' : 'Een premium jaarlijks event voor erkenning en reputatie.',
                'description' => $locale === 'en'
                    ? 'Nominations, voting, jury scoring and finalist experiences that feel event-worthy.'
                    : 'Nominaties, stemmen, jurybeoordeling en finalistprofielen die voelen als een echt event.',
                'url' => $localized('awards'),
            ],
        ];

        $partners = $safe(function () {
            $query = Partner::where('status', 'active');

            if (Schema::hasColumn('partners', 'is_featured')) {
                $query->where('is_featured', true)
                    ->orderBy('position')
                    ->orderByDesc('score');
            } else {
                $query->orderBy('name');
            }

            return $query->limit(Schema::hasColumn('partners', 'is_featured') ? 10 : 6)->get();
        }, collect());

        $staff = $safe(function () {
            return User::whereIn('role', ['helper', 'moderator', 'admin', 'management', 'owner'])
                ->with(['staffProfile', 'currentAbsence'])
                ->withExists(['absenceRequests as is_currently_absent' => fn ($query) => $query->current()])
                ->orderByRaw("CASE role
                    WHEN 'owner' THEN 1
                    WHEN 'management' THEN 2
                    WHEN 'admin' THEN 3
                    WHEN 'moderator' THEN 4
                    WHEN 'helper' THEN 5
                    ELSE 6 END")
                ->limit(4)
                ->get();
        }, collect());

        $winners = $safe(function () {
            if (! Schema::hasTable('nominations')) {
                return collect();
            }

            return Nomination::where('status', 'winner')
                ->with('category')
                ->latest('updated_at')
                ->limit(4)
                ->get();
        }, collect());

        $edition = $safe(function () {
            if (! Schema::hasTable('award_editions')) {
                return null;
            }

            return AwardEdition::where('type', 'cn_awards')->latest('year')->first();
        }, null);

        $stats = $safe(function () {
            return [
                'members' => Schema::hasTable('discord_members')
                    ? DB::table('discord_members')->where('is_active', true)->where('is_bot', false)->count()
                    : User::whereNotNull('discord_id')->where('discord_id', '!=', '')->count(),
                'votes' => Schema::hasTable('votes')
                    ? DB::table('votes')->where('is_valid', true)->count()
                    : 0,
                'awards' => Schema::hasTable('award_winners')
                    ? DB::table('award_winners')->count()
                    : 0,
                'projects' => Schema::hasTable('partners')
                    ? Partner::where('status', 'active')->count()
                    : 0,
            ];
        }, [
            'members' => 0,
            'votes' => 0,
            'awards' => 0,
            'projects' => 0,
        ]);

        return view('home', [
            'news' => $content->latestNews(3),
            'edition' => $edition,
            'winners' => $winners,
            'partners' => $partners,
            'staff' => $staff,
            'divisionCards' => $divisionCards,
            'testimonials' => [
                [
                    'quote' => $locale === 'en'
                        ? 'Connect Next combines technical clarity with brand feeling. It never feels generic.'
                        : 'Connect Next combineert technische scherpte met merkgevoel. Het voelt nooit generiek.',
                    'name' => 'StudioNova',
                    'role' => $locale === 'en' ? 'Creative Partner' : 'Creative partner',
                ],
                [
                    'quote' => $locale === 'en'
                        ? 'The awards experience already feels bigger, cleaner and more trustworthy.'
                        : 'De awardervaring voelt nu al groter, schoner en betrouwbaarder.',
                    'name' => 'CN Awards Jury',
                    'role' => $locale === 'en' ? 'Platform feedback' : 'Platform feedback',
                ],
                [
                    'quote' => $locale === 'en'
                        ? 'From community to software thinking: this is the kind of platform you can build on for years.'
                        : 'Van community tot softwaredenken: dit is het soort platform waarop je jaren kunt doorbouwen.',
                    'name' => 'Cloud86',
                    'role' => $locale === 'en' ? 'Infrastructure partner' : 'Infrastructuurpartner',
                ],
            ],
            'stats' => $stats,
        ]);
    }
}
