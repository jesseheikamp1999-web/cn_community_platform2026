<?php

namespace App\Services;

use App\Models\JuryScore;
use App\Models\Nomination;
use App\Models\User;

class JuryService
{
    public function save(User $jury, Nomination $nomination, array $scores, ?string $report): JuryScore
    {
        $reportText = trim(($scores['strengths'] ?? '').' '.($scores['improvements'] ?? '').' '.($scores['personal_note'] ?? '').' '.($report ?? ''));
        if (str_word_count($reportText) < 100) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'report' => 'Een juryrapport moet minimaal 100 woorden bevatten.',
            ]);
        }

        $dimensions = collect([
            'impact_score',
            'activity_score',
            'professionalism_score',
            'innovation_score',
            'future_score',
        ])->mapWithKeys(fn ($key) => [$key => max(0, min(10, (int) $scores[$key]))]);
        $total = $dimensions->sum();

        return JuryScore::updateOrCreate(
            ['nomination_id' => $nomination->id, 'jury_id' => $jury->id],
            $dimensions->all() + [
                'score' => round($total / 50 * 100, 2),
                'community_score' => $dimensions['impact_score'],
                'originality_score' => $dimensions['innovation_score'],
                'design_score' => $dimensions['professionalism_score'],
                'strengths' => $scores['strengths'] ?? null,
                'improvements' => $scores['improvements'] ?? null,
                'personal_note' => $scores['personal_note'] ?? null,
                'report' => $report,
            ]
        );
    }
}
