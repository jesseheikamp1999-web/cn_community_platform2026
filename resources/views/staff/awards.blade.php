@extends('layouts.dashboard')
@section('title', ($isMiniAwards ? 'Mini Awards' : 'Awards').' beheer - MijnCN')

@section('content')
<div class="dashboard-shell module-shell awards-admin">
    <header class="module-header">
        <div>
            <span class="dashboard-kicker">STAFF &middot; {{ $isMiniAwards ? 'MINI AWARDS' : 'AWARDS SHOW' }}</span>
            <h1>{{ $edition->name }}</h1>
            <p>{{ $isMiniAwards ? 'Zelfstandige korte communityrondes met eigen categorieën, nominaties en stemmen.' : 'Controlekamer voor nominaties, community-stemmen, juryrapporten, finalisten en reveal.' }}</p>
        </div>
        <span class="status status-approved">{{ ucfirst($edition->status) }}</span>
    </header>

    <section class="award-command-grid">
        <article><span>Nominaties</span><strong>{{ $stats['nominations'] }}</strong><small>{{ $stats['unique_nominators'] }} unieke nominators</small></article>
        <article><span>Stemmen</span><strong>{{ $stats['votes'] }}</strong><small>geldige actieve stemmen</small></article>
        <article><span>Finalisten</span><strong>{{ $stats['finalists'] }}</strong><small>top 5 per categorie</small></article>
        <article><span>{{ $isMiniAwards ? 'Categorieën' : 'Juryleden' }}</span><strong>{{ $isMiniAwards ? $edition->categories->count() : $stats['jury_members'] }}</strong><small>{{ $isMiniAwards ? 'eigen mini-categorieën' : 'gekoppelde panelleden' }}</small></article>
    </section>

    @if(auth()->user()->hasPermission('awards.manage'))
        <div class="awards-admin-grid">
            @unless($isMiniAwards)<section class="module-card">
                <div class="module-card-heading"><div><span>INSTELLINGEN</span><h2>Awards status</h2></div></div>
                <form class="module-form" method="post" action="{{ route('staff.awards.phase', $edition) }}">@csrf @method('PATCH')
                    <label>Huidige fase<select name="status">@foreach($isMiniAwards ? ['draft','nominations','voting','finale','published','archived'] : ['draft','nominations','voting','jury','finale','published','archived'] as $status)<option value="{{ $status }}" @selected($edition->status === $status)>{{ ucfirst($status) }}</option>@endforeach</select></label>
                    <button class="button button-primary">Fase opslaan</button>
                </form>
                <div class="admin-action-row">
                    <form method="post" action="{{ route('staff.awards.winners.generate', $edition) }}">@csrf<button class="button button-secondary">Top 5 finalisten berekenen</button></form>
                    <form method="post" action="{{ route('staff.awards.winners.publish', $edition) }}">@csrf<button class="button button-primary">Hall of Fame publiceren</button></form>
                </div>
            </section>@endunless
            <section class="module-card">
                <div class="module-card-heading"><div><span>REVEAL</span><h2>Live winnaar onthullen</h2></div></div>
                <p class="award-help">Reveal per positie, van 5 naar 1. De eerste plaats wordt pas winnaar zodra positie 1 wordt onthuld.</p>
                <div class="reveal-buttons">
                    @foreach([5,4,3,2,1] as $position)
                        <form method="post" action="{{ route('staff.awards.reveal.position', [$edition, $position]) }}">@csrf<button class="button {{ $position === 1 ? 'button-primary' : 'button-secondary' }}">Reveal #{{ $position }}</button></form>
                    @endforeach
                </div>
            </section>
        </div>

        <section class="module-card module-section award-category-manager">
            <div class="module-card-heading">
                <div><span>CATEGORIEËN</span><h2>Categorieën beheren</h2></div>
                <strong>{{ $edition->categories->count() }} categorieën</strong>
            </div>

            <form class="module-form category-create-form" method="post" action="{{ route('staff.awards.categories.store', $edition) }}">
                @csrf
                <div class="category-form-grid">
                    <label>Naam<input name="name" required maxlength="120" placeholder="Bijvoorbeeld Beste Community"></label>
                    <label>Volgorde<input name="sort_order" type="number" min="0" max="999" value="{{ $edition->categories->max('sort_order') + 10 }}" required></label>
                    <label>Publiek gewicht<input name="public_weight" type="number" min="0" max="100" step="0.01" value="{{ $isMiniAwards ? 100 : 60 }}" required></label>
                    <label>Jurygewicht<input name="jury_weight" type="number" min="0" max="100" step="0.01" value="{{ $isMiniAwards ? 0 : 40 }}" required></label>
                    <label class="wide">Omschrijving<textarea name="description" rows="2" maxlength="1000" placeholder="Korte uitleg voor bezoekers en nominators"></textarea></label>
                    <label>Icoon<input name="icon" maxlength="80" placeholder="Optioneel"></label>
                    <label class="check-label"><input type="checkbox" name="is_active" value="1" checked> Direct actief</label>
                </div>
                <button class="button button-primary">Categorie toevoegen</button>
            </form>

            <div class="category-admin-list">
                @foreach($edition->categories as $category)
                    <article>
                        <form class="module-form category-edit-form" method="post" action="{{ route('staff.awards.categories.update', $category) }}">
                            @csrf
                            @method('PUT')
                            <div class="category-form-grid">
                                <label>Naam<input name="name" value="{{ $category->name }}" required maxlength="120"></label>
                                <label>Volgorde<input name="sort_order" type="number" min="0" max="999" value="{{ $category->sort_order }}" required></label>
                                <label>Publiek gewicht<input name="public_weight" type="number" min="0" max="100" step="0.01" value="{{ $category->public_weight }}" required></label>
                                <label>Jurygewicht<input name="jury_weight" type="number" min="0" max="100" step="0.01" value="{{ $category->jury_weight }}" required></label>
                                <label class="wide">Omschrijving<textarea name="description" rows="2" maxlength="1000">{{ $category->description }}</textarea></label>
                                <label>Icoon<input name="icon" value="{{ $category->icon }}" maxlength="80"></label>
                                <label class="check-label"><input type="checkbox" name="is_active" value="1" @checked($category->is_active)> Actief</label>
                            </div>
                            <footer>
                                <small>{{ $category->nominations_count }} nominaties · slug: {{ $category->slug }}</small>
                                <button class="button button-secondary button-small">Wijzigingen opslaan</button>
                            </footer>
                        </form>
                        <form method="post" action="{{ route('staff.awards.categories.destroy', $category) }}" onsubmit="return confirm('Categorie definitief verwijderen? Dit kan alleen zonder nominaties.')">
                            @csrf
                            @method('DELETE')
                            <button class="category-delete" aria-label="{{ $category->name }} verwijderen">Verwijderen</button>
                        </form>
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    <section class="module-card module-section">
        <div class="module-card-heading"><div><span>CONTROLE</span><h2>Nominaties beoordelen</h2></div></div>
        <div class="award-review-list">
            @foreach($nominations as $nomination)
                @php($score = $myScores->get($nomination->id))
                <article>
                    <div class="award-review-head">
                        <div class="list-avatar">{{ strtoupper(substr($nomination->nominee_name, 0, 2)) }}</div>
                        <div><span>{{ $nomination->category->name }}</span><h3>{{ $nomination->nominee_name }}</h3><p>Door {{ $nomination->user->name }} &middot; {{ ucfirst($nomination->status) }} &middot; spam {{ $nomination->spam_score ?? 0 }}%</p></div>
                    </div>
                    <p class="award-review-motivation">{{ $nomination->motivation }}</p>
                    @if($nomination->evidence_url || $nomination->evidence_text)
                        <div class="award-evidence"><strong>Bewijs</strong>@if($nomination->evidence_url)<a href="{{ $nomination->evidence_url }}" target="_blank" rel="noopener">Bewijslink openen</a>@endif @if($nomination->evidence_text)<p>{{ $nomination->evidence_text }}</p>@endif</div>
                    @endif

                    @if(auth()->user()->hasPermission('awards.review'))
                        <form class="review-actions stacked" method="post" action="{{ route('staff.awards.review', $nomination) }}">@csrf @method('PATCH')
                            <textarea name="review_note" rows="2" placeholder="Opmerking voor auditlog of afwijzing">{{ old('review_note', $nomination->review_note) }}</textarea>
                            <div><button class="button button-primary button-small" name="status" value="approved">Goedkeuren</button><button class="button button-secondary button-small" name="status" value="rejected">Afwijzen</button><button class="button button-secondary button-small" name="status" value="duplicate">Dubbel markeren</button></div>
                        </form>
                    @endif

                </article>
            @endforeach
            @if($nominations->isEmpty())<div class="module-empty"><h3>Alles beoordeeld</h3><p>Er staan geen nieuwe nominaties meer in behandeling.</p></div>@endif
        </div>
    </section>

    @if(!$isMiniAwards && auth()->user()->hasPermission('jury.score'))
        <section class="module-card module-section">
            <div class="module-card-heading"><div><span>JURY PANEL</span><h2>Goedgekeurde nominaties beoordelen</h2></div><strong>{{ $juryNominations->count() }} kandidaten</strong></div>
            <div class="jury-candidate-list">
                @forelse($juryNominations as $nomination)
                    @php($score = $myScores->get($nomination->id))
                    <article class="jury-candidate-card">
                        <header><div class="list-avatar">{{ strtoupper(substr($nomination->nominee_name, 0, 2)) }}</div><div><span>{{ $nomination->category->name }}</span><h3>{{ $nomination->nominee_name }}</h3><p>{{ $score ? 'Jouw score: '.round($score->score, 1).'%' : 'Nog niet door jou beoordeeld' }}</p></div><b class="status {{ $score ? 'status-approved' : 'status-pending' }}">{{ $score ? 'Beoordeeld' : 'Open' }}</b></header>
                        <details class="jury-panel" @if(!$score) open @endif>
                            <summary>{{ $score ? 'Juryrapport bekijken of aanpassen' : 'Juryrapport toevoegen' }}</summary>
                            <form class="jury-report-form" method="post" action="{{ route('staff.awards.jury.score', $nomination) }}">@csrf
                                <section class="jury-form-section">
                                    <div class="jury-form-heading"><span>01</span><div><h4>Beoordelingscriteria</h4><p>Geef per onderdeel een score van 0 tot 10.</p></div></div>
                                    <div class="jury-score-grid">
                                        @foreach(['impact_score'=>'Community impact','activity_score'=>'Activiteit','professionalism_score'=>'Professionaliteit','innovation_score'=>'Innovatie','future_score'=>'Toekomstpotentie'] as $field=>$label)
                                            <label><span>{{ $label }}</span><div class="jury-number"><input type="number" name="{{ $field }}" min="0" max="10" required value="{{ old($field, $score?->{$field} ?? 7) }}"><em>/10</em></div></label>
                                        @endforeach
                                    </div>
                                </section>
                                <section class="jury-form-section">
                                    <div class="jury-form-heading"><span>02</span><div><h4>Onderbouwd juryrapport</h4><p>Het totale rapport moet minimaal 100 woorden bevatten.</p></div></div>
                                    <div class="jury-report-grid">
                                        <label><span>Sterke punten</span><textarea name="strengths" rows="5" required placeholder="Welke onderdelen springen positief uit?">{{ old('strengths', $score?->strengths) }}</textarea></label>
                                        <label><span>Verbeterpunten</span><textarea name="improvements" rows="5" required placeholder="Waar liggen concrete verbeterkansen?">{{ old('improvements', $score?->improvements) }}</textarea></label>
                                        <label class="wide"><span>Persoonlijke toelichting</span><textarea name="personal_note" rows="6" required placeholder="Leg jouw eindafweging en score uit.">{{ old('personal_note', $score?->personal_note ?? $score?->report) }}</textarea></label>
                                    </div>
                                </section>
                                <footer><small>Maximaal 50 punten &middot; rapport verplicht</small><button class="button button-primary">Juryrapport opslaan</button></footer>
                            </form>
                        </details>
                    </article>
                @empty
                    <div class="module-empty"><h3>Nog geen kandidaten klaar voor jury</h3><p>Goedgekeurde nominaties verschijnen automatisch in dit panel.</p></div>
                @endforelse
            </div>
        </section>
    @endif

    <section class="module-card module-section">
        <div class="module-card-heading"><div><span>FINALE</span><h2>Finalisten en revealstatus</h2></div></div>
        <div class="module-list">
            @forelse($winners as $winner)
                <article><div class="list-rank">#{{ $winner->position }}</div><div><strong>{{ $winner->nominee_name }}</strong><p>{{ $winner->category_name }} &middot; community {{ round($winner->community_score, 1) }}% &middot; jury {{ round($winner->jury_score, 1) }}% &middot; totaal {{ round($winner->final_score, 1) }}%</p></div><span class="status {{ $winner->revealed_position_at ? 'status-approved' : 'status-pending' }}">{{ $winner->revealed_position_at ? 'Revealed' : 'Verborgen' }}</span></article>
            @empty
                <div class="module-empty"><h3>Nog geen finalisten</h3><p>Bereken eerst de top 5 finalisten.</p></div>
            @endforelse
        </div>
    </section>
</div>
@endsection
