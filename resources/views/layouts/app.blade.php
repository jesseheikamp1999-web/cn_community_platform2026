<!doctype html>
<html lang="{{ app()->getLocale() }}" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('public.brand.name'))</title>
    <meta name="description" content="@yield('description', __('public.brand.description'))">
    <link rel="canonical" href="{{ url()->current() }}">
    <link rel="alternate" hreflang="nl" href="{{ route(\Illuminate\Support\Facades\Route::currentRouteName(), array_merge(request()->route()?->parameters() ?? [], ['locale' => 'nl'])) }}">
    <link rel="alternate" hreflang="en" href="{{ route(\Illuminate\Support\Facades\Route::currentRouteName(), array_merge(request()->route()?->parameters() ?? [], ['locale' => 'en'])) }}">
    <meta property="og:title" content="@yield('title', __('public.brand.name'))">
    <meta property="og:description" content="@yield('description', __('public.brand.description'))">
    <meta property="og:type" content="website">
    <script type="application/ld+json">
        {
            "@context":"https://schema.org",
            "@type":"Organization",
            "name":"Connect Next",
            "url":"{{ url('/') }}",
            "description":"{{ __('public.brand.description') }}"
        }
    </script>
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/site-enhancements.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/connect-next.css') }}">
    <script defer src="{{ asset('assets/js/app.js') }}"></script>
    @stack('head')
</head>
<body class="connect-next-body">
    @php($currentLocale = app()->getLocale())
    <div class="topline"></div>
    <header class="site-header connect-header">
        <a class="brand" href="{{ route('home') }}" aria-label="Connect Next home">
            <span class="brand-mark">CN</span>
            <span>
                <strong>{{ __('public.brand.name') }}</strong>
                <small>{{ __('public.brand.tagline') }}</small>
            </span>
        </a>
        <button class="nav-toggle" data-nav-toggle aria-label="Menu openen"><span></span><span></span></button>
        <nav class="main-nav" data-nav>
            <a class="{{ request()->routeIs('home') ? 'active' : '' }}" href="{{ route('home') }}">{{ __('public.nav.home') }}</a>
            <a class="{{ request()->routeIs('ai') ? 'active' : '' }}" href="{{ route('ai') }}">{{ __('public.nav.ai') }}</a>
            <a class="{{ request()->routeIs('development') ? 'active' : '' }}" href="{{ route('development') }}">{{ __('public.nav.development') }}</a>
            <a class="{{ request()->routeIs('communities') ? 'active' : '' }}" href="{{ route('communities') }}">{{ __('public.nav.communities') }}</a>
            <a class="{{ request()->routeIs('awards*') ? 'active' : '' }}" href="{{ route('awards') }}">{{ __('public.nav.awards') }}</a>
            <a class="{{ request()->routeIs('projects') ? 'active' : '' }}" href="{{ route('projects') }}">{{ __('public.nav.projects') }}</a>
            <a class="{{ request()->routeIs('about') ? 'active' : '' }}" href="{{ route('about') }}">{{ __('public.nav.about') }}</a>
            <a class="{{ request()->routeIs('contact') ? 'active' : '' }}" href="{{ route('contact') }}">{{ __('public.nav.contact') }}</a>
        </nav>
        <div class="header-actions connect-header-actions">
            <a class="icon-button" href="{{ route('search') }}" aria-label="Zoeken">⌕</a>
            <button class="icon-button" type="button" data-theme-toggle aria-label="Dark mode wisselen">◐</button>
            <div class="locale-switcher">
                <a class="{{ $currentLocale === 'nl' ? 'active' : '' }}" href="{{ route(\Illuminate\Support\Facades\Route::currentRouteName(), array_merge(request()->route()?->parameters() ?? [], ['locale' => 'nl'])) }}">NL</a>
                <a class="{{ $currentLocale === 'en' ? 'active' : '' }}" href="{{ route(\Illuminate\Support\Facades\Route::currentRouteName(), array_merge(request()->route()?->parameters() ?? [], ['locale' => 'en'])) }}">EN</a>
            </div>
            @auth
                <a class="button button-dark button-small" href="{{ route('dashboard') }}">{{ __('public.nav.mycn') }}</a>
            @else
                <a class="button button-dark button-small" href="{{ route('discord.login') }}">
                    <span class="discord-dot"></span> {{ __('public.nav.login') }}
                </a>
            @endauth
        </div>
    </header>

    @if(session('success'))
        <div class="flash">{{ session('success') }}</div>
    @endif

    <main>@yield('content')</main>

    <footer class="footer connect-footer">
        <div class="footer-main">
            <div>
                <a class="brand brand-light" href="{{ route('home') }}">
                    <span class="brand-mark">CN</span>
                    <span>
                        <strong>{{ __('public.brand.name') }}</strong>
                        <small>{{ __('public.brand.tagline') }}</small>
                    </span>
                </a>
                <p>{{ __('public.brand.description') }}</p>
            </div>
            <div>
                <strong>{{ __('public.footer.services') }}</strong>
                <a href="{{ route('ai') }}">Connect Next AI</a>
                <a href="{{ route('development') }}">Connect Next Development</a>
                <a href="{{ route('communities') }}">Connect Next Communities</a>
            </div>
            <div>
                <strong>{{ __('public.footer.platform') }}</strong>
                <a href="{{ route('awards') }}">Connect Next Awards</a>
                <a href="{{ route('nieuws') }}">{{ __('public.nav.news') }}</a>
                <a href="{{ route('projects') }}">{{ __('public.nav.projects') }}</a>
            </div>
            <div>
                <strong>{{ __('public.footer.company') }}</strong>
                <a href="{{ route('about') }}">{{ __('public.nav.about') }}</a>
                <a href="{{ route('contact') }}">{{ __('public.nav.contact') }}</a>
                <a href="{{ route('partners') }}">Partners</a>
            </div>
        </div>
        <div class="footer-bottom">
            <span>© {{ date('Y') }} {{ __('public.brand.name') }}. {{ __('public.footer.rights') }}</span>
            <span>{{ __('public.brand.tagline') }}</span>
        </div>
    </footer>
    @stack('scripts')
</body>
</html>
