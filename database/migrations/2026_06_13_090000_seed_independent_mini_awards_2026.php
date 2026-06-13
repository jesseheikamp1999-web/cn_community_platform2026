<?php

use App\Models\AwardCategory;
use App\Models\AwardEdition;
use App\Models\AwardRound;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $edition = AwardEdition::firstOrCreate(
            ['slug' => 'summer-mini-awards-2026'],
            [
                'name' => 'Summer Mini Awards 2026',
                'type' => 'mini_awards',
                'year' => 2026,
                'status' => 'nominations',
            ]
        );

        if ($edition->type !== 'mini_awards') {
            return;
        }

        $settings = $edition->settings ?? [];
        $settings['nominations']['max_per_user'] = 1;
        $settings['voting']['allow_change'] = true;
        $settings['finale']['finalists_count'] = 3;
        $edition->update([
            'name' => 'Summer Mini Awards 2026',
            'status' => $edition->status === 'draft' ? 'nominations' : $edition->status,
            'settings' => $settings,
        ]);

        foreach ([
            ['Meest Behulpzame Lid', 'Voor iemand die zonder aarzelen klaarstaat voor anderen.'],
            ['Leukste Communitymoment', 'Voor het moment waar de community nog steeds over praat.'],
            ['Beste Inside Joke', 'Voor de grap die inmiddels bij CN hoort.'],
            ['Creatiefste Bijdrage', 'Voor een originele creatie, idee of communitybijdrage.'],
            ['Positieve Verrassing', 'Voor iemand of iets dat deze ronde onverwacht indruk maakte.'],
            ['Beste Eventmoment', 'Voor het mooiste, grappigste of spannendste moment uit een CN-event.'],
        ] as $index => [$name, $description]) {
            AwardCategory::updateOrCreate(
                [
                    'award_edition_id' => $edition->id,
                    'slug' => Str::slug($name),
                ],
                [
                    'name' => $name,
                    'description' => $description,
                    'sort_order' => ($index + 1) * 10,
                    'jury_weight' => 0,
                    'public_weight' => 100,
                    'is_active' => true,
                ]
            );
        }

        $nominationStart = now()->startOfMinute();
        $nominationEnd = now()->addDays(7)->endOfMinute();
        $votingEnd = now()->addDays(14)->endOfMinute();

        AwardRound::firstOrCreate(
            ['award_edition_id' => $edition->id, 'type' => 'nomination'],
            [
                'name' => 'Mini-nominatieronde',
                'starts_at' => $nominationStart,
                'ends_at' => $nominationEnd,
                'is_active' => true,
            ]
        );
        AwardRound::firstOrCreate(
            ['award_edition_id' => $edition->id, 'type' => 'public_vote'],
            [
                'name' => 'Mini-stemronde',
                'starts_at' => $nominationEnd->copy()->addMinute(),
                'ends_at' => $votingEnd,
                'is_active' => true,
            ]
        );
    }

    public function down(): void
    {
        // Productie-inhoud wordt bij rollback niet destructief verwijderd.
    }
};
