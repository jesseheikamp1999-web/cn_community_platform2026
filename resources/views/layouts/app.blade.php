<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'CN Community Platform 2026')</title>
    <meta name="description" content="@yield('description', 'Het onafhankelijke community-platform van CN Community. Samen zijn we één.')">
    <link rel="canonical" href="{{ url()->current() }}">
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/site-enhancements.css') }}">
    <script defer src="{{ asset('assets/js/app.js') }}"></script>
    @stack('head')
</head>
<body>
    <div class="topline"></div>
    <header class="site-header">
        <a class="brand" href="{{ route('home') }}" aria-label="CN Community home">
            <img class="brand-logo" src="{{ asset('assets/images/cn-logo.png') }}" alt="CN Community">
            <span><strong>Community</strong><small>Platform 2026</small></span>
        </a>
        <button class="nav-toggle" data-nav-toggle aria-label="Menu openen"><span></span><span></span></button>
        <nav class="main-nav" data-nav>
            <a class="{{ request()->routeIs('home') ? 'active' : '' }}" href="{{ route('home') }}">Home</a>
            <a class="{{ request()->routeIs('awards') ? 'active' : '' }}" href="{{ route('awards') }}">Awards</a>
            <a href="{{ route('mini.awards') }}">Mini Awards</a>
            <a href="{{ route('nieuws') }}">Nieuws</a>
            <a href="{{ route('partners') }}">Partners</a>
            <a href="{{ route('staff') }}">Staff</a>
        </nav>
        <div class="header-actions">
            <a class="icon-button" href="{{ route('search') }}" aria-label="Zoeken">⌕</a>
            @auth
                <a class="button button-dark button-small" href="{{ route('dashboard') }}">MijnCN</a>
                <form class="header-logout" method="post" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" aria-label="Uitloggen" title="Uitloggen">@include('components.icon', ['name' => 'logout'])</button>
                </form>
            @else
                <a class="button button-dark button-small" href="{{ route('discord.login') }}">
                    <span class="discord-dot"></span> Inloggen
                </a>
            @endauth
        </div>
    </header>

    @if(session('success'))
        <div class="flash">{{ session('success') }}</div>
    @endif

    <main>@yield('content')</main>

    <footer class="footer">
        <div class="footer-main">
            <div>
                <a class="brand brand-light" href="{{ route('home') }}">
                    <img class="brand-logo" src="{{ asset('assets/images/cn-logo.png') }}" alt="CN Community">
                    <span><strong>Community</strong><small>Samen zijn we één.</small></span>
                </a>
                <p>Een plek waar mensen groeien, bijdragen en samen iets bijzonders bouwen.</p>
            </div>
            <div><strong>Platform</strong><a href="{{ route('awards') }}">CN Awards</a><a href="{{ route('mini.awards') }}">Mini Awards</a><a href="{{ route('nieuws') }}">Nieuws</a></div>
            <div><strong>Community</strong><a href="{{ route('partners') }}">Partners</a><a href="{{ route('staff') }}">Staff</a><a href="{{ route('solliciteren') }}">Solliciteren</a></div>
            <div><strong>Contact</strong><a href="{{ route('contact') }}">Contact</a><a href="{{ route('partner.worden') }}">Partner worden</a><a href="#">Privacy</a></div>
        </div>
        <div class="footer-bottom"><span>© {{ date('Y') }} CN Community</span><span>Onafhankelijk gebouwd. Verbonden via Discord.</span></div>
    </footer>
    @stack('scripts')
</body>
</html>
