<?php

namespace App\Services;

use App\Models\AwardCategory;
use App\Models\Content;
use Illuminate\Support\Facades\DB;

class PublishingService
{
    public function publishDueContent(): int
    {
        return Content::query()
            ->where('status', 'scheduled')
            ->where('published_at', '<=', now())
            ->update(['status' => 'published', 'updated_at' => now()]);
    }

    public function generateWinners(int $editionId): void
    {
        DB::transaction(function () use ($editionId) {
            $categoryIds = AwardCategory::where('award_edition_id', $editionId)->pluck('id');
            DB::table('award_winners')->whereIn('award_category_id', $categoryIds)->delete();

            AwardCategory::where('award_edition_id', $editionId)->each(function (AwardCategory $category) {
                $nominations = $category->nominations()
                    ->whereIn('status', ['approved', 'finalist'])
                    ->withCount(['votes as valid_votes' => fn ($query) => $query->where('is_valid', true)->whereNull('superseded_at')])
                    ->withAvg('juryScores', 'score')
                    ->with('juryScores')
                    ->get();
                $maxVotes = max(1, (int) $nominations->max('valid_votes'));
                $ranked = $nominations
                    ->map(function ($nomination) use ($category, $maxVotes) {
                        $communityScore = round($nomination->valid_votes / $maxVotes * 100, 3);
                        $juryScore = round((float) ($nomination->jury_scores_avg_score ?? 0), 3);
                        $finalScore = round(($communityScore * $category->public_weight / 100) + ($juryScore * $category->jury_weight / 100), 3);

                        return [
                            'nomination_id' => $nomination->id,
                            'community_score' => $communityScore,
                            'jury_score' => $juryScore,
                            'score' => $finalScore,
                            'highlights' => $nomination->juryScores
                                ->pluck('strengths')
                                ->filter()
                                ->take(3)
                                ->values()
                                ->all(),
                        ];
                    })
                    ->sortByDesc('score')
                    ->take((int) ($category->edition->settings['finale']['finalists_count'] ?? 5))
                    ->values();

                $category->nominations()->where('status', 'finalist')->update(['status' => 'approved']);
                foreach ($ranked as $index => $result) {
                    DB::table('award_winners')->insert([
                        'award_category_id' => $category->id,
                        'nomination_id' => $result['nomination_id'],
                        'final_score' => $result['score'],
                        'community_score' => $result['community_score'],
                        'jury_score' => $result['jury_score'],
                        'position' => $index + 1,
                        'jury_highlights' => json_encode($result['highlights']),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    DB::table('nominations')->where('id', $result['nomination_id'])->update([
                        'status' => 'finalist',
                        'updated_at' => now(),
                    ]);
                }
            });
        });
    }
}
