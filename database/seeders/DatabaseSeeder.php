<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\AwardCategory;
use App\Models\AwardEdition;
use App\Models\Badge;
use App\Models\Content;
use App\Models\Partner;
use App\Models\Permission;
use App\Models\Task;
use App\Models\User;
use App\Services\Academy2026Service;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = collect([
            ['staff.access', 'Staffomgeving openen', 'Staff'],
            ['awards.review', 'Nominaties beoordelen', 'Awards'],
            ['awards.manage', 'Awards beheren', 'Awards'],
            ['jury.score', 'Juryscores invoeren', 'Awards'],
            ['tasks.manage', 'Taken beheren', 'Taken'],
            ['academy.manage', 'Academy beheren', 'Academy'],
            ['hr.manage', 'HR beheren', 'HR'],
            ['partners.manage', 'Partners beheren', 'CRM'],
            ['content.manage', 'Nieuws en events beheren', 'Content'],
            ['roles.manage', 'Rollen en permissies beheren', 'Beveiliging'],
            ['audit.view', 'Auditlogs bekijken', 'Beveiliging'],
        ])->map(fn ($item) => Permission::create(['name' => $item[0], 'label' => $item[1], 'group' => $item[2]]));

        $users = collect([
            ['Jesse', 'jesse@cncommunity.nl', '100000000001', 'jesse', UserRole::Owner, 2840],
            ['Melvin', 'melvin@cncommunity.nl', '100000000002', 'melvin', UserRole::Management, 2160],
            ['Stan', 'stan@cncommunity.nl', '100000000003', 'stan', UserRole::Management, 1980],
            ['Lars', 'lars@cncommunity.nl', '100000000004', 'lars', UserRole::Admin, 1530],
            ['Noah', 'noah@example.nl', '100000000005', 'noah', UserRole::Member, 760],
        ])->map(fn ($item) => User::create([
            'name' => $item[0], 'email' => $item[1], 'discord_id' => $item[2],
            'discord_username' => $item[3], 'role' => $item[4], 'xp' => $item[5],
        ]));

        $users->take(4)->each(fn (User $user) => $user->permissions()->sync($permissions->pluck('id')));

        $edition = AwardEdition::create([
            'name' => 'CN Awards 2026', 'slug' => 'cn-awards-2026', 'type' => 'cn_awards',
            'year' => 2026, 'status' => 'nominations', 'starts_at' => now()->subDays(5),
            'ends_at' => now()->addDays(12), 'finale_at' => now()->addMonths(2),
        ]);
        foreach ([
            ['Communitylid van het Jaar', 'communitylid-van-het-jaar', 'Voor iemand die CN elke dag sterker maakt.'],
            ['Helper van het Jaar', 'helper-van-het-jaar', 'Voor uitzonderlijke hulp en betrokkenheid.'],
            ['Creatief Talent', 'creatief-talent', 'Voor ideeën die de community verrassen.'],
            ['Grootste Groei', 'grootste-groei', 'Voor iemand die zichtbaar is gegroeid.'],
        ] as $index => $category) {
            AwardCategory::create(['award_edition_id' => $edition->id, 'name' => $category[0], 'slug' => $category[1], 'description' => $category[2], 'sort_order' => $index]);
        }
        DB::table('award_rounds')->insert([
            'award_edition_id' => $edition->id, 'name' => 'Nominatieronde', 'type' => 'nomination',
            'starts_at' => now()->subDays(5), 'ends_at' => now()->addDays(12), 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $mini = AwardEdition::create(['name' => 'Summer Mini Awards', 'slug' => 'summer-mini-awards-2026', 'type' => 'mini_awards', 'year' => 2026, 'status' => 'draft']);
        AwardCategory::create(['award_edition_id' => $mini->id, 'name' => 'Beste Inside Joke', 'slug' => 'beste-inside-joke', 'description' => 'Het moment waar iedereen nog om lacht.']);

        foreach ([
            ['Een nieuw hoofdstuk voor CN Community', 'Ontdek waarom we een zelfstandig platform bouwen en wat dit voor jou betekent.'],
            ['De Moderator Academy is vernieuwd', 'Nieuwe scenario’s, mentorfeedback en een helder leerpad.'],
            ['Maak kennis met onze partners', 'Samen maken we meer mogelijk voor iedere member.'],
        ] as $index => $news) {
            Content::create([
                'author_id' => $users[0]->id, 'type' => 'news', 'title' => $news[0],
                'slug' => Str::slug($news[0]), 'excerpt' => $news[1],
                'body' => '<p>'.$news[1].'</p>', 'status' => 'published',
                'published_at' => now()->subDays($index * 3),
            ]);
        }

        foreach ([['Cloud86', 'https://cloud86.nl', 'strategic'], ['Studio Nova', '#', 'creative'], ['PixelForge', '#', 'community']] as $partner) {
            Partner::updateOrCreate(
                ['slug' => Str::slug($partner[0])],
                ['name' => $partner[0], 'website' => $partner[1], 'status' => 'active', 'tier' => $partner[2]]
            );
        }

        foreach ([['Eerste stap', 'Je hebt je eerste Academy-les afgerond.', 100], ['Communitybouwer', 'Je hebt zichtbaar bijgedragen aan CN.', 1000], ['CN Veteraan', 'Een vaste waarde binnen de community.', 2500]] as $badge) {
            Badge::create(['name' => $badge[0], 'slug' => Str::slug($badge[0]), 'description' => $badge[1], 'xp_required' => $badge[2]]);
        }

        app(Academy2026Service::class)->sync();
        app(\App\Services\NomiKnowledgeService::class)->refresh();

        $boardId = DB::table('boards')->insertGetId(['name' => 'CN Platform', 'slug' => 'cn-platform', 'description' => 'Operationele taken voor het platform.', 'is_private' => true, 'created_at' => now(), 'updated_at' => now()]);
        foreach ([['Finalepagina voorbereiden', 'in_progress', 'high'], ['Nieuwe Academy-les reviewen', 'open', 'normal'], ['Partnercheck juni', 'waiting', 'urgent'], ['Discord webhook testen', 'completed', 'normal']] as $position => $task) {
            Task::create(['board_id' => $boardId, 'creator_id' => $users[0]->id, 'title' => $task[0], 'status' => $task[1], 'priority' => $task[2], 'position' => $position, 'deadline' => now()->addDays($position + 2)]);
        }
    }
}
