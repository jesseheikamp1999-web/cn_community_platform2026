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

Artisan::command('cn:sync-external-news', function () {
    $result = app(\App\Services\ExternalNewsService::class)->sync(force: true);
    $this->info($result['created'].' externe nieuwsberichten toegevoegd, '.$result['updated'].' bijgewerkt.');
    foreach ($result['errors'] as $error) {
        $this->warn($error);
    }
})->purpose('Haal externe nieuwsfeeds zoals NU.nl en NOS op');

Artisan::command('cn:automate-community', function () {
    $result = app(\App\Services\CommunityAutomationService::class)->run();
    $this->info($result['award_phases'].' Awards-meldingen en '.$result['birthdays'].' verjaardagen verwerkt.');
})->purpose('Verwerk Awards-fasen, Discord-aankondigingen en verjaardagsmeldingen');

Artisan::command('cn:cleanup-chat', function () {
    $deleted = 0;
    \App\Models\ChatConversation::whereNotNull('retention_days')
        ->each(function (\App\Models\ChatConversation $conversation) use (&$deleted): void {
            $messages = $conversation->messages()
                ->where('created_at', '<', now()->subDays($conversation->retention_days))
                ->whereNull('pinned_at')
                ->with('attachments')
                ->get();
            foreach ($messages as $message) {
                foreach ($message->attachments as $attachment) {
                    \Illuminate\Support\Facades\Storage::disk($attachment->disk)->delete($attachment->path);
                }
                $message->delete();
                $deleted++;
            }
        });
    $this->info($deleted.' oude chatberichten zijn verwijderd.');
})->purpose('Verwijder oude, niet-vastgezette chatberichten volgens de bewaartermijn');

Schedule::command('cn:automate-community')->everyMinute()->withoutOverlapping();
Schedule::command('cn:sync-external-news')->everyThirtyMinutes()->withoutOverlapping();
Schedule::command('cn:cleanup-chat')->dailyAt('03:30')->withoutOverlapping();
