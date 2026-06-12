<?php

namespace App\Http\Controllers\Staff;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccessController extends Controller
{
    public function index(Request $request): View
    {
        $this->ownerOnly($request);
        $users = User::with('permissions')->orderByRaw("CASE role WHEN 'owner' THEN 1 WHEN 'management' THEN 2 WHEN 'admin' THEN 3 WHEN 'moderator' THEN 4 WHEN 'helper' THEN 5 ELSE 6 END")->orderBy('name')->get();
        $permissions = Permission::orderBy('group')->orderBy('label')->get()->groupBy('group');
        $roles = UserRole::cases();

        return view('staff.access', compact('users', 'permissions', 'roles'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->ownerOnly($request);
        $data = $request->validate([
            'role' => ['required', 'in:'.collect(UserRole::cases())->pluck('value')->implode(',')],
            'permissions' => ['array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ]);
        if ($user->is($request->user()) && $data['role'] !== UserRole::Owner->value) {
            return back()->withErrors(['role' => 'Je kunt je eigen eigenaarrol niet verwijderen.']);
        }

        $user->update(['role' => $data['role'], 'permissions_locked' => true]);
        $user->permissions()->sync($data['permissions'] ?? []);

        return back()->with('success', 'Rol en permissies van '.$user->name.' zijn bijgewerkt.');
    }

    private function ownerOnly(Request $request): void
    {
        abort_unless($request->user()->role === UserRole::Owner, 403);
    }
}
