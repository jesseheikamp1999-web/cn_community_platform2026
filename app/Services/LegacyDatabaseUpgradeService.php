<?php

namespace App\Services;

use App\Enums\UserRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class LegacyDatabaseUpgradeService
{
    private array $counts = [];

    public function run(): array
    {
        return DB::transaction(function () {
            $this->users();
            [$editionId, $categoryMap] = $this->awards();
            $this->partners();
            $this->tasks();
            $this->badges();
            $this->academy();
            $this->notifications();
            $this->operations();

            return $this->counts + ['edition_id' => $editionId, 'categories' => count($categoryMap)];
        });
    }

    private function users(): void
    {
        $roles = [
            'lid' => UserRole::Member->value,
            'member' => UserRole::Member->value,
            'helper' => UserRole::Helper->value,
            'moderator' => UserRole::Moderator->value,
            'jury' => UserRole::Jury->value,
            'admin' => UserRole::Admin->value,
            'management' => UserRole::Management->value,
            'owner' => UserRole::Owner->value,
        ];

        foreach (DB::table('legacy_users')->orderBy('id')->get() as $row) {
            DB::table('users')->updateOrInsert(['id' => $row->id], [
                'name' => $row->username ?: 'CN-lid',
                'email' => $row->email ?: null,
                'password' => $row->password_hash ?: null,
                'discord_id' => (string) $row->discord_id,
                'discord_username' => $row->username ?: null,
                'discord_avatar' => $row->avatar ?: null,
                'role' => $roles[$row->role] ?? UserRole::Member->value,
                'xp' => $this->legacyXp((int) $row->id),
                'birth_date' => $row->birth_date ?: null,
                'profile_bio' => $row->profile_bio ?: null,
                'birthday_visibility' => $this->visibility($row->birthday_visibility ?? null),
                'birthday_notifications' => (bool) ($row->birthday_notify ?? true),
                'last_seen_at' => $row->updated_at ?: null,
                'created_at' => $row->created_at ?: now(),
                'updated_at' => $row->updated_at ?: now(),
            ]);
            $this->increment('users');
        }
    }

    private function awards(): array
    {
        $editionId = DB::table('award_editions')->where('slug', 'cn-awards-2026')->value('id');
        if (!$editionId) {
            $editionId = DB::table('award_editions')->insertGetId([
                'name' => 'CN Awards 2026',
                'slug' => 'cn-awards-2026',
                'type' => 'cn_awards',
                'year' => 2026,
                'status' => 'nominations',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $categoryMap = [];
        if (Schema::hasTable('categories')) {
            foreach (DB::table('categories')->orderBy('sort_order')->get() as $row) {
                $categoryId = DB::table('award_categories')
                    ->where('award_edition_id', $editionId)
                    ->where('slug', Str::slug($row->name))
                    ->value('id');
                if (!$categoryId) {
                    $categoryId = DB::table('award_categories')->insertGetId([
                        'award_edition_id' => $editionId,
                        'name' => $row->name,
                        'slug' => Str::slug($row->name),
                        'description' => $row->description ?: null,
                        'icon' => $row->icon ?: null,
                        'sort_order' => (int) $row->sort_order,
                        'jury_weight' => 40,
                        'public_weight' => 60,
                        'is_active' => (bool) $row->is_active,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
                $categoryMap[(int) $row->id] = $categoryId;
            }
        }

        $roundId = DB::table('award_rounds')->where('award_edition_id', $editionId)->where('type', 'public_vote')->value('id');
        if (!$roundId) {
            $roundId = DB::table('award_rounds')->insertGetId([
                'award_edition_id' => $editionId,
                'name' => 'Publieksstemmen 2026',
                'type' => 'public_vote',
                'starts_at' => '2026-01-01 00:00:00',
                'ends_at' => '2026-12-31 23:59:59',
                'is_active' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $nominationMap = [];
        if (Schema::hasTable('submissions') && Schema::hasTable('submission_categories')) {
            $links = DB::table('submission_categories')->get()->groupBy('submission_id');
            foreach (DB::table('submissions')->orderBy('id')->get() as $submission) {
                foreach ($links->get($submission->id, collect()) as $index => $link) {
                    if (!isset($categoryMap[(int) $link->category_id]) || !DB::table('users')->where('id', $submission->user_id)->exists()) {
                        continue;
                    }
                    $attributes = [
                        'award_category_id' => $categoryMap[(int) $link->category_id],
                        'user_id' => $submission->user_id,
                        'nominee_name' => $submission->title,
                    ];
                    $nominationId = DB::table('nominations')->where($attributes)->value('id');
                    if (!$nominationId) {
                        $payload = $attributes + [
                            'motivation' => $submission->description,
                            'status' => in_array($submission->status, ['pending', 'approved', 'rejected'], true) ? $submission->status : 'pending',
                            'reviewed_at' => $submission->status === 'pending' ? null : $submission->updated_at,
                            'created_at' => $submission->created_at,
                            'updated_at' => $submission->updated_at,
                        ];
                        if ($index === 0 && !DB::table('nominations')->where('id', $submission->id)->exists()) {
                            $payload['id'] = $submission->id;
                        }
                        $nominationId = DB::table('nominations')->insertGetId($payload);
                    }
                    $nominationMap[(int) $submission->id.'-'.(int) $link->category_id] = $nominationId;
                    $this->increment('nominations');
                }
            }
        }

        if (Schema::hasTable('legacy_votes')) {
            foreach (DB::table('legacy_votes')->orderBy('id')->get() as $row) {
                $nominationId = $nominationMap[(int) $row->submission_id.'-'.(int) $row->category_id] ?? null;
                if (!$nominationId || !DB::table('users')->where('id', $row->user_id)->exists()) {
                    continue;
                }
                DB::table('votes')->updateOrInsert(
                    ['user_id' => $row->user_id, 'round_id' => $roundId, 'nomination_id' => $nominationId],
                    [
                        'ip_hash' => $row->ip_hash,
                        'user_agent_hash' => hash('sha256', (string) $row->user_agent),
                        'fraud_score' => 0,
                        'is_valid' => true,
                        'created_at' => $row->created_at,
                        'updated_at' => $row->created_at,
                    ]
                );
                $this->increment('votes');
            }
        }

        if (Schema::hasTable('legacy_jury_scores')) {
            foreach (DB::table('legacy_jury_scores')->get() as $row) {
                $nominationId = collect($nominationMap)->first(fn ($id, $key) => str_starts_with($key, $row->submission_id.'-'));
                if (!$nominationId || !DB::table('users')->where('id', $row->user_id)->exists()) {
                    continue;
                }
                $scores = [(int) $row->originality_score, (int) $row->activity_score, (int) $row->design_score, (int) $row->community_score, (int) $row->professionalism_score];
                DB::table('jury_scores')->updateOrInsert(
                    ['nomination_id' => $nominationId, 'jury_id' => $row->user_id],
                    [
                        'score' => array_sum($scores) / count($scores),
                        'originality_score' => $scores[0],
                        'activity_score' => $scores[1],
                        'design_score' => $scores[2],
                        'community_score' => $scores[3],
                        'professionalism_score' => $scores[4],
                        'report' => $row->note,
                        'created_at' => $row->created_at,
                        'updated_at' => $row->updated_at,
                    ]
                );
            }
        }

        if (Schema::hasTable('winners')) {
            foreach (DB::table('winners')->get() as $row) {
                $nominationId = $nominationMap[(int) $row->submission_id.'-'.(int) $row->category_id] ?? null;
                if (!$nominationId || !isset($categoryMap[(int) $row->category_id])) {
                    continue;
                }
                DB::table('award_winners')->updateOrInsert(
                    ['award_category_id' => $categoryMap[(int) $row->category_id], 'nomination_id' => $nominationId],
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
            }
        }

        return [$editionId, $categoryMap];
    }

    private function partners(): void
    {
        if (!Schema::hasTable('legacy_partners')) {
            return;
        }
        foreach (DB::table('legacy_partners')->get() as $row) {
            DB::table('partners')->updateOrInsert(['slug' => Str::slug($row->name)], [
                'name' => $row->name,
                'website' => $row->website_url ?: null,
                'logo' => $row->logo_url ?: null,
                'status' => $row->is_active ? 'active' : 'ended',
                'tier' => $row->partner_type ?: 'community',
                'notes' => $row->description ?: null,
                'created_at' => $row->created_at ?: now(),
                'updated_at' => now(),
            ]);
            $this->increment('partners');
        }
    }

    private function tasks(): void
    {
        if (!Schema::hasTable('cn_tasks')) {
            return;
        }
        $boardId = DB::table('boards')->where('slug', 'cn-taken')->value('id')
            ?: DB::table('boards')->insertGetId(['name' => 'CN Taken', 'slug' => 'cn-taken', 'description' => 'Taken uit het bestaande CN-platform.', 'is_private' => true, 'created_at' => now(), 'updated_at' => now()]);
        $statusMap = ['todo' => 'open', 'bugs' => 'open', 'requests' => 'open', 'initiative' => 'open'];

        foreach (DB::table('cn_tasks')->orderBy('id')->get() as $row) {
            $creatorId = $this->existingUserId($row->created_by) ?? DB::table('users')->oldest('id')->value('id');
            if (!$creatorId) {
                continue;
            }
            $taskId = DB::table('tasks')->where('board_id', $boardId)->where('title', $row->title)->value('id');
            $payload = [
                'board_id' => $boardId,
                'creator_id' => $creatorId,
                'title' => $row->title,
                'description' => $row->description ?: $row->short_description,
                'status' => $statusMap[$row->status] ?? $row->status,
                'priority' => $row->priority,
                'type' => $row->type,
                'required_role' => $row->required_role ?: null,
                'is_public' => (bool) $row->is_public,
                'progress' => (int) $row->progress,
                'labels' => $row->labels ?: null,
                'image_url' => $row->image_url ?: null,
                'claimed_by' => $this->existingUserId($row->claimed_by),
                'completed_by' => $this->existingUserId($row->completed_by),
                'deadline' => $row->deadline ?: null,
                'completed_at' => $row->completed_at ?: null,
                'archived_at' => $row->archived_at ?: null,
                'position' => $row->id,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ];
            if ($taskId) {
                DB::table('tasks')->where('id', $taskId)->update($payload);
            } else {
                $taskId = DB::table('tasks')->insertGetId($payload);
            }
            if ($assigned = $this->existingUserId($row->assigned_to)) {
                DB::table('task_assignees')->updateOrInsert(['task_id' => $taskId, 'user_id' => $assigned], ['created_at' => now(), 'updated_at' => now()]);
            }
            $this->increment('tasks');
        }
    }

    private function badges(): void
    {
        if (!Schema::hasTable('user_badges')) {
            return;
        }
        foreach (DB::table('user_badges')->get() as $row) {
            if (!$this->existingUserId($row->user_id)) {
                continue;
            }
            $slug = Str::slug($row->badge_key ?: $row->badge_label);
            $badgeId = DB::table('badges')->where('slug', $slug)->value('id')
                ?: DB::table('badges')->insertGetId([
                    'name' => $row->badge_label,
                    'slug' => $slug,
                    'description' => 'Behaald in het bestaande CN-platform.',
                    'icon' => $row->badge_type,
                    'color' => '#d71920',
                    'xp_required' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            DB::table('badge_user')->updateOrInsert(
                ['badge_id' => $badgeId, 'user_id' => $row->user_id],
                ['awarded_at' => $row->earned_at, 'created_at' => $row->earned_at, 'updated_at' => $row->earned_at]
            );
            $this->increment('badges');
        }
    }

    private function academy(): void
    {
        if (!Schema::hasTable('staff_exam_questions') && !Schema::hasTable('staff_practice_scenarios')) {
            return;
        }
        $pathId = DB::table('learning_paths')->where('slug', 'cn-staff-academy')->value('id')
            ?: DB::table('learning_paths')->insertGetId([
                'name' => 'CN Staff Academy',
                'slug' => 'cn-staff-academy',
                'description' => 'De bestaande examens en praktijkscenario’s van CN Community.',
                'target_role' => 'helper',
                'is_published' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        $examId = DB::table('lessons')->where('learning_path_id', $pathId)->where('slug', 'cn-staff-examen')->value('id')
            ?: DB::table('lessons')->insertGetId([
                'learning_path_id' => $pathId,
                'title' => 'CN Community Staff Examen',
                'slug' => 'cn-staff-examen',
                'content' => '<p>Test jouw kennis van moderatie, communicatie en het CN-beleid.</p>',
                'type' => 'exam',
                'xp_reward' => 250,
                'position' => 1,
                'settings' => json_encode(['timer_minutes' => 10, 'pass_score' => 75]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        if (Schema::hasTable('staff_exam_questions')) {
            foreach (DB::table('staff_exam_questions')->where('is_active', true)->orderBy('sort_order')->get() as $row) {
                DB::table('questions')->updateOrInsert(
                    ['lesson_id' => $examId, 'question' => $row->question],
                    [
                        'options' => json_encode(['A' => $row->option_a, 'B' => $row->option_b, 'C' => $row->option_c, 'D' => $row->option_d]),
                        'correct_answer' => $row->correct_option,
                        'points' => max(1, (int) $row->points),
                        'created_at' => $row->created_at,
                        'updated_at' => $row->updated_at,
                    ]
                );
            }
        }

        if (Schema::hasTable('staff_practice_scenarios')) {
            foreach (DB::table('staff_practice_scenarios')->where('is_active', true)->orderBy('sort_order')->get() as $row) {
                DB::table('lessons')->updateOrInsert(
                    ['learning_path_id' => $pathId, 'slug' => 'praktijk-'.Str::slug($row->title)],
                    [
                        'title' => $row->title,
                        'content' => $row->scenario,
                        'type' => 'scenario',
                        'xp_reward' => 100,
                        'position' => 10 + $row->sort_order,
                        'settings' => json_encode(['legacy_scenario_id' => $row->id, 'max_points' => $row->max_points]),
                        'created_at' => $row->created_at,
                        'updated_at' => $row->updated_at,
                    ]
                );
            }
        }

        if (Schema::hasTable('staff_exam_attempts')) {
            foreach (DB::table('staff_exam_attempts')->where('status', 'submitted')->orderBy('id')->get() as $row) {
                if (!$this->existingUserId($row->user_id)) {
                    continue;
                }
                DB::table('lesson_progress')->updateOrInsert(
                    ['lesson_id' => $examId, 'user_id' => $row->user_id],
                    [
                        'status' => $row->passed ? 'passed' : 'failed',
                        'score' => $row->percentage,
                        'feedback' => $row->badge_label ?: null,
                        'completed_at' => $row->submitted_at,
                        'created_at' => $row->created_at,
                        'updated_at' => $row->updated_at,
                    ]
                );
                if ($row->passed && $row->certificate_token) {
                    DB::table('certificates')->updateOrInsert(
                        ['uuid' => $this->certificateUuid($row->certificate_token)],
                        [
                            'user_id' => $row->user_id,
                            'learning_path_id' => $pathId,
                            'title' => 'CN Community Staff Examen',
                            'issued_at' => $row->submitted_at,
                            'created_at' => $row->created_at,
                            'updated_at' => $row->updated_at,
                        ]
                    );
                }
            }
        }

        if (Schema::hasTable('staff_practice_submissions')) {
            $scenarioLessons = DB::table('lessons')->where('learning_path_id', $pathId)->where('type', 'scenario')->get()
                ->keyBy(fn ($lesson) => (int) data_get(json_decode($lesson->settings ?: '{}', true), 'legacy_scenario_id'));
            foreach (DB::table('staff_practice_submissions')->get() as $row) {
                $lesson = $scenarioLessons->get((int) $row->scenario_id);
                if (!$lesson || !$this->existingUserId($row->user_id)) {
                    continue;
                }
                DB::table('lesson_progress')->updateOrInsert(
                    ['lesson_id' => $lesson->id, 'user_id' => $row->user_id],
                    [
                        'status' => $row->status === 'reviewed' ? 'passed' : 'submitted',
                        'score' => $row->score,
                        'submission' => $row->answer,
                        'feedback' => $row->feedback,
                        'mentor_id' => $this->existingUserId($row->reviewer_id),
                        'completed_at' => $row->reviewed_at,
                        'created_at' => $row->submitted_at,
                        'updated_at' => $row->reviewed_at ?: $row->submitted_at,
                    ]
                );
            }
        }
    }

    private function notifications(): void
    {
        if (!Schema::hasTable('social_notifications')) {
            return;
        }
        $messageTypes = ['message', 'comment', 'reply', 'mention'];
        $systemSender = DB::table('users')->where('role', UserRole::Owner->value)->value('id') ?: DB::table('users')->oldest('id')->value('id');
        foreach (DB::table('social_notifications')->orderBy('id')->get() as $row) {
            if (!$this->existingUserId($row->user_id)) {
                continue;
            }
            if (in_array($row->type, $messageTypes, true) && $systemSender) {
                DB::table('messages')->updateOrInsert(
                    ['recipient_id' => $row->user_id, 'body' => $row->message, 'created_at' => $row->created_at],
                    ['sender_id' => $systemSender, 'channel' => 'legacy', 'read_at' => $row->is_read ? $row->created_at : null, 'updated_at' => $row->created_at]
                );
                continue;
            }
            $id = (string) Str::uuid();
            DB::table('notifications')->insert([
                'id' => $id,
                'type' => 'legacy.'.$row->type,
                'notifiable_type' => 'App\\Models\\User',
                'notifiable_id' => $row->user_id,
                'data' => json_encode(['title' => ucfirst(str_replace('_', ' ', $row->type)), 'message' => $row->message, 'link' => $row->link]),
                'read_at' => $row->is_read ? $row->created_at : null,
                'created_at' => $row->created_at,
                'updated_at' => $row->created_at,
            ]);
        }
    }

    private function operations(): void
    {
        if (Schema::hasTable('staff_dossiers')) {
            foreach (DB::table('staff_dossiers')->get() as $row) {
                if (!$this->existingUserId($row->user_id)) {
                    continue;
                }
                DB::table('staff_profiles')->updateOrInsert(
                    ['user_id' => $row->user_id],
                    [
                        'position' => $row->function_title ?: DB::table('users')->where('id', $row->user_id)->value('role'),
                        'status' => match ($row->status) { 'inactive', 'archived' => 'inactive', default => 'active' },
                        'joined_at' => $row->started_at ?: substr($row->created_at, 0, 10),
                        'bio' => $row->notes ?: null,
                        'created_at' => $row->created_at,
                        'updated_at' => $row->updated_at ?: $row->created_at,
                    ]
                );
            }
        }

        foreach (DB::table('users')->whereNot('role', UserRole::Member->value)->get() as $user) {
            if (!DB::table('staff_profiles')->where('user_id', $user->id)->exists()) {
                $legacyTitle = Schema::hasTable('legacy_users')
                    ? DB::table('legacy_users')->where('id', $user->id)->value('staff_title')
                    : null;
                DB::table('staff_profiles')->insert([
                    'user_id' => $user->id,
                    'position' => $legacyTitle ?: ucfirst(str_replace('_', ' ', $user->role)),
                    'status' => 'active',
                    'joined_at' => substr($user->created_at, 0, 10),
                    'created_at' => $user->created_at,
                    'updated_at' => now(),
                ]);
            }
        }

        if (Schema::hasTable('cn_absences')) {
            foreach (DB::table('cn_absences')->get() as $row) {
                $userId = $this->existingUserId($row->user_id);
                if (!$userId) {
                    continue;
                }
                DB::table('absence_requests')->updateOrInsert(
                    ['user_id' => $userId, 'starts_on' => substr($row->starts_at, 0, 10), 'ends_on' => substr($row->ends_at, 0, 10)],
                    [
                        'starts_at' => $row->starts_at,
                        'ends_at' => $row->ends_at,
                        'reason' => trim($row->reason.' '.($row->note ?: '')),
                        'status' => match ($row->status) { 'active', 'ended' => 'approved', 'cancelled', 'rejected' => 'rejected', default => 'pending' },
                        'reviewed_by' => $this->existingUserId($row->reviewed_by),
                        'created_at' => $row->created_at,
                        'updated_at' => $row->updated_at,
                    ]
                );
            }
        }

        $absentUserIds = DB::table('absence_requests')
            ->where('status', 'approved')
            ->where(function ($query) {
                $query->where(function ($timed) {
                    $timed->whereNotNull('starts_at')
                        ->where('starts_at', '<=', now())
                        ->where('ends_at', '>=', now());
                })->orWhere(function ($legacy) {
                    $legacy->whereNull('starts_at')
                        ->whereDate('starts_on', '<=', today())
                        ->whereDate('ends_on', '>=', today());
                });
            })
            ->pluck('user_id');
        if ($absentUserIds->isNotEmpty()) {
            DB::table('staff_profiles')->whereIn('user_id', $absentUserIds)->update(['status' => 'absent', 'updated_at' => now()]);
        }

        if (Schema::hasTable('staff_applications')) {
            foreach (DB::table('staff_applications')->get() as $row) {
                DB::table('applications')->updateOrInsert(
                    ['email' => $row->email, 'created_at' => $row->created_at],
                    [
                        'user_id' => $this->existingUserId($row->user_id),
                        'name' => $row->discord_username,
                        'position' => $row->desired_role,
                        'answers' => json_encode([
                            'age' => $row->age,
                            'experience' => $row->experience,
                            'motivation' => $row->motivation,
                            'availability' => $row->availability,
                            'legacy' => json_decode($row->answers_json ?: '[]', true),
                        ]),
                        'status' => match ($row->status) { 'in_review' => 'screening', 'accepted' => 'accepted', 'rejected', 'archived' => 'rejected', default => 'new' },
                        'updated_at' => $row->updated_at,
                    ]
                );
            }
        }
    }

    private function legacyXp(int $userId): int
    {
        if (!Schema::hasTable('user_reputation_events')) {
            return 0;
        }

        return max(0, (int) DB::table('user_reputation_events')->where('user_id', $userId)->sum('points'));
    }

    private function existingUserId(mixed $id): ?int
    {
        if (!$id) {
            return null;
        }

        return DB::table('users')->where('id', $id)->exists() ? (int) $id : null;
    }

    private function visibility(?string $value): string
    {
        return match ($value) {
            'private' => 'private',
            'public', 'community' => 'community',
            default => 'staff',
        };
    }

    private function certificateUuid(string $token): string
    {
        $hex = substr(hash('sha256', $token), 0, 32);

        return substr($hex, 0, 8).'-'.substr($hex, 8, 4).'-4'.substr($hex, 13, 3).'-a'.substr($hex, 17, 3).'-'.substr($hex, 20, 12);
    }

    private function increment(string $key): void
    {
        $this->counts[$key] = ($this->counts[$key] ?? 0) + 1;
    }
}
