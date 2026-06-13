<?php

namespace App\Http\Controllers;

use App\Models\AwardEdition;
use App\Models\Nomination;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AwardsController extends Controller
{
    public function index(Request $request): View
    {
        $edition = AwardEdition::where('type', 'cn_awards')
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
        $currentRound = $edition?->rounds->first(fn ($round) => $round->starts_at->lte(now()) && $round->ends_at->gte(now()));
        $nextRound = $edition?->rounds->first(fn ($round) => $round->starts_at->isFuture());
        $phaseDeadline = $currentRound?->ends_at
            ?? ($edition?->status === 'finale' ? $edition?->finale_at : $nextRound?->starts_at);
        $phaseLabels = [
            'nomination' => ['Nominaties geopend', 'Stemronde start'],
            'public_vote' => ['Stemronde geopend', 'Juryfase start'],
            'jury' => ['Jurybeoordeling', 'Finale start'],
            'finale' => ['Finale begonnen', 'Winnaars worden gepubliceerd'],
        ];
        $phaseType = $currentRound?->type ?? ($edition?->status === 'finale' ? 'finale' : null);
        $phaseStatus = $phaseType ? ($phaseLabels[$phaseType] ?? [ucfirst($phaseType), 'Volgende fase']) : null;

        return view('awards.index', compact(
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

    public function nomination(Request $request, Nomination $nomination): View
    {
        abort_unless(in_array($nomination->status, ['approved', 'finalist', 'winner'], true), 404);
        $nomination->load(['category.edition', 'user', 'juryScores']);
        $validVotes = $nomination->votes()->where('is_valid', true)->whereNull('superseded_at')->count();
        $juryScore = round((float) $nomination->juryScores()->avg('score'), 1);
        $activeVoteRound = $nomination->category->edition->rounds()
            ->where('type', 'public_vote')
            ->get()
            ->first(fn ($round) => $round->isOpen());
        $hasVoted = $request->user() && $activeVoteRound
            ? $request->user()->votes()
                ->where('round_id', $activeVoteRound->id)
                ->where('nomination_id', $nomination->id)
                ->whereNull('superseded_at')
                ->exists()
            : false;

        return view('awards.nomination', compact('nomination', 'validVotes', 'juryScore', 'activeVoteRound', 'hasVoted'));
    }

    public function finale(): View
    {
        $edition = AwardEdition::where('type', 'cn_awards')->latest('year')->firstOrFail();
        $finalists = DB::table('award_winners')
            ->join('nominations', 'nominations.id', '=', 'award_winners.nomination_id')
            ->join('award_categories', 'award_categories.id', '=', 'award_winners.award_category_id')
            ->where('award_categories.award_edition_id', $edition->id)
            ->select('award_winners.*', 'nominations.nominee_name', 'nominations.motivation', 'nominations.logo_url', 'award_categories.name as category_name')
            ->orderBy('award_categories.sort_order')
            ->orderByDesc('award_winners.position')
            ->get()
            ->groupBy('category_name');

        return view('awards.finale', compact('edition', 'finalists'));
    }

    public function hallOfFame(): View
    {
        $years = DB::table('award_winners')
            ->join('nominations', 'nominations.id', '=', 'award_winners.nomination_id')
            ->join('award_categories', 'award_categories.id', '=', 'award_winners.award_category_id')
            ->join('award_editions', 'award_editions.id', '=', 'award_categories.award_edition_id')
            ->where('award_winners.position', 1)
            ->whereNotNull('award_winners.published_at')
            ->select('award_winners.*', 'nominations.nominee_name', 'nominations.logo_url', 'award_categories.name as category_name', 'award_editions.year')
            ->orderByDesc('award_editions.year')
            ->orderBy('award_categories.sort_order')
            ->get()
            ->groupBy('year');

        return view('awards.hall-of-fame', compact('years'));
    }
}
