<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'MijnCN')</title>
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/dashboard.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/site-enhancements.css') }}">
    <script defer src="{{ asset('assets/js/app.js') }}"></script>
</head>
<body class="app-body">
@php
    $currentUser = auth()->user();
    $unreadNotifications = \Illuminate\Support\Facades\Schema::hasTable('notifications')
        ? $currentUser->unreadNotifications()->count()
        : 0;
    $unreadMessages = \Illuminate\Support\Facades\Schema::hasTable('messages')
        ? \Illuminate\Support\Facades\DB::table('messages')
            ->where('recipient_id', $currentUser->id)
            ->whereNull('read_at')
            ->count()
        : 0;
    $level = max(1, intdiv($currentUser->xp, 500) + 1);
    $levelXp = $currentUser->xp % 500;
@endphp
<aside class="app-sidebar">
    <a class="app-logo" href="{{ route('home') }}"><img src="{{ asset('assets/images/cn-logo.png') }}" alt="CN Community"><span><strong>COMMUNITY</strong><small>MIJNCN PLATFORM</small></span></a>
    <div class="sidebar-profile">
        <div class="sidebar-avatar">@include('components.user-avatar', ['user' => $currentUser])<i></i></div>
        <div><strong>{{ $currentUser->name }}</strong><small>{{ '@'.($currentUser->discord_username ?: 'discord') }}</small></div>
        <a href="{{ route('mijncn.module', 'profile') }}" aria-label="Profiel bekijken">&middot;&middot;&middot;</a>
    </div>
    <div class="sidebar-level"><span>Level {{ $level }}</span><small>{{ $levelXp }} / 500 XP</small><i><b style="width:{{ $levelXp / 5 }}%"></b></i></div>
    <nav class="side-nav">
        <small>MIJN CN</small>
        <a class="{{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">@include('components.icon', ['name' => 'home']) <span>Dashboard</span></a>
        <a class="{{ request()->is('mijn-cn/profile') ? 'active' : '' }}" href="{{ route('mijncn.module', 'profile') }}">@include('components.icon', ['name' => 'user']) <span>Profiel</span></a>
        <a class="{{ request()->is('mijn-cn/notifications') ? 'active' : '' }}" href="{{ route('mijncn.module', 'notifications') }}">@include('components.icon', ['name' => 'bell']) <span>Meldingen</span>@if($unreadNotifications)<b>{{ $unreadNotifications }}</b>@endif</a>
        <a class="{{ request()->is('mijn-cn/inbox') ? 'active' : '' }}" href="{{ route('mijncn.module', 'inbox') }}">@include('components.icon', ['name' => 'mail']) <span>Inbox</span>@if($unreadMessages)<b>{{ $unreadMessages }}</b>@endif</a>
        <a class="{{ request()->is('mijn-cn/community') ? 'active' : '' }}" href="{{ route('mijncn.module', 'community') }}">@include('components.icon', ['name' => 'community']) <span>Communityleden</span></a>
        <a class="{{ request()->is('mijn-cn/birthdays') ? 'active' : '' }}" href="{{ route('mijncn.module', 'birthdays') }}">@include('components.icon', ['name' => 'calendar']) <span>Verjaardagen</span></a>
        @if($currentUser->role->value !== 'member')
            <a class="{{ request()->is('mijn-cn/absences') ? 'active' : '' }}" href="{{ route('mijncn.module', 'absences') }}">@include('components.icon', ['name' => 'calendar']) <span>Afwezigheid</span></a>
        @endif
        <small>AWARDS</small>
        <a class="{{ request()->is('mijn-cn/nominations') ? 'active' : '' }}" href="{{ route('mijncn.module', 'nominations') }}">@include('components.icon', ['name' => 'nomination']) <span>Mijn Nominaties</span></a>
        <a class="{{ request()->is('mijn-cn/votes') ? 'active' : '' }}" href="{{ route('mijncn.module', 'votes') }}">@include('components.icon', ['name' => 'vote']) <span>Mijn Stemmen</span></a>
        <a class="{{ request()->is('mijn-cn/results') ? 'active' : '' }}" href="{{ route('mijncn.module', 'results') }}">@include('components.icon', ['name' => 'result']) <span>Mijn Resultaten</span></a>
        <a href="{{ route('awards.hall') }}">@include('components.icon', ['name' => 'award']) <span>Hall of Fame</span></a>
        <small>ACADEMY</small>
        <a class="{{ request()->routeIs('academy.*') ? 'active' : '' }}" href="{{ route('academy.index') }}">@include('components.icon', ['name' => 'book']) <span>Academy Wereld</span></a>
        <a class="{{ request()->is('mijn-cn/certificates') ? 'active' : '' }}" href="{{ route('mijncn.module', 'certificates') }}">@include('components.icon', ['name' => 'certificate']) <span>Certificaten</span></a>
        <a class="{{ request()->is('mijn-cn/badges') ? 'active' : '' }}" href="{{ route('mijncn.module', 'badges') }}">@include('components.icon', ['name' => 'badge']) <span>Badges</span></a>
        @if($currentUser->hasPermission('staff.access'))
            <small>STAFF</small>
            <a class="{{ request()->routeIs('staff.dashboard') ? 'active' : '' }}" href="{{ route('staff.dashboard') }}">@include('components.icon', ['name' => 'result']) <span>Staff beheer</span></a>
            @if($currentUser->hasPermission('awards.review') || $currentUser->hasPermission('awards.manage') || $currentUser->hasPermission('jury.score'))
                <a class="{{ request()->routeIs('staff.awards*') ? 'active' : '' }}" href="{{ route('staff.awards') }}">@include('components.icon', ['name' => 'award']) <span>Awards beheer</span></a>
                <a class="{{ request()->routeIs('staff.mini-awards') ? 'active' : '' }}" href="{{ route('staff.mini-awards') }}">@include('components.icon', ['name' => 'award']) <span>Mini Awards beheer</span></a>
            @endif
            @if(in_array($currentUser->role->value, ['management', 'owner'], true))
                <a class="{{ request()->routeIs('staff.hr*') ? 'active' : '' }}" href="{{ route('staff.hr') }}">@include('components.icon', ['name' => 'user']) <span>HR & sollicitaties</span></a>
                <a class="{{ request()->routeIs('staff.academy*') ? 'active' : '' }}" href="{{ route('staff.academy') }}">@include('components.icon', ['name' => 'book']) <span>Academy beheer</span></a>
            @endif
            @if($currentUser->hasPermission('content.manage'))
                <a class="{{ request()->routeIs('staff.news.*') ? 'active' : '' }}" href="{{ route('staff.news.index') }}">@include('components.icon', ['name' => 'book']) <span>Nieuwsbeheer</span></a>
            @endif
            @if($currentUser->role->value === 'owner')
                <a class="{{ request()->routeIs('staff.access*') ? 'active' : '' }}" href="{{ route('staff.access') }}">@include('components.icon', ['name' => 'settings']) <span>Rollen & permissies</span></a>
            @endif
        @endif
        <small>OVERIG</small>
        <a class="{{ request()->is('mijn-cn/tasks') ? 'active' : '' }}" href="{{ route('mijncn.module', 'tasks') }}">@include('components.icon', ['name' => 'task']) <span>Takenbord</span></a>
        <a class="{{ request()->is('mijn-cn/nomi') ? 'active' : '' }}" href="{{ route('mijncn.module', 'nomi') }}">@include('components.icon', ['name' => 'spark']) <span>Nomi AI</span><em>NIEUW</em></a>
        <a class="{{ request()->is('mijn-cn/settings') ? 'active' : '' }}" href="{{ route('mijncn.module', 'settings') }}">@include('components.icon', ['name' => 'settings']) <span>Instellingen</span></a>
    </nav>
    <form class="sidebar-logout" method="post" action="{{ route('logout') }}">@csrf<button>@include('components.icon', ['name' => 'logout']) <span>Uitloggen</span></button></form>
</aside>
<div class="app-main">
    <header class="app-topbar">
        <button class="mobile-sidebar-toggle" data-sidebar-toggle aria-label="Navigatie openen">&#9776;</button>
        <form action="{{ route('search') }}"><span>&#8981;</span><input name="q" placeholder="Zoeken in CN..."><kbd>Ctrl K</kbd></form>
        <div class="topbar-actions">
            <a href="{{ route('mijncn.module', 'notifications') }}" aria-label="Meldingen">@include('components.icon', ['name' => 'bell']) @if($unreadNotifications)<b>{{ $unreadNotifications }}</b>@endif</a>
            <a href="{{ route('mijncn.module', 'inbox') }}" aria-label="Inbox">@include('components.icon', ['name' => 'mail']) @if($unreadMessages)<b>{{ $unreadMessages }}</b>@endif</a>
            <div class="topbar-profile-menu">
                <button class="topbar-user" type="button" data-profile-toggle aria-expanded="false" aria-haspopup="true">@include('components.user-avatar', ['user' => $currentUser])<div><strong>{{ $currentUser->name }}</strong><small>{{ $currentUser->role->label() }}</small></div><b aria-hidden="true">&#8964;</b></button>
                <div class="profile-dropdown" data-profile-menu>
                    <a href="{{ route('mijncn.module', 'profile') }}">@include('components.icon', ['name' => 'user']) <span>Mijn profiel</span></a>
                    <a href="{{ route('mijncn.module', 'settings') }}">@include('components.icon', ['name' => 'settings']) <span>Instellingen</span></a>
                    <form method="post" action="{{ route('logout') }}">@csrf<button type="submit">@include('components.icon', ['name' => 'logout']) <span>Uitloggen</span></button></form>
                </div>
            </div>
        </div>
    </header>
    @if(session('success'))<div class="flash">{{ session('success') }}</div>@endif
    @yield('content')
</div>
@stack('scripts')
</body>
</html>
