<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('cn:publish-scheduled', function () {
    app(\App\Services\PublishingService::class)->publishDueContent();
    $this->info('Geplande publicaties zijn verwerkt.');
})->purpose('Publiceer gepland nieuws, events en winnaars');

Artisan::command('cn:sync-discord-members', function () {
    $count = app(\App\Services\DiscordMemberSyncService::class)->sync();
    $this->info($count.' Discord-leden zijn gesynchroniseerd.');
})->purpose('Synchroniseer de CN Community Discord-leden met MijnCN');

Artisan::command('cn:index-nomi-knowledge', function () {
    $count = app(\App\Services\NomiKnowledgeService::class)->refresh();
    $this->info($count.' gepubliceerde kennisitems zijn voor Nomi geïndexeerd.');
})->purpose('Werk Nomi bij met gepubliceerde CN Community-inhoud');

Artisan::command('cn:automate-community', function () {
    $result = app(\App\Services\CommunityAutomationService::class)->run();
    $this->info($result['award_phases'].' Awards-meldingen en '.$result['birthdays'].' verjaardagen verwerkt.');
})->purpose('Verwerk Awards-fasen, Discord-aankondigingen en verjaardagsmeldingen');

Schedule::command('cn:automate-community')->everyMinute()->withoutOverlapping();
