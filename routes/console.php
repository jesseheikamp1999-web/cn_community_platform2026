<?php

use Illuminate\Support\Facades\Artisan;

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
