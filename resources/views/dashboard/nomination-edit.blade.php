@extends('layouts.dashboard')

@section('title', 'Nominatieprofiel beheren - MijnCN')

@section('content')
<div class="dashboard-shell module-shell">
    <header class="module-header">
        <div>
            <span class="dashboard-kicker">MIJN CN · NOMINATIEPROFIEL</span>
            <h1>Beheer je nominatie</h1>
            <p>Maak de publieke nominatiepagina sterker met een goede tekst, logo, banner en links.</p>
        </div>
        <a class="button button-secondary" href="{{ route('mijncn.module', 'nominations') }}">Terug naar nominaties</a>
    </header>

    @if(session('success'))<div class="module-alert success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="module-alert error">{{ $errors->first() }}</div>@endif

    <div class="nomination-editor-grid">
        <section class="module-card">
            <div class="module-card-heading">
                <div><span>PROFIEL</span><h2>{{ $nomination->nominee_name }}</h2></div>
                @if(in_array($nomination->status, ['approved', 'finalist', 'winner'], true))
                    <a class="text-action" href="{{ route('awards.nomination', $nomination) }}" target="_blank" rel="noopener">Publiek bekijken</a>
                @else
                    <span class="status status-{{ $nomination->status }}">{{ ucfirst($nomination->status) }}</span>
                @endif
            </div>
            <form class="module-form nomination-profile-form" method="post" action="{{ route('mijncn.nominations.update', $nomination) }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <label>Naam van kandidaat, server of project
                    <input name="nominee_name" value="{{ old('nominee_name', $nomination->nominee_name) }}" required maxlength="120">
                </label>

                <label>Publieke profieltekst
                    <textarea name="motivation" rows="12" required minlength="40" maxlength="6000" placeholder="# Titel&#10;Schrijf waarom deze nominatie bijzonder is. Gebruik **dikgedrukte tekst** voor nadruk.">{{ old('motivation', $nomination->motivation) }}</textarea>
                </label>
                <p class="form-help">Formatting werkt met <code># Titel</code>, <code>## Subtitel</code>, <code>**dikgedrukt**</code>, lijstjes met <code>- item</code> en links.</p>

                <label>Extra bewijs of historie
                    <textarea name="evidence_text" rows="5" maxlength="4000">{{ old('evidence_text', $nomination->evidence_text) }}</textarea>
                </label>

                <div class="module-form-grid">
                    <label>Website
                        <input type="url" name="website_url" value="{{ old('website_url', $nomination->website_url) }}" placeholder="https://...">
                    </label>
                    <label>Discord invite
                        <input type="url" name="discord_invite" value="{{ old('discord_invite', $nomination->discord_invite) }}" placeholder="https://discord.gg/...">
                    </label>
                </div>

                <div class="module-form-grid">
                    <label>Logo URL
                        <input type="url" name="logo_url" value="{{ old('logo_url', $nomination->logo_url) }}" placeholder="https://...">
                    </label>
                    <label>Logo uploaden
                        <input type="file" name="logo_upload" accept=".jpg,.jpeg,.png,.gif,.webp">
                    </label>
                </div>

                <div class="module-form-grid">
                    <label>Banner URL
                        <input type="url" name="banner_url" value="{{ old('banner_url', $nomination->banner_url) }}" placeholder="https://...">
                    </label>
                    <label>Banner uploaden
                        <input type="file" name="banner_upload" accept=".jpg,.jpeg,.png,.gif,.webp">
                    </label>
                </div>

                <button class="button button-primary">Nominatieprofiel opslaan</button>
            </form>
        </section>

        <aside class="module-card nomination-preview-card">
            <div class="nomination-preview-hero" @if($nomination->banner_url) style="background-image:linear-gradient(135deg,rgba(16,17,21,.92),rgba(80,12,17,.78)),url('{{ $nomination->banner_url }}')" @endif>
                <div class="nomination-preview-logo">
                    @if($nomination->logo_url)<img src="{{ $nomination->logo_url }}" alt="{{ $nomination->nominee_name }}">@else{{ strtoupper(substr($nomination->nominee_name, 0, 2)) }}@endif
                </div>
            </div>
            <div class="nomination-preview-body">
                <span>{{ $nomination->category->name }}</span>
                <h2>{{ $nomination->nominee_name }}</h2>
                {!! \App\Support\SafeMarkdown::render($nomination->motivation) !!}
                <div class="nominee-links">
                    @if($nomination->website_url)<a class="button button-secondary button-small" href="{{ $nomination->website_url }}" target="_blank" rel="noopener">Website</a>@endif
                    @if($nomination->discord_invite)<a class="button button-secondary button-small" href="{{ $nomination->discord_invite }}" target="_blank" rel="noopener">Discord</a>@endif
                </div>
            </div>
        </aside>
    </div>
</div>
@endsection
