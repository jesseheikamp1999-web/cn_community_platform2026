@props(['user', 'class' => ''])

@php($initials = strtoupper(mb_substr($user->name, 0, 2)))

<span class="user-avatar-wrap {{ $class }}">
    @if($user->discord_avatar_url)
        <img class="user-avatar-image" src="{{ $user->discord_avatar_url }}" alt="" referrerpolicy="no-referrer" onerror="this.hidden=true; this.nextElementSibling.hidden=false">
        <span class="user-avatar-fallback" hidden>{{ $initials }}</span>
    @else
        <span class="user-avatar-fallback">{{ $initials }}</span>
    @endif
</span>
