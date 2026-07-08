<?php

namespace App\Http\Controllers;

use App\Models\AwardEdition;
use App\Models\Content;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class PageController extends Controller
{
    public function show(string $page): View
    {
        abort_unless(in_array($page, ['ai', 'development', 'communities', 'projects', 'about', 'contact', 'partners', 'staff', 'apply', 'partner']), 404);

        $data = match ($page) {
            'ai' => ['content' => $this->servicePage('ai')],
            'development' => ['content' => $this->servicePage('development')],
            'communities' => ['content' => $this->servicePage('communities'), 'partners' => $this->partnerCollection(), 'staff' => $this->staffCollection()->take(6)],
            'projects' => ['content' => $this->servicePage('projects'), 'items' => $this->partnerCollection()],
            'about' => ['content' => $this->servicePage('about'), 'staff' => $this->staffCollection()],
            'contact' => ['content' => $this->servicePage('contact')],
            'partners' => ['items' => tap(Partner::where('status', 'active'), function ($query): void {
                Schema::hasColumn('partners', 'position')
                    ? $query->orderBy('position')->orderByDesc('score')
                    : $query->orderBy('name');
            })->get(), 'content' => $this->servicePage('projects')],
            'staff' => ['items' => $this->staffCollection(),
                'content' => $this->servicePage('communities'),
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
            'apply' => ['content' => $this->formPage('apply')],
            'partner' => ['content' => $this->formPage('partner')],
            default => ['items' => collect()],
        };

        return view('pages.generic', compact('page') + $data);
    }

    public function search(Request $request): View
    {
        $query = trim((string) $request->string('q'));
        $results = strlen($query) >= 2
            ? Content::published()->where(fn ($q) => $q->where('title', 'like', "%{$query}%")->orWhere('body', 'like', "%{$query}%"))->limit(20)->get()
            : collect();

        return view('pages.search', compact('query', 'results'));
    }

    private function staffCollection()
    {
        return User::whereNot('role', 'member')
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
            ->get();
    }

    private function partnerCollection()
    {
        return tap(Partner::where('status', 'active'), function ($query): void {
            Schema::hasColumn('partners', 'position')
                ? $query->orderBy('position')->orderByDesc('score')
                : $query->orderBy('name');
        })->get();
    }

    private function servicePage(string $key): array
    {
        $copy = __("public.division_pages.$key");

        return match ($key) {
            'ai' => $copy + ['bullets' => [
                'AI Consulting', 'AI Content Creation', 'AI Product Photography', 'AI Video Generation',
                'AI Voiceovers', 'Prompt Engineering', 'AI Automation', 'AI Chatbots', 'SEO & Branding',
            ]],
            'development' => $copy + ['bullets' => [
                'Websites', 'Web Applications', 'Mobile Apps', 'SaaS Platforms',
                'Customer Portals', 'Dashboards', 'API Integrations', 'AI Integrations', 'Automation',
            ]],
            'communities' => $copy + ['bullets' => [
                'Community Management', 'Discord Communities', 'Events', 'Partnerships', 'Growth Strategy', 'Gaming Communities',
            ]],
            'projects' => $copy + ['bullets' => [
                'Premium Platforms', 'Brand Experiences', 'Awards Journeys', 'Community Tools', 'Integrated Dashboards',
            ]],
            'about' => $copy + ['bullets' => [
                'Innovation', 'Simplicity', 'Professionalism', 'Trust', 'Community', 'Technology', 'Growth',
            ]],
            default => $copy + ['bullets' => ['Connect Next']],
        };
    }

    private function formPage(string $key): array
    {
        return match ($key) {
            'apply' => [
                'eyebrow' => app()->getLocale() === 'en' ? 'JOIN THE TEAM' : 'WERK MEE',
                'title' => app()->getLocale() === 'en' ? 'Apply to Connect Next Communities.' : 'Solliciteer bij Connect Next Communities.',
                'description' => app()->getLocale() === 'en'
                    ? 'Tell us where you want to contribute across moderation, operations, community support or events.'
                    : 'Laat ons weten waar jij wilt bijdragen binnen moderatie, operatie, community support of events.',
                'form_type' => 'application',
            ],
            default => [
                'eyebrow' => app()->getLocale() === 'en' ? 'PARTNERSHIP' : 'SAMENWERKEN',
                'title' => app()->getLocale() === 'en' ? 'Become a Connect Next partner.' : 'Word partner van Connect Next.',
                'description' => app()->getLocale() === 'en'
                    ? 'We work with brands, platforms and communities that fit our long-term vision.'
                    : 'We werken samen met merken, platforms en communities die passen bij onze langetermijnvisie.',
                'form_type' => 'partnership',
            ],
        };
    }
}
