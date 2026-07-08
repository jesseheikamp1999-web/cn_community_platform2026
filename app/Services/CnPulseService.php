<?php

namespace App\Services;

use App\Models\AbsenceRequest;
use App\Models\AwardEdition;
use App\Models\Content;
use App\Models\Nomination;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CnPulseService
{
    public function feed(int $limit = 18): Collection
    {
        return collect()
            ->merge($this->newsItems())
            ->merge($this->awardItems())
            ->merge($this->birthdayItems())
            ->merge($this->staffItems())
            ->merge($this->partnerItems())
            ->sortByDesc('date')
            ->take($limit)
            ->values();
    }

    public function statusCards(): array
    {
        return [
            [
                'label' => 'Awards',
                'value' => Schema::hasTable('award_editions')
                    ? (AwardEdition::where('type', 'cn_awards')->latest('year')->value('status') ?: 'concept')
                    : 'niet klaar',
                'hint' => 'huidige fase',
            ],
            [
                'label' => 'Nominaties',
                'value' => Schema::hasTable('nominations') ? (string) Nomination::count() : '0',
                'hint' => 'totaal binnen',
            ],
            [
                'label' => 'Nieuws',
                'value' => Schema::hasTable('contents') ? (string) Content::published()->where('type', 'news')->count() : '0',
                'hint' => 'gepubliceerd',
            ],
            [
                'label' => 'Staff afwezig',
                'value' => Schema::hasTable('absence_requests') ? (string) AbsenceRequest::current()->count() : '0',
                'hint' => 'op dit moment',
            ],
        ];
    }

    private function newsItems(): Collection
    {
        if (!Schema::hasTable('contents')) {
            return collect();
        }

        return Content::published()
            ->where('type', 'news')
            ->latest('published_at')
            ->limit(5)
            ->get()
            ->map(fn (Content $content) => $this->item(
                'Nieuws',
                $content->title,
                $content->excerpt ?: 'Nieuwe update uit Connect Next.',
                $content->published_at,
                route('news.show', $content)
            ));
    }

    private function awardItems(): Collection
    {
        if (!Schema::hasTable('nominations')) {
            return collect();
        }

        return Nomination::with('category.edition')
            ->latest()
            ->limit(6)
            ->get()
            ->map(fn (Nomination $nomination) => $this->item(
                'Awards',
                $nomination->nominee_name,
                ($nomination->category?->name ?: 'Nieuwe nominatie').' · status: '.$nomination->status,
                $nomination->created_at,
                in_array($nomination->status, ['approved', 'finalist', 'winner'], true)
                    ? route('awards.nomination', $nomination)
                    : route('mijncn.module', 'nominations')
            ));
    }

    private function birthdayItems(): Collection
    {
        if (!Schema::hasTable('users')) {
            return collect();
        }

        return User::whereNotNull('birth_date')
            ->where('birthday_visibility', 'community')
            ->get()
            ->map(function (User $user) {
                $date = today()->setDate(today()->year, $user->birth_date->month, $user->birth_date->day);
                if ($date->isBefore(today())) {
                    $date->addYear();
                }

                return $this->item(
                    'Verjaardag',
                    $user->name.' is binnenkort jarig',
                    $date->isToday() ? 'Vandaag jarig.' : 'Over '.today()->diffInDays($date).' dagen.',
                    $date,
                    route('mijncn.module', 'birthdays')
                );
            })
            ->sortBy('date')
            ->take(4)
            ->values();
    }

    private function staffItems(): Collection
    {
        if (!Schema::hasTable('absence_requests')) {
            return collect();
        }

        return AbsenceRequest::with('user')
            ->where('status', 'approved')
            ->where(function ($query) {
                if (Schema::hasColumns('absence_requests', ['starts_at', 'ends_at'])) {
                    $query->where('ends_at', '>=', now());
                } else {
                    $query->whereDate('ends_on', '>=', today());
                }
            })
            ->latest(Schema::hasColumn('absence_requests', 'starts_at') ? 'starts_at' : 'starts_on')
            ->limit(5)
            ->get()
            ->map(fn (AbsenceRequest $absence) => $this->item(
                'Staff',
                ($absence->user?->name ?: 'Stafflid').' is afwezig',
                ($absence->starts_at ?? $absence->starts_on)->translatedFormat('d M H:i').' - '.($absence->ends_at ?? $absence->ends_on)->translatedFormat('d M H:i'),
                $absence->starts_at ?? $absence->starts_on,
                route('mijncn.module', 'absences')
            ));
    }

    private function partnerItems(): Collection
    {
        if (!Schema::hasTable('partners')) {
            return collect();
        }

        return Partner::where('status', 'active')
            ->latest()
            ->limit(4)
            ->get()
            ->map(fn (Partner $partner) => $this->item(
                'Partners',
                $partner->name,
                $partner->description ?: 'Actieve partner binnen Connect Next.',
                $partner->created_at,
                route('partners')
            ));
    }

    private function item(string $type, string $title, string $description, mixed $date, string $url): array
    {
        return [
            'type' => $type,
            'title' => $title,
            'description' => $description,
            'date' => $date,
            'url' => $url,
        ];
    }
}
