<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\AwardCategory;
use App\Models\AwardEdition;
use App\Models\Nomination;
use App\Models\Partner;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

class LegacyImportService
{
    private array $counts = ['users' => 0, 'categories' => 0, 'nominations' => 0, 'partners' => 0, 'tasks' => 0];

    public function run(): array
    {
        if (!Schema::connection('legacy')->hasTable('users')) {
            throw new RuntimeException('De legacy database bevat geen users-tabel.');
        }

        return DB::transaction(function () {
            $edition = AwardEdition::firstOrCreate(
                ['slug' => 'cn-awards-2026'],
                ['name' => 'CN Awards 2026', 'type' => 'cn_awards', 'year' => 2026, 'status' => 'draft']
            );

            $userMap = $this->importUsers();
            $categoryMap = $this->importCategories($edition);
            $this->importNominations($userMap, $categoryMap);
            $this->importPartners();
            $this->importTasks($userMap);

            return $this->counts;
        });
    }

    private function importUsers(): array
    {
        $map = [];
        $roles = ['lid' => UserRole::Member, 'helper' => UserRole::Helper, 'moderator' => UserRole::Moderator, 'jury' => UserRole::Jury, 'admin' => UserRole::Admin, 'management' => UserRole::Management, 'owner' => UserRole::Owner];

        DB::connection('legacy')->table('users')->orderBy('id')->chunk(200, function ($rows) use (&$map, $roles) {
            foreach ($rows as $row) {
                $user = User::updateOrCreate(
                    ['discord_id' => (string) $row->discord_id],
                    [
                        'name' => $row->username,
                        'email' => $row->email ?: null,
                        'discord_username' => $row->username,
                        'discord_avatar' => $row->avatar ?: null,
                        'role' => $roles[$row->role] ?? UserRole::Member,
                        'birth_date' => $row->birth_date ?: null,
                        'profile_bio' => $row->profile_bio ?: null,
                        'birthday_visibility' => $row->birthday_visibility === 'private' ? 'private' : 'staff',
                        'birthday_notifications' => (bool) ($row->birthday_notify ?? true),
                    ]
                );
                $map[$row->id] = $user->id;
                $this->counts['users']++;
            }
        });

        return $map;
    }

    private function importCategories(AwardEdition $edition): array
    {
        if (!Schema::connection('legacy')->hasTable('categories')) {
            return [];
        }

        $map = [];
        foreach (DB::connection('legacy')->table('categories')->orderBy('sort_order')->get() as $row) {
            $category = AwardCategory::updateOrCreate(
                ['award_edition_id' => $edition->id, 'slug' => Str::slug($row->name)],
                ['name' => $row->name, 'description' => $row->description, 'icon' => $row->icon, 'sort_order' => $row->sort_order, 'is_active' => (bool) $row->is_active]
            );
            $map[$row->id] = $category->id;
            $this->counts['categories']++;
        }

        return $map;
    }

    private function importNominations(array $userMap, array $categoryMap): void
    {
        if (!Schema::connection('legacy')->hasTable('submissions')) {
            return;
        }

        $categoryLinks = Schema::connection('legacy')->hasTable('submission_categories')
            ? DB::connection('legacy')->table('submission_categories')->get()->groupBy('submission_id')
            : collect();

        foreach (DB::connection('legacy')->table('submissions')->orderBy('id')->get() as $row) {
            foreach ($categoryLinks->get($row->id, collect()) as $link) {
                if (!isset($userMap[$row->user_id], $categoryMap[$link->category_id])) {
                    continue;
                }

                Nomination::firstOrCreate(
                    [
                        'award_category_id' => $categoryMap[$link->category_id],
                        'user_id' => $userMap[$row->user_id],
                        'nominee_name' => $row->title,
                    ],
                    [
                        'motivation' => $row->description,
                        'status' => $row->status,
                        'created_at' => $row->created_at,
                        'updated_at' => $row->updated_at,
                    ]
                );
                $this->counts['nominations']++;
            }
        }
    }

    private function importPartners(): void
    {
        if (!Schema::connection('legacy')->hasTable('partners')) {
            return;
        }

        foreach (DB::connection('legacy')->table('partners')->get() as $row) {
            Partner::updateOrCreate(
                ['slug' => Str::slug($row->name)],
                [
                    'name' => $row->name,
                    'website' => $row->website_url,
                    'logo' => $row->logo_url,
                    'status' => $row->is_active ? 'active' : 'ended',
                    'tier' => $row->partner_type ?: 'community',
                    'notes' => $row->description,
                ]
            );
            $this->counts['partners']++;
        }
    }

    private function importTasks(array $userMap): void
    {
        if (!Schema::connection('legacy')->hasTable('cn_tasks')) {
            return;
        }

        $boardId = DB::table('boards')->where('slug', 'legacy-taken')->value('id')
            ?: DB::table('boards')->insertGetId(['name' => 'Geïmporteerde taken', 'slug' => 'legacy-taken', 'description' => 'Taken uit het vorige CN-platform.', 'is_private' => true, 'created_at' => now(), 'updated_at' => now()]);

        $statusMap = ['todo' => 'open', 'testing' => 'waiting', 'bugs' => 'open', 'requests' => 'open', 'initiative' => 'open'];
        foreach (DB::connection('legacy')->table('cn_tasks')->whereNull('archived_at')->get() as $row) {
            $creatorId = $userMap[$row->created_by] ?? User::oldest()->value('id');
            if (!$creatorId) {
                continue;
            }
            Task::updateOrCreate(
                ['board_id' => $boardId, 'title' => $row->title],
                [
                    'creator_id' => $creatorId,
                    'description' => $row->description ?: $row->short_description,
                    'status' => $statusMap[$row->status] ?? $row->status,
                    'priority' => $row->priority,
                    'type' => $row->type,
                    'required_role' => $row->required_role ?: null,
                    'is_public' => (bool) $row->is_public,
                    'progress' => $row->progress,
                    'labels' => $row->labels ?: null,
                    'image_url' => $row->image_url ?: null,
                    'claimed_by' => $userMap[$row->claimed_by] ?? null,
                    'completed_by' => $userMap[$row->completed_by] ?? null,
                    'deadline' => $row->deadline ?: null,
                    'completed_at' => $row->completed_at ?: null,
                    'position' => $row->id,
                ]
            );
            $this->counts['tasks']++;
        }
    }
}
