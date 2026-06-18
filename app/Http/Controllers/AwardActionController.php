<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\AwardCategory;
use App\Models\Nomination;
use App\Services\AwardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

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

        $route = $nomination->category->edition->type === 'mini_awards'
            ? 'mini.awards'
            : 'awards';

        return redirect()
            ->route($route, ['categorie' => $nomination->category->slug])
            ->with('success', 'Je stem is veilig opgeslagen.');
    }

    public function editProfile(Request $request, Nomination $nomination): View
    {
        $this->authorizeProfileManagement($request, $nomination);
        $nomination->load('category.edition');

        return view('dashboard.nomination-edit', compact('nomination'));
    }

    public function updateProfile(Request $request, Nomination $nomination): RedirectResponse
    {
        $this->authorizeProfileManagement($request, $nomination);

        $data = $request->validate([
            'nominee_name' => ['required', 'string', 'max:120'],
            'motivation' => ['required', 'string', 'min:40', 'max:6000'],
            'evidence_text' => ['nullable', 'string', 'max:4000'],
            'website_url' => ['nullable', 'url', 'max:255'],
            'discord_invite' => ['nullable', 'url', 'max:255'],
            'logo_url' => ['nullable', 'url', 'max:255'],
            'banner_url' => ['nullable', 'url', 'max:255'],
            'logo_upload' => ['nullable', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:4096'],
            'banner_upload' => ['nullable', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:6144'],
        ]);

        foreach (['logo_upload' => 'logo_url', 'banner_upload' => 'banner_url'] as $input => $column) {
            if ($request->hasFile($input)) {
                $data[$column] = Storage::disk('public')->url(
                    $request->file($input)->store('nominations/'.$nomination->id, 'public')
                );
            }
        }

        $nomination->update([
            'nominee_name' => $data['nominee_name'],
            'motivation' => $data['motivation'],
            'evidence_text' => $data['evidence_text'] ?? null,
            'website_url' => $data['website_url'] ?? null,
            'discord_invite' => $data['discord_invite'] ?? null,
            'logo_url' => $data['logo_url'] ?? null,
            'banner_url' => $data['banner_url'] ?? null,
        ]);

        return redirect()
            ->route('mijncn.nominations.edit', $nomination)
            ->with('success', 'Je nominatieprofiel is bijgewerkt.');
    }

    private function authorizeProfileManagement(Request $request, Nomination $nomination): void
    {
        $user = $request->user();

        abort_unless(
            $nomination->user_id === $user->id
            || $user->hasPermission('awards.manage')
            || in_array($user->role, [UserRole::Owner, UserRole::Management, UserRole::Admin], true),
            403
        );
    }
}
