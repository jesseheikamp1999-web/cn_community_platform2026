<?php

namespace App\Services;

use App\Models\AwardCategory;
use App\Models\AwardEdition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class LegacyAwardsSyncService
{
    public function run(): array
    {
        $counts = ['categories' => 0, 'nominations' => 0, 'votes' => 0, 'jury_scores' => 0, 'winners' => 0];
        if (!Schema::hasTable('submissions') || !Schema::hasTable('submission_categories')) {
            return $counts;
        }

        $edition = AwardEdition::firstOrCreate(
            ['slug' => 'cn-awards-2026'],
            ['name' => 'CN Awards 2026', 'type' => 'cn_awards', 'year' => 2026, 'status' => 'nominations']
        );
        if ($edition->status === 'draft') {
            $edition->update(['status' => 'nominations']);
        }

        $this->ensureRound($edition->id, 'nomination', 'Nominaties 2026', $edition->status === 'nominations');
        $voteRoundId = $this->ensureRound($edition->id, 'public_vote', 'Publieksstemmen 2026', false);
        $categoryMap = $this->syncCategories($edition, $counts);
        $usersByLegacyId = $this->mapLegacyUsers();
        $nominationMap = $this->syncNominations($categoryMap, $usersByLegacyId, $counts);

        $this->syncVotes($nominationMap, $usersByLegacyId, $voteRoundId, $counts);
        $this->syncJuryScores($nominationMap, $usersByLegacyId, $counts);
        $this->syncWinners($nominationMap, $categoryMap, $counts);

        return $counts;
    }

    private function ensureRound(int $editionId, string $type, string $name, bool $active): int
    {
        $roundId = DB::table('award_rounds')
            ->where('award_edition_id', $editionId)
            ->where('type', $type)
            ->value('id');
        $values = [
            'name' => $name,
            'starts_at' => '2026-01-01 00:00:00',
            'ends_at' => '2026-12-31 23:59:59',
            'is_active' => $active,
            'updated_at' => now(),
        ];

        if ($roundId) {
            DB::table('award_rounds')->where('id', $roundId)->update($values);

            return (int) $roundId;
        }

        return DB::table('award_rounds')->insertGetId($values + [
            'award_edition_id' => $editionId,
            'type' => $type,
            'created_at' => now(),
        ]);
    }

    private function syncCategories(AwardEdition $edition, array &$counts): array
    {
        $categoryMap = [];
        if (!Schema::hasTable('categories')) {
            return $categoryMap;
        }

        foreach (DB::table('categories')->orderBy('sort_order')->get() as $row) {
            $category = AwardCategory::updateOrCreate(
                ['award_edition_id' => $edition->id, 'slug' => Str::slug($row->name)],
                [
                    'name' => $row->name,
                    'description' => $row->description ?: null,
                    'icon' => $row->icon ?: null,
                    'sort_order' => (int) $row->sort_order,
                    'is_active' => (bool) $row->is_active,
                ]
            );
            $categoryMap[(int) $row->id] = $category->id;
            $counts['categories']++;
        }

        return $categoryMap;
    }

    private function mapLegacyUsers(): array
    {
        $users = [];
        if (!Schema::hasTable('legacy_users')) {
            return $users;
        }

        foreach (DB::table('legacy_users')->get(['id', 'discord_id']) as $legacyUser) {
            $users[(int) $legacyUser->id] = DB::table('users')
                ->where('discord_id', (string) $legacyUser->discord_id)
                ->value('id');
        }

        return $users;
    }

    private function syncNominations(array $categoryMap, array $usersByLegacyId, array &$counts): array
    {
        $nominationMap = [];
        $links = DB::table('submission_categories')->get()->groupBy('submission_id');

        foreach (DB::table('submissions')->orderBy('id')->get() as $submission) {
            $userId = $this->userId((int) $submission->user_id, $usersByLegacyId);
            if (!$userId) {
                continue;
            }

            foreach ($links->get($submission->id, collect()) as $link) {
                $categoryId = $categoryMap[(int) $link->category_id] ?? null;
                if (!$categoryId) {
                    continue;
                }
                $attributes = [
                    'award_category_id' => $categoryId,
                    'user_id' => $userId,
                    'nominee_name' => $submission->title,
                ];
                $nominationId = DB::table('nominations')->where($attributes)->value('id');
                if (!$nominationId) {
                    $nominationId = DB::table('nominations')->insertGetId($attributes + [
                        'motivation' => $submission->description,
                        'logo_url' => $submission->logo_url ?? null,
                        'banner_url' => $submission->banner_url ?? null,
                        'website_url' => $submission->website_url ?? null,
                        'discord_invite' => $submission->discord_invite ?? null,
                        'is_verified' => (bool) ($submission->is_verified ?? false),
                        'status' => in_array($submission->status, ['pending', 'approved', 'rejected'], true)
                            ? $submission->status
                            : 'pending',
                        'reviewed_at' => $submission->status === 'pending' ? null : $submission->updated_at,
                        'created_at' => $submission->created_at,
                        'updated_at' => $submission->updated_at,
                    ]);
                    $counts['nominations']++;
                }
                $nominationMap[(int) $submission->id.'-'.(int) $link->category_id] = (int) $nominationId;
            }
        }

        return $nominationMap;
    }

    private function syncVotes(array $nominationMap, array $usersByLegacyId, int $roundId, array &$counts): void
    {
        if (!Schema::hasTable('legacy_votes')) {
            return;
        }

        foreach (DB::table('legacy_votes')->orderBy('id')->get() as $row) {
            $nominationId = $nominationMap[(int) $row->submission_id.'-'.(int) $row->category_id] ?? null;
            $userId = $this->userId((int) $row->user_id, $usersByLegacyId);
            if (!$nominationId || !$userId) {
                continue;
            }
            DB::table('votes')->updateOrInsert(
                ['user_id' => $userId, 'round_id' => $roundId, 'nomination_id' => $nominationId],
                [
                    'ip_hash' => $row->ip_hash ?: hash('sha256', 'legacy-'.$row->id),
                    'user_agent_hash' => hash('sha256', (string) ($row->user_agent ?? 'legacy')),
                    'fraud_score' => 0,
                    'is_valid' => true,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->created_at,
                ]
            );
            $counts['votes']++;
        }
    }

    private function syncJuryScores(array $nominationMap, array $usersByLegacyId, array &$counts): void
    {
        if (!Schema::hasTable('legacy_jury_scores')) {
            return;
        }

        foreach (DB::table('legacy_jury_scores')->get() as $row) {
            $nominationId = collect($nominationMap)->first(
                fn ($id, $key) => str_starts_with((string) $key, (int) $row->submission_id.'-')
            );
            $juryId = $this->userId((int) $row->user_id, $usersByLegacyId);
            if (!$nominationId || !$juryId) {
                continue;
            }
            $scores = [
                (int) $row->originality_score,
                (int) $row->activity_score,
                (int) $row->design_score,
                (int) $row->community_score,
                (int) $row->professionalism_score,
            ];
            DB::table('jury_scores')->updateOrInsert(
                ['nomination_id' => $nominationId, 'jury_id' => $juryId],
                [
                    'score' => array_sum($scores) / count($scores),
                    'originality_score' => $scores[0],
                    'activity_score' => $scores[1],
                    'design_score' => $scores[2],
                    'community_score' => $scores[3],
                    'professionalism_score' => $scores[4],
                    'report' => $row->note ?? null,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ]
            );
            $counts['jury_scores']++;
        }
    }

    private function syncWinners(array $nominationMap, array $categoryMap, array &$counts): void
    {
        if (!Schema::hasTable('winners')) {
            return;
        }

        foreach (DB::table('winners')->get() as $row) {
            $nominationId = $nominationMap[(int) $row->submission_id.'-'.(int) $row->category_id] ?? null;
            $categoryId = $categoryMap[(int) $row->category_id] ?? null;
            if (!$nominationId || !$categoryId) {
                continue;
            }
            DB::table('award_winners')->updateOrInsert(
                ['award_category_id' => $categoryId, 'nomination_id' => $nominationId],
                [
                    'final_score' => $row->final_score,
                    'position' => 1,
                    'revealed_at' => $row->revealed ? $row->generated_at : null,
                    'published_at' => $row->revealed ? $row->generated_at : null,
                    'created_at' => $row->generated_at,
                    'updated_at' => $row->generated_at,
                ]
            );
            if ($row->revealed) {
                DB::table('nominations')->where('id', $nominationId)->update(['status' => 'winner']);
            }
            $counts['winners']++;
        }
    }

    private function userId(int $legacyId, array $usersByLegacyId): ?int
    {
        $userId = $usersByLegacyId[$legacyId] ?? null;
        if ($userId) {
            return (int) $userId;
        }

        return DB::table('users')->where('id', $legacyId)->exists() ? $legacyId : null;
    }
}
