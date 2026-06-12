<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Permission;
use App\Services\DiscordService;
use App\Services\DiscordMemberSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class DiscordController extends Controller
{
    public function redirect(Request $request)
    {
        $state = Str::random(40);
        $request->session()->put('discord_oauth_state', $state);

        return redirect()->away('https://discord.com/oauth2/authorize?'.http_build_query([
            'client_id' => config('services.discord.client_id'),
            'redirect_uri' => config('services.discord.redirect'),
            'response_type' => 'code',
            'scope' => 'identify email guilds',
            'state' => $state,
        ]));
    }

    public function callback(Request $request, DiscordService $discord, DiscordMemberSyncService $memberSync)
    {
        abort_unless(hash_equals((string) $request->session()->pull('discord_oauth_state'), (string) $request->state), 419);

        $tokens = $discord->exchangeCode($request->string('code'));
        $profile = $discord->user($tokens['access_token']);
        $member = $discord->guildMember($profile['id']);
        $platformRole = $discord->platformRole($member);

        $user = User::updateOrCreate(
            ['discord_id' => $profile['id']],
            [
                'name' => $profile['global_name'] ?? $profile['username'],
                'email' => $profile['email'] ?? null,
                'discord_username' => $profile['username'],
                'discord_avatar' => $profile['avatar'] ?? null,
                'role' => $platformRole,
            ]
        );
        if ($member) {
            $memberSync->storeMember($member);
        }

        if (!$user->permissions_locked) {
            $this->syncPermissions($user);
        }
        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'))->with('success', 'Welkom terug bij CN Community.');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    private function syncPermissions(User $user): void
    {
        $permissions = match ($user->role) {
            \App\Enums\UserRole::Owner => Permission::pluck('id'),
            \App\Enums\UserRole::Management => Permission::whereIn('name', [
                'staff.access', 'awards.review', 'awards.manage', 'tasks.manage',
                'academy.manage', 'hr.manage', 'partners.manage', 'content.manage', 'audit.view',
            ])->pluck('id'),
            \App\Enums\UserRole::Admin => Permission::whereIn('name', [
                'staff.access', 'awards.review', 'tasks.manage', 'academy.manage', 'content.manage',
            ])->pluck('id'),
            \App\Enums\UserRole::Moderator, \App\Enums\UserRole::Helper => Permission::whereIn('name', [
                'staff.access',
            ])->pluck('id'),
            \App\Enums\UserRole::Jury => Permission::whereIn('name', [
                'staff.access', 'jury.score',
            ])->pluck('id'),
            \App\Enums\UserRole::PartnerManager => Permission::whereIn('name', [
                'staff.access', 'partners.manage',
            ])->pluck('id'),
            default => collect(),
        };

        $user->permissions()->sync($permissions);
    }
}
