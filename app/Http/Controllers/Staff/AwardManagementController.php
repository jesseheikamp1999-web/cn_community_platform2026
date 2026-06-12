<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\AwardEdition;
use App\Models\AwardRound;
use App\Models\JuryScore;
use App\Models\Nomination;
use App\Services\JuryService;
use App\Services\PublishingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AwardManagementController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('awards.review') || $request->user()->hasPermission('jury.score'), 403);

        $edition = AwardEdition::where('type', 'cn_awards')
            ->with(['rounds' => fn ($query) => $query->orderBy('starts_at'), 'categories' => fn ($query) => $query->orderBy('sort_order')])
            ->latest('year')
            ->firstOrFail();
        $allNominations = Nomination::with(['category', 'user', 'juryScores'])
            ->whereHas('category', fn ($query) => $query->where('award_edition_id', $edition->id))
            ->orderByRaw("CASE status WHEN 'pending' THEN 1 WHEN 'approved' THEN 2 WHEN 'finalist' THEN 3 ELSE 4 END")
            ->latest()
            ->get();
        $nominations = $allNominations->where('status', 'pending')->values();
        $juryNominations = $allNominations->whereIn('status', ['approved', 'finalist'])->values();
        $stats = [
            'nominations' => $allNominations->count(),
            'unique_nominators' => $allNominations->pluck('user_id')->unique()->count(),
            'votes' => DB::table('votes')->whereNull('superseded_at')->where('is_valid', true)->count(),
            'finalists' => $allNominations->where('status', 'finalist')->count(),
            'jury_members' => DB::table('award_jury_assignments')->where('award_edition_id', $edition->id)->distinct('user_id')->count('user_id'),
        ];
        $myScores = JuryScore::where('jury_id', $request->user()->id)->get()->keyBy('nomination_id');
        $winners = DB::table('award_winners')
            ->join('nominations', 'nominations.id', '=', 'award_winners.nomination_id')
            ->join('award_categories', 'award_categories.id', '=', 'award_winners.award_category_id')
            ->where('award_categories.award_edition_id', $edition->id)
            ->select('award_winners.*', 'nominations.nominee_name', 'award_categories.name as category_name')
            ->orderBy('award_categories.sort_order')
            ->orderByDesc('award_winners.position')
            ->get();

        return view('staff.awards', compact('edition', 'nominations', 'juryNominations', 'myScores', 'stats', 'winners'));
    }

    public function updateEdition(Request $request, AwardEdition $edition): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('awards.manage'), 403);
        $data = $request->validate(['status' => ['required', 'in:draft,nominations,voting,jury,finale,published,archived']]);
        $edition->update($data);

        return back()->with('success', 'De Awards-fase is bijgewerkt.');
    }

    public function saveRound(Request $request, AwardEdition $edition): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('awards.manage'), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', 'in:nomination,public_vote,jury,finale'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $data['is_active'] = $request->boolean('is_active');
        $edition->rounds()->updateOrCreate(['type' => $data['type']], $data);

        return back()->with('success', 'De ronde is opgeslagen.');
    }

    public function score(Request $request, Nomination $nomination, JuryService $jury): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('jury.score'), 403);
        abort_unless(in_array($nomination->status, ['approved', 'finalist'], true), 422);
        $data = $request->validate([
            'impact_score' => ['required', 'integer', 'between:0,10'],
            'activity_score' => ['required', 'integer', 'between:0,10'],
            'professionalism_score' => ['required', 'integer', 'between:0,10'],
            'innovation_score' => ['required', 'integer', 'between:0,10'],
            'future_score' => ['required', 'integer', 'between:0,10'],
            'strengths' => ['required', 'string', 'max:3000'],
            'improvements' => ['required', 'string', 'max:3000'],
            'personal_note' => ['required', 'string', 'max:3000'],
            'report' => ['nullable', 'string', 'max:3000'],
        ]);
        $jury->save($request->user(), $nomination, $data, $data['report'] ?? null);

        return back()->with('success', 'Jurybeoordeling opgeslagen.');
    }

    public function review(Request $request, Nomination $nomination): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('awards.review'), 403);
        $data = $request->validate([
            'status' => ['required', 'in:approved,rejected,duplicate'],
            'review_note' => ['nullable', 'string', 'max:2000'],
        ]);
        $oldStatus = $nomination->status;
        $nomination->update([
            'status' => $data['status'],
            'review_note' => $data['review_note'] ?? null,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);
        DB::table('nomination_review_logs')->insert([
            'nomination_id' => $nomination->id,
            'user_id' => $request->user()->id,
            'action' => 'review',
            'old_status' => $oldStatus,
            'new_status' => $data['status'],
            'note' => $data['review_note'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Nominatiecontrole opgeslagen.');
    }

    public function revealPosition(Request $request, AwardEdition $edition, int $position): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('awards.manage'), 403);
        abort_unless($position >= 1 && $position <= 5, 404);
        $categoryIds = $edition->categories()->pluck('id');
        DB::table('award_winners')
            ->whereIn('award_category_id', $categoryIds)
            ->where('position', $position)
            ->update([
                'revealed_position_at' => now(),
                'revealed_at' => $position === 1 ? now() : DB::raw('revealed_at'),
                'updated_at' => now(),
            ]);
        if ($position === 1) {
            $winnerIds = DB::table('award_winners')
                ->whereIn('award_category_id', $categoryIds)
                ->where('position', 1)
                ->pluck('nomination_id');
            Nomination::whereIn('id', $winnerIds)->update(['status' => 'winner']);
        }

        return back()->with('success', 'Reveal positie '.$position.' is vrijgegeven.');
    }

    public function generateWinners(Request $request, AwardEdition $edition, PublishingService $publishing): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('awards.manage'), 403);
        $publishing->generateWinners($edition->id);
        $edition->update(['status' => 'finale']);

        return back()->with('success', 'Finalisten en winnaars zijn opnieuw berekend.');
    }

    public function publishWinners(Request $request, AwardEdition $edition): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('awards.manage'), 403);
        $categoryIds = $edition->categories()->pluck('id');
        $winnerIds = \Illuminate\Support\Facades\DB::table('award_winners')
            ->whereIn('award_category_id', $categoryIds)
            ->where('position', 1)
            ->pluck('nomination_id');
        \Illuminate\Support\Facades\DB::table('award_winners')
            ->whereIn('award_category_id', $categoryIds)
            ->update(['revealed_at' => now(), 'published_at' => now(), 'updated_at' => now()]);
        Nomination::whereIn('id', $winnerIds)->update(['status' => 'winner']);
        $edition->update(['status' => 'published']);

        return back()->with('success', 'De winnaars zijn gepubliceerd.');
    }
}
