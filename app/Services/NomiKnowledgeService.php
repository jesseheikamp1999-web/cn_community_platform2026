<?php

namespace App\Services;

use App\Models\Content;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NomiKnowledgeService
{
    public function refresh(): int
    {
        $count = 0;
        Content::where('status', 'published')->whereIn('type', ['news', 'page', 'event'])->each(function (Content $content) use (&$count) {
            $this->indexContent($content);
            $count++;
        });

        return $count;
    }

    public function indexContent(Content $content): void
    {
        if ($content->status !== 'published') {
            DB::table('nomi_knowledge_items')->where(['source_type' => 'content', 'source_id' => $content->id])->delete();
            return;
        }
        $text = trim(strip_tags($content->excerpt."\n".$content->body));
        DB::table('nomi_knowledge_items')->updateOrInsert(
            ['source_type' => 'content', 'source_id' => $content->id],
            [
                'title' => $content->title,
                'content' => $text,
                'visibility' => 'public',
                'checksum' => hash('sha256', $text),
                'indexed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function contextFor(string $question, string $visibility = 'public', int $limit = 4): Collection
    {
        $terms = collect(preg_split('/\s+/u', Str::lower($question)))
            ->filter(fn ($term) => mb_strlen($term) >= 4)
            ->unique()
            ->take(8);
        if ($terms->isEmpty()) {
            return collect();
        }

        return collect(DB::table('nomi_knowledge_items')
            ->whereIn('visibility', $visibility === 'staff' ? ['public', 'staff'] : ['public'])
            ->where(function ($query) use ($terms) {
                foreach ($terms as $term) {
                    $query->orWhere('title', 'like', '%'.$term.'%')->orWhere('content', 'like', '%'.$term.'%');
                }
            })
            ->latest('indexed_at')
            ->limit($limit)
            ->get());
    }
}
