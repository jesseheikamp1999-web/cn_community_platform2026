<?php

namespace App\Http\Controllers;

use App\Models\AwardCategory;
use App\Models\Nomination;
use App\Services\AwardService;
use Illuminate\Http\Request;

class AwardActionController extends Controller
{
    public function nominate(Request $request, AwardCategory $category, AwardService $awards)
    {
        $data = $request->validate([
            'nominee_name' => ['required', 'string', 'max:100'],
            'nominee_discord_id' => ['nullable', 'string', 'max:30'],
            'motivation' => ['required', 'string', 'min:40', 'max:2000'],
            'evidence_url' => ['nullable', 'url', 'max:255'],
            'evidence_text' => ['nullable', 'string', 'max:2000'],
        ]);

        $awards->nominate($request->user(), $category, $data);

        return back()->with('success', 'Je nominatie is ontvangen en wacht op beoordeling.');
    }

    public function vote(Request $request, Nomination $nomination, AwardService $awards)
    {
        $data = $request->validate(['round_id' => ['required', 'integer', 'exists:award_rounds,id']]);
        $awards->vote($request->user(), $nomination, $data['round_id'], $request);

        return back()->with('success', 'Je stem is veilig opgeslagen.');
    }
}
