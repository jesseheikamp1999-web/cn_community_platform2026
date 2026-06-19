<?php

namespace App\Services;

use App\Models\Content;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use SimpleXMLElement;
use Throwable;

class ExternalNewsService
{
    public function sync(bool $force = false): array
    {
        if (!config('news.external_enabled')) {
            return ['created' => 0, 'updated' => 0, 'errors' => ['Externe nieuwsfeeds staan uit.']];
        }

        $cacheKey = 'external-news:last-sync';
        if (!$force && Cache::has($cacheKey)) {
            return ['created' => 0, 'updated' => 0, 'errors' => [], 'skipped' => true];
        }

        $created = 0;
        $updated = 0;
        $errors = [];

        foreach (config('news.feeds', []) as $feed) {
            try {
                foreach ($this->fetchFeed($feed) as $item) {
                    $content = Content::updateOrCreate(
                        ['slug' => $item['slug']],
                        [
                            'type' => 'news',
                            'title' => $item['title'],
                            'excerpt' => $item['excerpt'],
                            'body' => $this->bodyFor($item),
                            'cover_image' => $item['image'],
                            'status' => 'published',
                            'published_at' => $item['published_at'],
                            'meta' => [
                                'external' => true,
                                'source' => $item['source'],
                                'source_url' => $item['url'],
                                'source_guid' => $item['guid'],
                                'category' => $item['category'],
                            ],
                        ],
                    );

                    $content->wasRecentlyCreated ? $created++ : $updated++;
                }
            } catch (Throwable $exception) {
                $errors[] = ($feed['source'] ?? 'Onbekende feed').': '.$exception->getMessage();
            }
        }

        Cache::put($cacheKey, now(), now()->addMinutes(max(5, (int) config('news.refresh_minutes', 30))));

        return compact('created', 'updated', 'errors') + ['skipped' => false];
    }

    public function syncIfStale(): void
    {
        if (app()->runningUnitTests() && !Cache::get('external-news:allow-test-sync')) {
            return;
        }

        try {
            $this->sync();
        } catch (Throwable) {
            // Externe feeds mogen nooit de CN-site blokkeren.
        }
    }

    private function fetchFeed(array $feed): array
    {
        $response = Http::timeout(5)
            ->withHeaders([
                'Accept' => 'application/rss+xml, application/xml, text/xml',
                'User-Agent' => 'CN Community Platform 2026 (+https://www.cncommunity.nl)',
            ])
            ->get($feed['url']);

        if (!$response->ok()) {
            throw new \RuntimeException('feed gaf HTTP '.$response->status());
        }

        $xml = simplexml_load_string($response->body(), SimpleXMLElement::class, LIBXML_NOCDATA | LIBXML_NONET);
        if (!$xml) {
            throw new \RuntimeException('feed kon niet gelezen worden');
        }

        $nodes = $xml->channel->item ?? $xml->entry ?? [];
        $items = [];
        $limit = max(1, (int) config('news.max_per_feed', 5));

        foreach ($nodes as $node) {
            if (count($items) >= $limit) {
                break;
            }

            $item = $this->mapItem($node, $feed);
            if ($item) {
                $items[] = $item;
            }
        }

        return $items;
    }

    private function mapItem(SimpleXMLElement $node, array $feed): ?array
    {
        $title = trim((string) $node->title);
        $url = $this->linkFrom($node);
        if ($title === '' || $url === '') {
            return null;
        }

        $guid = trim((string) ($node->guid ?? $node->id ?? $url));
        $description = trim(strip_tags(html_entity_decode((string) ($node->description ?? $node->summary ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        $publishedAt = $this->publishedAt($node);
        $source = (string) ($feed['source'] ?? parse_url($url, PHP_URL_HOST));

        return [
            'source' => $source,
            'category' => (string) ($feed['category'] ?? 'Nieuws'),
            'title' => $title,
            'excerpt' => Str::limit($description ?: 'Lees het volledige bericht bij '.$source.'.', 220),
            'url' => $url,
            'guid' => $guid,
            'slug' => 'extern-'.$this->slugSource($source).'-'.substr(sha1($guid ?: $url), 0, 12),
            'image' => $this->imageFrom($node),
            'published_at' => $publishedAt,
        ];
    }

    private function linkFrom(SimpleXMLElement $node): string
    {
        if (isset($node->link['href'])) {
            return trim((string) $node->link['href']);
        }

        return trim((string) $node->link);
    }

    private function imageFrom(SimpleXMLElement $node): ?string
    {
        foreach (['media', 'enclosure'] as $namespace) {
            $children = $namespace === 'media'
                ? $node->children('http://search.yahoo.com/mrss/')
                : $node;

            if ($namespace === 'media' && isset($children->content)) {
                $url = trim((string) $children->content->attributes()->url);
                if ($url !== '') {
                    return $url;
                }
            }

            if ($namespace === 'enclosure' && isset($node->enclosure)) {
                $url = trim((string) $node->enclosure->attributes()->url);
                $type = trim((string) $node->enclosure->attributes()->type);
                if ($url !== '' && ($type === '' || str_starts_with($type, 'image/'))) {
                    return $url;
                }
            }
        }

        return null;
    }

    private function publishedAt(SimpleXMLElement $node): Carbon
    {
        $date = (string) ($node->pubDate ?? $node->published ?? $node->updated ?? '');

        try {
            return $date ? Carbon::parse($date) : now();
        } catch (Throwable) {
            return now();
        }
    }

    private function bodyFor(array $item): string
    {
        return '<p>'.e($item['excerpt']).'</p><p><a href="'.e($item['url']).'" target="_blank" rel="noopener noreferrer">Lees het volledige artikel bij '.e($item['source']).' &rarr;</a></p>';
    }

    private function slugSource(string $source): string
    {
        return Str::slug(str_replace('.', '-', $source));
    }
}
