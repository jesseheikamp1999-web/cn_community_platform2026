<?php

namespace Database\Seeders;

use App\Models\AwardCategory;
use App\Models\AwardEdition;
use App\Models\Badge;
use App\Models\Permission;
use App\Services\Academy2026Service;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
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
        ] as $permission) {
            Permission::updateOrCreate(
                ['name' => $permission[0]],
                ['label' => $permission[1], 'group' => $permission[2]]
            );
        }

        $edition = AwardEdition::firstOrCreate(
            ['slug' => 'cn-awards-2026'],
            ['name' => 'CN Awards 2026', 'type' => 'cn_awards', 'year' => 2026, 'status' => 'draft']
        );

        foreach ([
            ['Communitylid van het Jaar', 'Voor iemand die CN iedere dag sterker maakt.'],
            ['Helper van het Jaar', 'Voor uitzonderlijke hulp en betrokkenheid.'],
            ['Creatief Talent', 'Voor ideeën die de community verrassen.'],
            ['Grootste Groei', 'Voor iemand die zichtbaar is gegroeid.'],
        ] as $index => $category) {
            AwardCategory::updateOrCreate(
                ['award_edition_id' => $edition->id, 'slug' => Str::slug($category[0])],
                ['name' => $category[0], 'description' => $category[1], 'sort_order' => $index]
            );
        }

        $miniEdition = AwardEdition::firstOrCreate(
            ['slug' => 'summer-mini-awards-2026'],
            ['name' => 'Summer Mini Awards 2026', 'type' => 'mini_awards', 'year' => 2026, 'status' => 'nominations']
        );
        $miniSettings = $miniEdition->settings ?? [];
        $miniSettings['nominations']['max_per_user'] = 1;
        $miniSettings['voting']['allow_change'] = true;
        $miniSettings['finale']['finalists_count'] = 3;
        $miniEdition->update(['settings' => $miniSettings]);

        foreach ([
            ['Meest Behulpzame Lid', 'Voor iemand die zonder aarzelen klaarstaat voor anderen.'],
            ['Leukste Communitymoment', 'Voor het moment waar de community nog steeds over praat.'],
            ['Beste Inside Joke', 'Voor de grap die inmiddels bij CN hoort.'],
            ['Creatiefste Bijdrage', 'Voor een originele creatie, idee of communitybijdrage.'],
            ['Positieve Verrassing', 'Voor iemand of iets dat deze ronde onverwacht indruk maakte.'],
            ['Beste Eventmoment', 'Voor het mooiste, grappigste of spannendste moment uit een CN-event.'],
        ] as $index => $category) {
            AwardCategory::updateOrCreate(
                ['award_edition_id' => $miniEdition->id, 'slug' => Str::slug($category[0])],
                [
                    'name' => $category[0],
                    'description' => $category[1],
                    'sort_order' => ($index + 1) * 10,
                    'jury_weight' => 0,
                    'public_weight' => 100,
                    'is_active' => true,
                ]
            );
        }

        app(Academy2026Service::class)->sync();
        app(\App\Services\NomiKnowledgeService::class)->refresh();

        foreach ([
            ['Eerste stap', 'Je hebt je eerste Academy-les afgerond.', 100],
            ['Communitybouwer', 'Je hebt zichtbaar bijgedragen aan CN.', 1000],
            ['CN Veteraan', 'Een vaste waarde binnen de community.', 2500],
        ] as $badge) {
            Badge::firstOrCreate(
                ['slug' => Str::slug($badge[0])],
                ['name' => $badge[0], 'description' => $badge[1], 'xp_required' => $badge[2]]
            );
        }

        DB::table('boards')->updateOrInsert(
            ['slug' => 'cn-platform'],
            ['name' => 'CN Platform', 'description' => 'Operationele taken voor CN Community.', 'is_private' => true, 'created_at' => now(), 'updated_at' => now()]
        );
    }
}
