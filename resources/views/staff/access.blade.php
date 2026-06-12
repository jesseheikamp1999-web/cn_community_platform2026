@extends('layouts.dashboard')
@section('title', 'Rollen & permissies - MijnCN')
@section('content')
<main class="module-shell">
    <header class="module-header"><div><span class="dashboard-kicker">EIGENAAR &middot; TOEGANG</span><h1>Rollen & permissies</h1><p>Beheer platformrollen en geef redacteurs of beheerders alleen de rechten die zij nodig hebben.</p></div></header>
    <div class="access-grid">
        @foreach($users as $member)
            <form class="module-card access-card" method="post" action="{{ route('staff.access.update', $member) }}">@csrf @method('PUT')
                <header>@include('components.user-avatar', ['user' => $member])<div><h2>{{ $member->name }}</h2><p>{{ '@'.($member->discord_username ?: 'geen-discord') }}</p></div></header>
                <label>Platformrol<select name="role">@foreach($roles as $role)<option value="{{ $role->value }}" @selected($member->role === $role)>{{ $role->label() }}</option>@endforeach</select></label>
                @foreach($permissions as $group => $items)<fieldset><legend>{{ $group }}</legend>@foreach($items as $permission)<label class="permission-check"><input type="checkbox" name="permissions[]" value="{{ $permission->id }}" @checked($member->permissions->contains($permission))><span><strong>{{ $permission->label }}</strong><small>{{ $permission->name }}</small></span></label>@endforeach</fieldset>@endforeach
                <button class="button button-primary button-small">Toegang opslaan</button>
            </form>
        @endforeach
    </div>
</main>
@endsection
