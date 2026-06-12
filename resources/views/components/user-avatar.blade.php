@props(['user', 'class' => ''])

@if($user->discord_avatar_url)
    <img class="user-avatar-image {{ $class }}" src="{{ $user->discord_avatar_url }}" alt="Discord-avatar van {{ $user->name }}" referrerpolicy="no-referrer">
@else
    <span class="user-avatar-fallback {{ $class }}">{{ strtoupper(substr($user->name, 0, 2)) }}</span>
@endif
