<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Permission;
use App\Services\DiscordService;
use App\Services\DiscordMemberSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class DiscordController extends Controller
{
    public function redirect(Request $request)
    {
        $state = Str::random(40);
        $redirectUri = $this->redirectUri();
        $request->session()->put('discord_oauth_state', $state);
        $request->session()->put('discord_oauth_redirect_uri', $redirectUri);

        return redirect()->away('https://discord.com/oauth2/authorize?'.http_build_query([
            'client_id' => config('services.discord.client_id'),
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'identify email guilds',
            'state' => $state,
        ]));
    }

    public function callback(Request $request, DiscordService $discord, DiscordMemberSyncService $memberSync)
    {
        if (!$request->filled(['code', 'state'])) {
            return redirect()->route('discord.login')
                ->withErrors(['discord' => 'Discord heeft geen geldige inlogcode teruggestuurd. Probeer opnieuw.']);
        }

        $expectedState = (string) $request->session()->pull('discord_oauth_state');
        abort_unless($expectedState !== '' && hash_equals($expectedState, (string) $request->state), 419);

        $redirectUri = (string) $request->session()->pull(
            'discord_oauth_redirect_uri',
            $this->redirectUri()
        );

        try {
            $tokens = $discord->exchangeCode((string) $request->string('code'), $redirectUri);
            $profile = $discord->user($tokens['access_token']);
            $member = $discord->guildMember($profile['id']);
            $platformRole = $discord->platformRole($member);
            $existingUser = User::where('discord_id', $profile['id'])->with('staffProfile')->first();
            $previousRoleLabel = $existingUser?->role->label();

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

            if (
                $user->staffProfile
                && ($user->staffProfile->position === null || $user->staffProfile->position === $previousRoleLabel)
            ) {
                $user->staffProfile->update(['position' => $platformRole->label()]);
            }

            if ($member) {
                $memberSync->storeMember($member);
            }

            if (!$user->permissions_locked) {
                $this->syncPermissions($user);
            }
            Auth::login($user, true);
            $request->session()->regenerate();
        } catch (Throwable $exception) {
            Log::warning('Discord OAuth login failed.', [
                'message' => $exception->getMessage(),
                'redirect_uri' => $redirectUri,
            ]);

            return redirect()->route('discord.login')
                ->withErrors(['discord' => 'De Discord-login kon niet worden afgerond. Probeer opnieuw.']);
        }

        return redirect()->intended(route('dashboard'))->with('success', 'Welkom terug bij Connect Next.');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home', ['locale' => session('public_locale', app()->getLocale())]);
    }

    private function syncPermissions(User $user): void
    {
        $permissions = match ($user->role) {
            \App\Enums\UserRole::Owner => Permission::pluck('id'),
            \App\Enums\UserRole::Management => Permission::whereIn('name', [
                'staff.access', 'awards.review', 'awards.manage', 'tasks.manage',
                'hr.manage', 'partners.manage', 'content.manage', 'audit.view',
            ])->pluck('id'),
            \App\Enums\UserRole::Admin => Permission::whereIn('name', [
                'staff.access', 'awards.review', 'tasks.manage', 'content.manage',
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

    private function redirectUri(): string
    {
        return (string) (
            config('services.discord.redirect')
            ?: rtrim((string) config('app.url'), '/').'/auth/discord/callback'
        );
    }
}
