<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->text('description')->nullable()->after('slug');
            $table->string('category')->default('server')->after('tier');
            $table->unsignedSmallInteger('score')->default(0)->after('category');
            $table->unsignedSmallInteger('position')->default(100)->index()->after('score');
            $table->boolean('is_featured')->default(true)->after('position');
        });

        $projects = [
            ['Stumpertjes', 'Creatieve Discord-community met actieve leden.', 'https://discord.gg/dG7HRqVa9J', 94, 1],
            ['Game On', 'Gamingproject met focus op events en gezelligheid.', 'https://discord.gg/dG7HRqVa9J', 91, 2],
            ['NightMC', 'Minecraft-server met een herkenbare community.', 'https://discord.gg/dG7HRqVa9J', 89, 3],
            ['ValoraMC', 'Serverproject met groeiende spelersgroep.', 'https://discord.gg/dG7HRqVa9J', 87, 4],
            ['GamingTubex', 'Communityproject rond content en gaming.', 'https://discord.gg/dG7HRqVa9J', 85, 5],
            ['NL & BE Roleplay', 'Roleplay-community voor Nederlandse en Belgische spelers.', 'https://discord.gg/dG7HRqVa9J', 83, 6],
            ['PixelForge', 'Creatieve server voor makers en developers.', 'https://discord.gg/dG7HRqVa9J', 81, 7],
            ['Creative Hub', 'Partnerproject voor design, bouw en content.', 'https://discord.gg/dG7HRqVa9J', 79, 8],
            ['Nexus', 'Communityserver met focus op samenwerking.', 'https://discord.gg/dG7HRqVa9J', 77, 9],
            ['Studio Nova', 'Creatieve partner voor community-identiteit.', 'https://discord.gg/dG7HRqVa9J', 75, 10],
        ];

        foreach ($projects as [$name, $description, $website, $score, $position]) {
            DB::table('partners')->updateOrInsert(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'description' => $description,
                    'website' => $website,
                    'status' => 'active',
                    'tier' => 'community',
                    'category' => 'server',
                    'score' => $score,
                    'position' => $position,
                    'is_featured' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->dropColumn(['description', 'category', 'score', 'position', 'is_featured']);
        });
    }
};
