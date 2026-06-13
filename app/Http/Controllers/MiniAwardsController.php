<?php

namespace App\Http\Controllers;

use App\Models\AwardEdition;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MiniAwardsController extends Controller
{
    public function index(Request $request): View
    {
        $edition = AwardEdition::where('type', 'mini_awards')
            ->with([
                'rounds' => fn ($query) => $query->orderBy('starts_at'),
                'categories' => fn ($query) => $query
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->withCount(['nominations' => fn ($nominations) => $nominations
                        ->whereIn('status', ['approved', 'finalist', 'winner'])]),
            ])
            ->latest('year')
            ->latest('id')
            ->first();

        $selectedCategory = $edition?->categories->firstWhere('slug', (string) $request->query('categorie'))
            ?? $edition?->categories->first();
        $selectedCategory?->load(['nominations' => fn ($nominations) => $nominations
            ->whereIn('status', ['approved', 'finalist', 'winner'])
            ->withCount(['votes' => fn ($votes) => $votes->where('is_valid', true)->whereNull('superseded_at')])
            ->orderByDesc('votes_count')
            ->orderBy('nominee_name')]);

        $activeVoteRound = $edition?->rounds
            ->first(fn ($round) => $round->type === 'public_vote' && $round->isOpen());
        $currentVoteId = null;
        if ($request->user() && $activeVoteRound && $selectedCategory) {
            $currentVoteId = $request->user()->votes()
                ->where('round_id', $activeVoteRound->id)
                ->whereNull('superseded_at')
                ->whereHas('nomination', fn ($query) => $query->where('award_category_id', $selectedCategory->id))
                ->value('nomination_id');
        }

        $currentRound = $edition?->rounds
            ->first(fn ($round) => $round->starts_at->lte(now()) && $round->ends_at->gte(now()));
        $nextRound = $edition?->rounds->first(fn ($round) => $round->starts_at->isFuture());
        $phaseDeadline = $currentRound?->ends_at ?? $nextRound?->starts_at;
        $phaseLabels = [
            'nomination' => ['Mini-nominaties geopend', 'Stemronde start'],
            'public_vote' => ['Mini-stemronde geopend', 'Uitslag volgt'],
        ];
        $phaseStatus = $currentRound
            ? ($phaseLabels[$currentRound->type] ?? [ucfirst($currentRound->type), 'Volgende fase'])
            : null;

        return view('mini-awards.index', compact(
            'edition',
            'selectedCategory',
            'activeVoteRound',
            'currentVoteId',
            'currentRound',
            'nextRound',
            'phaseDeadline',
            'phaseStatus'
        ));
    }

    public function archive(): View
    {
        $editions = AwardEdition::where('type', 'mini_awards')
            ->whereIn('status', ['published', 'archived'])
            ->with(['categories' => fn ($query) => $query
                ->with(['nominations' => fn ($nominations) => $nominations
                    ->where('status', 'winner')
                    ->orderBy('nominee_name')])
                ->orderBy('sort_order')])
            ->latest('year')
            ->latest('id')
            ->get();

        return view('mini-awards.archive', compact('editions'));
    }
}
