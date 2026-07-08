<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
class NomiAiService
{
    public function __construct(private readonly NomiKnowledgeService $knowledge)
    {
    }

    public function ask(string $question, string $context = 'community'): string
    {
        $knowledge = $this->knowledge->contextFor($question, $context === 'staff' ? 'staff' : 'public')
            ->map(fn ($item) => $item->title.': '.mb_substr($item->content, 0, 700))
            ->implode("\n\n");

        if (!config('services.nomi.endpoint') || !config('services.nomi.token')) {
            return $this->localAnswer($question, $context, $knowledge);
        }

        $response = Http::withToken(config('services.nomi.token'))
            ->timeout(20)
            ->post(config('services.nomi.endpoint'), [
                'question' => $question,
                'context' => $context,
                'language' => 'nl',
                'assistant' => 'Nomi',
                'platform_knowledge' => $knowledge,
            ])
            ->throw()
            ->json();

        return (string) data_get($response, 'answer');
    }

    private function localAnswer(string $question, string $context, string $knowledge): string
    {
        $question = mb_strtolower($question);

        if (str_contains($question, 'nomineer') || str_contains($question, 'nominatie')) {
            return 'Open CN Awards, kies een actieve categorie en motiveer duidelijk waarom deze persoon of community de nominatie verdient. Hiervoor moet je met Discord zijn ingelogd.';
        }

        if (str_contains($question, 'stem')) {
            return 'Stemmen kan tijdens een geopende stemronde via CN Awards. Per categorie controleert het platform je Discord-account, IP-signaal en bestaande stem om dubbele stemmen te voorkomen.';
        }

        if ($context === 'staff' || str_contains($question, 'afwezig')) {
            return 'Staffleden kunnen via MijnCN bij Afwezigheid een periode registreren. Tijdens die datums staat op de publieke staffpagina automatisch Niet beschikbaar.';
        }

        if ($knowledge !== '') {
            return "Ik vond hierover informatie binnen CN Community:\n\n".$knowledge;
        }

        return 'Ik kan je helpen met Connect Next Awards, MijnCN, taken, community-updates en staffprocessen. Stel je vraag zo concreet mogelijk, dan wijs ik je naar de juiste plek.';
    }
}
