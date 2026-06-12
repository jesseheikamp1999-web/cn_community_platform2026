<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AwardEdition;
use App\Models\Content;
use App\Models\LearningPath;
use App\Services\Academy2026Service;
use App\Models\Partner;
use App\Models\Task;
use Illuminate\Http\Request;

class PlatformController extends Controller
{
    public function profile(Request $request)
    {
        return response()->json(['data' => $request->user()->load('badges')]);
    }

    public function awards()
    {
        return response()->json(['data' => AwardEdition::with('categories')->latest('year')->paginate(15)]);
    }

    public function academy(\Illuminate\Http\Request $request, Academy2026Service $academy)
    {
        $paths = LearningPath::where('is_published', true)
            ->with('lessons')
            ->get()
            ->filter(fn (LearningPath $path) => $academy->isPathUnlocked($request->user(), $path))
            ->values();

        return response()->json(['data' => $paths]);
    }

    public function tasks(Request $request)
    {
        return response()->json(['data' => Task::with('assignees')->whereHas('assignees', fn ($q) => $q->whereKey($request->user()->id))->get()]);
    }

    public function partners()
    {
        return response()->json(['data' => Partner::where('status', 'active')->get()]);
    }

    public function news()
    {
        return response()->json(['data' => Content::published()->where('type', 'news')->latest('published_at')->paginate(20)]);
    }

    public function events()
    {
        return response()->json(['data' => Content::published()->where('type', 'event')->latest('published_at')->paginate(20)]);
    }

    public function discordMember(Request $request, string $discordId)
    {
        $user = \App\Models\User::where('discord_id', $discordId)->firstOrFail();

        return response()->json(['data' => [
            'discord_id' => $user->discord_id,
            'name' => $user->name,
            'role' => $user->role,
            'xp' => $user->xp,
            'badges' => $user->badges()->pluck('name'),
        ]]);
    }
}
