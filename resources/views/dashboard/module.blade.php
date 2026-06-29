@extends('layouts.dashboard')

@php
    $titles = [
        'profile' => ['Mijn profiel', 'Je Discord-profiel en persoonlijke gegevens op één plek.'],
        'notifications' => ['Meldingen', 'Updates over Awards, Academy, taken en jouw account.'],
        'inbox' => ['Inbox', 'Persoonlijke en interne berichten binnen CN Community.'],
        'nominations' => ['Mijn nominaties', 'Bekijk de personen en communities die jij hebt genomineerd.'],
        'votes' => ['Mijn stemmen', 'Een overzicht van jouw uitgebrachte Awards-stemmen.'],
        'results' => ['Mijn resultaten', 'Finaleplaatsen en gepubliceerde Awards-resultaten.'],
        'lessons' => ['Mijn lessen', 'Werk stap voor stap aan jouw ontwikkeling binnen CN.'],
        'exams' => ['Examens', 'Quizzen, examens en praktijkscenario’s uit de Academy.'],
        'certificates' => ['Certificaten', 'Jouw officieel behaalde CN Academy-certificaten.'],
        'badges' => ['Badges', 'Erkenning voor bijdragen, groei en behaalde mijlpalen.'],
        'tasks' => ['Takenbord', 'Open, toegewezen en afgeronde werkzaamheden.'],
        'nomi' => ['Nomi AI', 'Stel vragen over de community, Academy of staffprocessen.'],
        'settings' => ['Instellingen', 'Beheer privacy, verjaardag en persoonlijke voorkeuren.'],
        'absences' => ['Afwezigheid', 'Geef aan wanneer je tijdelijk niet beschikbaar bent voor CN.'],
        'birthdays' => ['Verjaardagen', 'Bekijk aankomende verjaardagen volgens de privacyvoorkeuren van leden.'],
        'community' => ['Communityleden', 'De mensen die via Discord zijn ingelogd en onderdeel zijn van MijnCN.'],
        'pulse' => ['CN Pulse', 'Live community-feed met nieuws, awards, verjaardagen, staffstatus en mijlpalen.'],
        'discord' => ['Bot & Discord', 'Koppel CN Pulse-events aan Discord-kanalen en test bot-pushes.'],
        'partners' => ['Projecten & partners', 'Beheer de publieke server- en projectranglijst van CN.'],
    ];
    [$pageTitle, $pageDescription] = $titles[$module];
@endphp

@section('title', $pageTitle.' — MijnCN')

@section('content')
<div class="dashboard-shell module-shell">
    <header class="module-header">
        <div><span class="dashboard-kicker">MIJN CN · {{ strtoupper($user->role->label()) }}</span><h1>{{ $pageTitle }}</h1><p>{{ $pageDescription }}</p></div>
        @if(in_array($module, ['nominations', 'votes', 'results'], true))
            <a class="button button-primary" href="{{ route('awards') }}">Open Awards</a>
        @elseif(in_array($module, ['profile', 'settings'], true))
            <span class="discord-connected"><i></i> Discord gekoppeld</span>
        @elseif($module === 'community' && in_array($user->role->value, ['management', 'owner'], true))
            <form method="post" action="{{ route('staff.discord.members.sync') }}">@csrf<button class="button button-primary">Discord synchroniseren</button></form>
        @elseif($module === 'discord')
            <form method="post" action="{{ route('mijncn.discord.upgrade') }}">@csrf<button class="button button-primary">Database bijwerken</button></form>
        @endif
    </header>

    @if($errors->any())<div class="module-alert error">{{ $errors->first() }}</div>@endif

    @if(in_array($module, ['profile', 'settings'], true))
        <div class="module-columns">
            <section class="module-card profile-overview-card">
                <div class="module-profile-head">
                    <div class="module-avatar">@include('components.user-avatar', ['user' => $user])<i></i></div>
                    <div><span class="role-chip inline">{{ $user->role->label() }}</span><h2>{{ $user->name }}</h2><p>{{ '@'.($user->discord_username ?: 'Discord-profiel') }}</p></div>
                </div>
                <div class="profile-facts">
                    <div><span>Discord ID</span><strong>{{ $user->discord_id ?: 'Niet beschikbaar' }}</strong></div>
                    <div><span>Level</span><strong>{{ max(1, intdiv($user->xp, 500) + 1) }}</strong></div>
                    <div><span>Totale XP</span><strong>{{ number_format($user->xp, 0, ',', '.') }}</strong></div>
                    <div><span>Lid sinds</span><strong>{{ $user->created_at->translatedFormat('d M Y') }}</strong></div>
                </div>
                @if($module === 'profile')
                    <div class="module-bio"><span>Over mij</span><p>{{ $user->profile_bio ?: 'Voeg via je instellingen een korte introductie toe.' }}</p></div>
                @endif
            </section>

            <section class="module-card">
                <div class="module-card-heading"><div><span>PERSOONLIJK</span><h2>Profielinstellingen</h2></div></div>
                <form class="module-form" method="post" action="{{ route('mijncn.profile.update') }}">
                    @csrf @method('PATCH')
                    <label>Over mij<textarea name="profile_bio" maxlength="280" rows="4" placeholder="Vertel kort iets over jouw rol binnen CN.">{{ old('profile_bio', $user->profile_bio) }}</textarea></label>
                    <div class="module-form-grid">
                        <label>Geboortedatum<input type="date" name="birth_date" value="{{ old('birth_date', $user->birth_date?->format('Y-m-d')) }}"></label>
                        <label>Zichtbaarheid<select name="birthday_visibility"><option value="private" @selected($user->birthday_visibility === 'private')>Privé</option><option value="staff" @selected($user->birthday_visibility === 'staff')>Alleen staff</option><option value="community" @selected($user->birthday_visibility === 'community')>Community</option></select></label>
                    </div>
                    <label class="check-label"><input type="checkbox" name="birthday_notifications" value="1" @checked($user->birthday_notifications)> Stuur mij verjaardagsmeldingen</label>
                    @if($user->role->value !== 'member')
                        <div class="staff-profile-fields">
                            <span class="dashboard-kicker">PUBLIEK STAFFPROFIEL</span>
                            <div class="module-form-grid">
                                <label>Publieke functie<input name="staff_position" maxlength="80" value="{{ old('staff_position', $user->publicPosition()) }}" placeholder="Bijvoorbeeld Moderator"></label>
                                <label>Status<select name="staff_public_status">
                                    @foreach(['active' => 'Beschikbaar', 'busy' => 'Druk', 'away' => 'Afwezig', 'vacation' => 'Vakantie'] as $value => $label)
                                        <option value="{{ $value }}" @selected(old('staff_public_status', $user->staffProfile?->public_status ?? 'active') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select></label>
                            </div>
                            <label>Staff bio<textarea name="staff_bio" rows="4" maxlength="500" placeholder="Korte bio voor de publieke staffkaart.">{{ old('staff_bio', $user->staffProfile?->bio) }}</textarea></label>
                            <div class="module-form-grid">
                                <label>Discord/profiel link<input type="url" name="staff_discord_url" value="{{ old('staff_discord_url', $user->staffProfile?->discord_url) }}" placeholder="https://discord.com/users/..."></label>
                                <label>Specialisaties<input name="staff_specialties" value="{{ old('staff_specialties', collect($user->staffProfile?->specialties ?? [])->implode(', ')) }}" placeholder="Moderatie, Events, Support"></label>
                            </div>
                        </div>
                    @endif
                    <button class="button button-primary">Instellingen opslaan</button>
                </form>
            </section>
        </div>
        @if($module === 'profile')
            <section class="module-card module-section">
                <div class="module-card-heading"><div><span>BEVEILIGING</span><h2>Recente logins</h2></div></div>
                <div class="module-list">
                    @forelse($loginHistory as $login)
                        <article><div class="list-icon">@include('components.icon', ['name' => 'user'])</div><div><strong>Discord-login</strong><p>{{ \Carbon\Carbon::parse($login->logged_in_at)->translatedFormat('d M Y · H:i') }}</p></div><span class="status status-approved">Geslaagd</span></article>
                    @empty
                        <div class="module-empty"><h3>Nog geen loginhistorie</h3><p>Nieuwe sessies worden hier veilig en zonder leesbare IP-adressen bijgehouden.</p></div>
                    @endforelse
                </div>
            </section>
        @endif

    @elseif($module === 'notifications')
        <section class="module-card">
            <div class="module-card-heading"><div><span>ACTUEEL</span><h2>Alle meldingen</h2></div><form method="post" action="{{ route('mijncn.notifications.read') }}">@csrf<button class="text-action">Alles gelezen</button></form></div>
            <div class="module-list">
                @forelse($notifications as $notification)
                    <article class="{{ $notification->read_at ? '' : 'unread' }}"><div class="list-icon">@include('components.icon', ['name' => 'bell'])</div><div><strong>{{ data_get($notification->data, 'title', 'Nieuwe melding') }}</strong><p>{{ data_get($notification->data, 'message', data_get($notification->data, 'body', 'Er is een nieuwe update voor jou.')) }}</p></div><time>{{ $notification->created_at->diffForHumans() }}</time></article>
                @empty
                    <div class="module-empty"><h3>Je bent helemaal bij</h3><p>Nieuwe updates verschijnen hier automatisch.</p></div>
                @endforelse
            </div>
            {{ $notifications->links() }}
        </section>

    @elseif($module === 'inbox')
        <section class="module-card">
            <div class="module-card-heading"><div><span>BERICHTEN</span><h2>Jouw gesprekken</h2></div></div>
            <div class="module-list">
                @forelse($messages as $message)
                    <article><div class="list-avatar">{{ strtoupper(substr($message->sender_name ?: 'CN', 0, 2)) }}</div><div><strong>{{ $message->sender_name ?: 'CN Community' }}</strong><p>{{ $message->body }}</p></div><time>{{ \Carbon\Carbon::parse($message->created_at)->diffForHumans() }}</time></article>
                @empty
                    <div class="module-empty"><h3>Je inbox is leeg</h3><p>Persoonlijke berichten van staff en management verschijnen hier.</p></div>
                @endforelse
            </div>
            {{ $messages->links() }}
        </section>

    @elseif($module === 'nominations')
        <section class="module-card">
            <div class="module-card-heading"><div><span>CN AWARDS</span><h2>{{ $nominations->total() }} nominaties</h2></div></div>
            <div class="module-list">
                @forelse($nominations as $nomination)
                    <article>
                        <div class="list-avatar">
                            @if($nomination->logo_url)
                                <img src="{{ $nomination->logo_url }}" alt="{{ $nomination->nominee_name }}">
                            @else
                                {{ strtoupper(substr($nomination->nominee_name, 0, 2)) }}
                            @endif
                        </div>
                        <div>
                            <strong>{{ $nomination->nominee_name }}</strong>
                            <p>{{ $nomination->category->name }} · {{ $nomination->category->edition->name }}</p>
                            <div class="module-inline-actions">
                                <a class="text-action" href="{{ route('mijncn.nominations.edit', $nomination) }}">Beheer profiel</a>
                                @if(in_array($nomination->status, ['approved', 'finalist', 'winner'], true))
                                    <a class="text-action muted" href="{{ route('awards.nomination', $nomination) }}" target="_blank" rel="noopener">Publiek bekijken</a>
                                @endif
                            </div>
                        </div>
                        <span class="status status-{{ $nomination->status }}">{{ ucfirst($nomination->status) }}</span>
                    </article>
                @empty
                    <div class="module-empty"><h3>Nog geen nominaties</h3><p>Geef iemand uit de community het podium dat diegene verdient.</p><a class="button button-primary button-small" href="{{ route('awards') }}">Iemand nomineren</a></div>
                @endforelse
            </div>
            {{ $nominations->links() }}
        </section>

    @elseif($module === 'votes')
        <section class="module-card">
            <div class="module-card-heading"><div><span>STEMGESCHIEDENIS</span><h2>{{ $votes->total() }} uitgebrachte stemmen</h2></div></div>
            <div class="module-list">
                @forelse($votes as $vote)
                    <article><div class="list-icon">@include('components.icon', ['name' => 'vote'])</div><div><strong>{{ $vote->nomination->nominee_name }}</strong><p>{{ $vote->nomination->category->name }} · {{ $vote->nomination->category->edition->name }}</p></div><span class="status {{ $vote->is_valid ? 'status-approved' : 'status-controle' }}">{{ $vote->is_valid ? 'Geldig' : 'Controle' }}</span></article>
                @empty
                    <div class="module-empty"><h3>Nog niet gestemd</h3><p>Zodra een stemronde opent, kun je via de Awards-pagina stemmen.</p></div>
                @endforelse
            </div>
            {{ $votes->links() }}
        </section>

    @elseif($module === 'results')
        <section class="module-card">
            <div class="module-card-heading"><div><span>HALL OF FAME</span><h2>Gepubliceerde resultaten</h2></div></div>
            <div class="module-list">
                @forelse($results as $result)
                    <article><div class="list-rank">#{{ $result->position }}</div><div><strong>{{ $result->nominee_name }}</strong><p>{{ $result->category_name }} · {{ $result->edition_name }}</p></div><span class="status status-winner">Winnaar</span></article>
                @empty
                    <div class="module-empty"><h3>Nog geen resultaten</h3><p>Finaleplaatsen en winnaars verschijnen zodra ze zijn gepubliceerd.</p></div>
                @endforelse
            </div>
            {{ $results->links() }}
        </section>

    @elseif(in_array($module, ['lessons', 'exams'], true))
        <section class="module-card">
            <div class="module-card-heading"><div><span>CN ACADEMY</span><h2>{{ $lessons->total() }} beschikbare {{ $module === 'exams' ? 'toetsen' : 'lessen' }}</h2></div></div>
            <div class="academy-grid">
                @forelse($lessons as $lesson)
                    @php($lessonProgress = $progress->get($lesson->id))
                    <article><div class="academy-card-icon">@include('components.icon', ['name' => $module === 'exams' ? 'exam' : 'book'])</div><span>{{ $lesson->path->name }}</span><h3>{{ $lesson->title }}</h3><p>{{ ucfirst($lesson->type) }} · {{ $lesson->xp_reward }} XP</p><div class="academy-card-footer"><span class="status status-{{ $lessonProgress->status ?? 'pending' }}">{{ $lessonProgress ? ucfirst($lessonProgress->status) : 'Nog starten' }}</span></div></article>
                @empty
                    <div class="module-empty wide"><h3>Nog niets gepubliceerd</h3><p>De Academy-inhoud verschijnt hier zodra een leerpad is vrijgegeven.</p></div>
                @endforelse
            </div>
            {{ $lessons->links() }}
        </section>

    @elseif($module === 'certificates')
        <section class="module-card">
            <div class="module-card-heading"><div><span>BEHAALD</span><h2>Mijn certificaten</h2></div></div>
            <div class="certificate-grid">
                @forelse($certificates as $certificate)
                    <article><div class="certificate-mark">CN</div><span>CN COMMUNITY ACADEMY</span><h3>{{ $certificate->title }}</h3><p>{{ $certificate->path_name }}</p><small>Uitgegeven op {{ \Carbon\Carbon::parse($certificate->issued_at)->translatedFormat('d F Y') }}</small></article>
                @empty
                    <div class="module-empty wide"><h3>Nog geen certificaten</h3><p>Voltooi een volledig leerpad om jouw eerste certificaat te verdienen.</p></div>
                @endforelse
            </div>
            {{ $certificates->links() }}
        </section>

    @elseif($module === 'badges')
        <section class="module-card">
            <div class="module-card-heading"><div><span>MIJLPALEN</span><h2>{{ count($earnedBadgeIds) }} van {{ $badges->count() }} badges behaald</h2></div></div>
            <div class="badge-grid">
                @forelse($badges as $badge)
                    <article class="{{ in_array($badge->id, $earnedBadgeIds, true) ? 'earned' : 'locked' }}"><div class="badge-medal" style="--badge-color:{{ $badge->color }}">@include('components.icon', ['name' => 'badge'])</div><h3>{{ $badge->name }}</h3><p>{{ $badge->description }}</p><span>{{ in_array($badge->id, $earnedBadgeIds, true) ? 'Behaald' : $badge->xp_required.' XP nodig' }}</span></article>
                @empty
                    <div class="module-empty wide"><h3>Nog geen badges ingesteld</h3><p>Management kan badges toevoegen voor communitymijlpalen.</p></div>
                @endforelse
            </div>
        </section>

    @elseif($module === 'tasks')
        <section class="module-card">
            <div class="module-card-heading"><div><span>WERKRUIMTE</span><h2>{{ $tasks->total() }} zichtbare taken</h2></div></div>
            <div class="task-module-grid">
                @forelse($tasks as $task)
                    <article><div class="task-module-top"><span class="priority-dot priority-{{ $task->priority }}"></span><span>{{ ucfirst(str_replace('_', ' ', $task->status)) }}</span><b>{{ ucfirst($task->priority) }}</b></div><h3>{{ $task->title }}</h3><p>{{ $task->description ?: 'Geen aanvullende omschrijving.' }}</p><div class="task-module-meta"><span>{{ $task->deadline?->translatedFormat('d M Y') ?? 'Geen deadline' }}</span><strong>{{ $task->progress }}%</strong></div><div class="task-progress"><i style="width:{{ $task->progress }}%"></i></div>
                        @if(!$task->claimed_by && app(\App\Services\TaskWorkflowService::class)->canClaim($task, $user))
                            <form method="post" action="{{ route('mijncn.tasks.claim', $task) }}">@csrf<button class="button button-secondary button-small">Taak claimen</button></form>
                        @elseif($task->claimed_by === $user->id && $task->status !== 'completed')
                            <form method="post" action="{{ route('mijncn.tasks.complete', $task) }}">@csrf<input name="note" placeholder="Korte afrondingsnotitie (optioneel)"><button class="button button-primary button-small">Voltooien</button></form>
                        @endif
                    </article>
                @empty
                    <div class="module-empty wide"><h3>Geen taken beschikbaar</h3><p>Open of aan jou toegewezen taken verschijnen hier.</p></div>
                @endforelse
            </div>
            {{ $tasks->links() }}
        </section>

    @elseif($module === 'absences')
        @php($weekStart = now()->startOfWeek())
        <div class="module-columns absence-layout">
            <section class="module-card">
                <div class="module-card-heading"><div><span>BESCHIKBAARHEID</span><h2>Afwezig melden</h2></div></div>
                @if($currentAbsence)
                    <div class="absence-current">
                        <span class="availability unavailable">Niet beschikbaar</span>
                        <h3>{{ ($currentAbsence->starts_at ?? $currentAbsence->starts_on)->translatedFormat('d M Y H:i') }} tot {{ ($currentAbsence->ends_at ?? $currentAbsence->ends_on)->translatedFormat('d M Y H:i') }}</h3>
                        <p>{{ $currentAbsence->reason }}</p>
                        <form method="post" action="{{ route('mijncn.absences.cancel', $currentAbsence) }}">
                            @csrf @method('DELETE')
                            <button class="button button-secondary">Afwezigheid intrekken</button>
                        </form>
                    </div>
                @endif
                    <form class="module-form absence-planner-form" method="post" action="{{ route('mijncn.absences.store') }}" data-absence-planner data-existing-absences='@json($approvedAbsenceBlocks ?? [])'>
                        @csrf
                        <input type="hidden" name="starts_at" data-absence-start value="{{ old('starts_at', now()->format('Y-m-d\TH:i')) }}">
                        <input type="hidden" name="ends_at" data-absence-end value="{{ old('ends_at', now()->addHours(4)->format('Y-m-d\TH:i')) }}">
                        <input type="hidden" name="absence_ranges" data-absence-ranges value="{{ old('absence_ranges') }}">
                        <div class="absence-planner">
                            <div class="absence-week-head">
                                <button type="button" data-week-shift="-7" aria-label="Vorige week">&larr;</button>
                                <strong data-week-title>{{ $weekStart->format('Y') }} - week {{ $weekStart->isoWeek() }}</strong>
                                <button type="button" data-week-shift="7" aria-label="Volgende week">&rarr;</button>
                            </div>
                            <div class="absence-legend">
                                <span><i class="past"></i> Voorbij</span>
                                <span><i class="selected"></i> Geselecteerd</span>
                                <span><i class="booked"></i> Al afwezig</span>
                            </div>
                            <div class="absence-hours">@for($hour = 0; $hour < 24; $hour++)<span>{{ $hour }}</span>@endfor</div>
                            <div class="absence-grid">
                                @foreach(range(0, 6) as $dayOffset)
                                    @php($day = $weekStart->copy()->addDays($dayOffset))
                                    <div class="absence-day-label"><b>{{ $day->translatedFormat('D') }}</b><span>{{ $day->format('d/m') }}</span></div>
                                    <div class="absence-hour-row">
                                        @for($hour = 0; $hour < 24; $hour++)
                                            <button type="button" data-absence-cell data-date="{{ $day->format('Y-m-d') }}" data-hour="{{ str_pad((string) $hour, 2, '0', STR_PAD_LEFT) }}" aria-label="{{ $day->translatedFormat('l') }} {{ $hour }}:00"></button>
                                        @endfor
                                    </div>
                                @endforeach
                            </div>
                            <p class="absence-selection" data-absence-summary>Selecteer in het rooster wanneer je niet beschikbaar bent.</p>
                        </div>
                        <div class="absence-quick-actions">
                            <button type="submit" class="absence-action absent">AFWEZIG MELDEN</button>
                            <button type="button" class="absence-action present" data-absence-clear>AANWEZIG</button>
                        </div>
                        <div class="module-form-grid">
                            <label>Reden type<select name="absence_type"><option value="afwezig">Afwezig</option><option value="vakantie">Vakantie</option><option value="school">School</option><option value="werk">Werk</option><option value="druk">Druk</option><option value="prive">Privé</option></select></label>
                            <label>Geselecteerde periode<small data-absence-range>Wordt gevuld vanuit het rooster</small></label>
                        </div>
                        <label>Reden<textarea name="reason" rows="5" maxlength="1000" required placeholder="Laat kort weten waarom en wanneer je weer bereikbaar bent.">{{ old('reason') }}</textarea></label>
                        <p class="form-help">Tijdens deze periode staat op de publieke staffpagina automatisch "Niet beschikbaar".</p>
                    </form>
            </section>
            <section class="module-card absence-team-board">
                <div class="module-card-heading">
                    <div><span>TEAMROOSTER</span><h2>Wie is wanneer afwezig?</h2></div>
                    <div class="absence-tabs" data-absence-tabs>
                        <button type="button" class="active" data-absence-filter="all">Alles</button>
                        <button type="button" data-absence-filter="now">Nu</button>
                        <button type="button" data-absence-filter="week">Deze week</button>
                    </div>
                </div>
                <div class="absence-team-list">
                    @forelse(($teamAbsenceUsers ?? collect()) as $teamUser)
                        @php($teamCurrent = $teamUser->absenceRequests->first(fn($absence) => ($absence->starts_at ?? $absence->starts_on->startOfDay())->lte(now()) && ($absence->ends_at ?? $absence->ends_on->endOfDay())->gte(now())))
                        @php($teamFuture = $teamUser->absenceRequests->first(fn($absence) => ($absence->ends_at ?? $absence->ends_on->endOfDay())->gte(now())))
                        <article data-absence-row data-absence-now="{{ $teamCurrent ? '1' : '0' }}" data-absence-week="{{ $teamFuture ? '1' : '0' }}">
                            <div class="list-avatar">@include('components.user-avatar', ['user' => $teamUser])</div>
                            <div>
                                <strong>{{ $teamUser->name }}</strong>
                                <span>{{ $teamUser->publicPosition() }}</span>
                                @if($teamFuture)
                                    <p>{{ ($teamFuture->starts_at ?? $teamFuture->starts_on)->translatedFormat('d M H:i') }} - {{ ($teamFuture->ends_at ?? $teamFuture->ends_on)->translatedFormat('d M H:i') }}</p>
                                    <small>{{ $teamFuture->reason }}</small>
                                @else
                                    <p>Geen geplande afwezigheid.</p>
                                @endif
                            </div>
                            <b class="{{ $teamCurrent ? 'is-away' : '' }}">{{ $teamCurrent ? 'Afwezig' : 'Beschikbaar' }}</b>
                        </article>
                    @empty
                        <div class="module-empty"><h3>Geen teamdata</h3><p>Staffleden verschijnen hier zodra ze een profiel hebben.</p></div>
                    @endforelse
                </div>
                <details class="absence-history">
                    <summary>Mijn eerdere meldingen</summary>
                    <div class="module-list">
                        @forelse($absences as $absence)
                            <article>
                                <div class="list-icon">@include('components.icon', ['name' => 'calendar'])</div>
                                <div><strong>{{ ($absence->starts_at ?? $absence->starts_on)->translatedFormat('d M Y H:i') }} - {{ ($absence->ends_at ?? $absence->ends_on)->translatedFormat('d M Y H:i') }}</strong><p>{{ $absence->reason }}</p></div>
                                <span class="status status-{{ $absence->status }}">{{ $absence->status === 'approved' ? 'Geregistreerd' : 'Ingetrokken' }}</span>
                            </article>
                        @empty
                            <div class="module-empty"><h3>Nog geen afwezigheid gemeld</h3><p>Je eerdere periodes verschijnen hier.</p></div>
                        @endforelse
                    </div>
                    {{ $absences->links() }}
                </details>
            </section>
        </div>

    @elseif($module === 'birthdays')
        <section class="module-card birthday-module">
            <div class="module-card-heading">
                <div><span>COMMUNITYKALENDER</span><h2>Aankomende verjaardagen</h2></div>
                <a class="text-action" href="{{ route('mijncn.module', 'settings') }}">Privacy instellen</a>
            </div>
            <div class="birthday-grid">
                @forelse($birthdays as $birthdayUser)
                    <article class="{{ $birthdayUser->days_until_birthday === 0 ? 'is-today' : '' }}">
                        <div class="birthday-avatar">@include('components.user-avatar', ['user' => $birthdayUser])</div>
                        <div>
                            <span>{{ $birthdayUser->days_until_birthday === 0 ? 'VANDAAG JARIG' : $birthdayUser->next_birthday->translatedFormat('d F') }}</span>
                            <h3>{{ $birthdayUser->name }}</h3>
                            <p>{{ $birthdayUser->days_until_birthday === 0 ? 'Stuur een felicitatie in Discord.' : 'Over '.$birthdayUser->days_until_birthday.' dagen' }}</p>
                        </div>
                        <b>{{ $birthdayUser->next_age }}</b>
                    </article>
                @empty
                    <div class="module-empty wide"><h3>Geen zichtbare verjaardagen</h3><p>Leden bepalen zelf of hun verjaardag privé, voor staff of voor de community zichtbaar is.</p></div>
                @endforelse
            </div>
        </section>

    @elseif($module === 'community')
        <section class="community-overview">
            <div class="community-metrics">
                <article><span>COMMUNITY LEDEN</span><strong>{{ number_format($communityStats['members'], 0, ',', '.') }}</strong><small>leden in CN Community Discord</small></article>
                <article><span>NU ACTIEF</span><strong>{{ number_format($communityStats['online'], 0, ',', '.') }}</strong><small>actief in de laatste 5 minuten</small></article>
                <article><span>STAFF</span><strong>{{ number_format($communityStats['staff'], 0, ',', '.') }}</strong><small>teamleden en jury</small></article>
                <article><span>BADGES BEHAALD</span><strong>{{ number_format($communityStats['badges'], 0, ',', '.') }}</strong><small>communitymijlpalen</small></article>
            </div>

            <section class="module-card community-directory">
                <div class="module-card-heading">
                    <div><span>MIJNCN DIRECTORY</span><h2>Ontdek de community</h2></div>
                    <form class="community-search" method="get" action="{{ route('mijncn.module', 'community') }}">
                        <input name="q" value="{{ $communitySearch }}" placeholder="Zoek naam of Discord-gebruiker">
                        <button class="button button-primary button-small">Zoeken</button>
                    </form>
                </div>
                <div class="community-member-grid">
                    @foreach($members as $member)
                        @php($profile = $member->mijncnUser)
                        @php($isOnline = $profile?->last_seen_at?->gte(now()->subMinutes(5)))
                        @php($isRecent = !$isOnline && $profile?->last_seen_at?->gte(now()->subDays(7)))
                        @php($level = $profile ? max(1, intdiv($profile->xp, 500) + 1) : null)
                        @php($role = \App\Enums\UserRole::tryFrom($member->platform_role))
                        <article class="community-member-card">
                            <div class="community-member-cover"><span>CN</span></div>
                            <div class="community-member-avatar">
                                @if($member->avatar_url)
                                    <img class="user-avatar-image" src="{{ $member->avatar_url }}" alt="Discord-avatar van {{ $member->display_name }}" referrerpolicy="no-referrer">
                                @else
                                    <span class="user-avatar-fallback">{{ strtoupper(substr($member->display_name, 0, 2)) }}</span>
                                @endif
                                <i class="{{ $isOnline ? 'online' : ($isRecent ? 'recent' : '') }}"></i>
                            </div>
                            <span class="role-chip inline">{{ $role?->label() ?? 'Lid' }}</span>
                            <h3>{{ $member->display_name }}</h3>
                            <p>{{ '@'.$member->username }}</p>
                            <div class="community-member-stats">
                                <span><b>{{ $level ? 'Level '.$level : 'Discord' }}</b>{{ $profile ? number_format($profile->xp, 0, ',', '.').' XP' : 'Nog niet ingelogd' }}</span>
                                <span><b>{{ $profile?->badges_count ?? 0 }}</b>badges</span>
                                <span><b>{{ $profile?->nominations_count ?? 0 }}</b>nominaties</span>
                            </div>
                            <div class="community-member-status">
                                <i class="{{ $isOnline ? 'online' : ($isRecent ? 'recent' : '') }}"></i>
                                {{ $isOnline ? 'Nu actief in MijnCN' : ($isRecent ? 'Recent actief in MijnCN' : ($profile ? 'MijnCN-lid' : 'Discord-lid')) }}
                            </div>
                        </article>
                    @endforeach
                    @if($members->isEmpty())
                        <div class="module-empty wide"><h3>Geen leden gevonden</h3><p>Probeer een andere naam of Discord-gebruikersnaam.</p></div>
                    @endif
                </div>
                {{ $members->links() }}
            </section>
        </section>

    @elseif($module === 'pulse')
        <section class="pulse-hero">
            @foreach($statusCards as $card)
                <article><span>{{ $card['label'] }}</span><strong>{{ $card['value'] }}</strong><small>{{ $card['hint'] }}</small></article>
            @endforeach
        </section>
        <section class="module-card">
            <div class="module-card-heading"><div><span>LIVE FEED</span><h2>Wat speelt er nu?</h2></div></div>
            <div class="pulse-feed">
                @forelse($pulseItems as $item)
                    <a href="{{ $item['url'] }}">
                        <span>{{ $item['type'] }}</span>
                        <strong>{{ $item['title'] }}</strong>
                        <p>{{ $item['description'] }}</p>
                        <time>{{ \Carbon\Carbon::parse($item['date'])->diffForHumans() }}</time>
                    </a>
                @empty
                    <div class="module-empty"><h3>Nog geen Pulse-items</h3><p>Zodra er nieuws, nominaties of staffupdates zijn, verschijnen ze hier.</p></div>
                @endforelse
            </div>
        </section>

    @elseif($module === 'discord')
        @unless($discordReady)
            <div class="module-alert error">
                Discord-kanalen zijn nog niet klaargezet. Klik op <strong>Database bijwerken</strong> om dit zonder artisan te installeren.
            </div>
        @endunless
        <section class="pulse-hero">
            @foreach($statusCards as $card)
                <article><span>{{ $card['label'] }}</span><strong>{{ $card['value'] }}</strong><small>{{ $card['hint'] }}</small></article>
            @endforeach
        </section>
        <section class="module-card discord-map-card">
            <div class="module-card-heading">
                <div><span>DISCORD CATEGORIEËN</span><h2>Nieuwe kanaalindeling</h2></div>
                @if($discordReady)
                    <div class="header-actions">
                        <form method="post" action="{{ route('mijncn.discord.panels') }}">@csrf<button class="text-action">Vaste panelen bijwerken</button></form>
                        <form method="post" action="{{ route('mijncn.discord.automation') }}">@csrf<button class="text-action">Automatisering draaien</button></form>
                    </div>
                @endif
            </div>
            <div class="discord-category-grid">
                <article><span>📌 | INFORMATIE</span><p>#📢┃aankondigingen<br>#👋┃welkom<br>#ℹ️┃informatie<br>#📚┃regels<br>#🎫┃support<br>#🧾┃changelog</p></article>
                <article><span>📡 | CN PULSE</span><p>#📡┃cn-pulse<br>#📰┃nieuws<br>#🎂┃verjaardagen<br>#📊┃dagelijkse-statistieken<br>#🎲┃events<br>#🎉┃giveaways</p></article>
                <article><span>🏆 | AWARDS</span><p>#🏆┃awards-info<br>#🗳️┃stem-nu<br>#🔥┃trending<br>#📈┃leaderboard<br>#📥┃award-logs</p></article>
            </div>
        </section>
        <section class="module-card discord-sync-card">
            <div class="module-card-heading">
                <div><span>BOT API</span><h2>Discord Sync API</h2></div>
                <code>{{ url('/api/discord-sync') }}</code>
            </div>
            <div class="discord-sync-overview">
                <article><span>API key</span><strong>{{ $discordSyncKeyHint ?? 'Niet ingesteld' }}</strong><small>Header: x-api-key</small></article>
                <article><span>Laatste request</span><strong>{{ $discordSyncLastRequest?->requested_at?->diffForHumans() ?? 'Nog geen request' }}</strong><small>{{ $discordSyncLastRequest ? ($discordSyncLastRequest->success ? 'Succes' : 'Fout') : 'Wacht op bot' }}</small></article>
                <article><span>Laatste items</span><strong>{{ $discordSyncLastRequest?->item_count ?? 0 }}</strong><small>gegenereerd voor bot</small></article>
            </div>
            <div class="discord-sync-columns">
                <div>
                    <h3>Actieve panelen</h3>
                    <div class="module-list compact-list">
                        @forelse($discordSyncPanels ?? [] as $panel)
                            <article><div><strong>{{ $panel['title'] ?? $panel['key'] }}</strong><p><code>{{ $panel['key'] }}</code> · {{ $panel['active'] ? 'Actief' : 'Uitgeschakeld' }} · refresh {{ $panel['refresh_after_seconds'] ?? 300 }}s @if($panel['channel_id']) · kanaal {{ $panel['channel_id'] }} @endif @if($panel['message_id']) · message {{ $panel['message_id'] }} @endif</p></div><span class="status status-{{ $panel['active'] ? 'approved' : 'controle' }}">{{ $panel['active'] ? 'actief' : 'uit' }}</span></article>
                        @empty
                            <div class="module-empty"><h3>Nog geen panelen</h3><p>Klik op Database bijwerken.</p></div>
                        @endforelse
                    </div>
                </div>
                <div>
                    <h3>Laatste sync-items</h3>
                    <div class="module-list compact-list">
                        @forelse($discordSyncItems ?? [] as $item)
                            <article><div><strong>{{ $item['type'] }} @if(isset($item['key'])) &middot; {{ $item['key'] }} @elseif(isset($item['id'])) &middot; {{ $item['id'] }} @endif</strong><p>{{ data_get($item, 'payload.title', $item['title'] ?? 'Geen titel') }}</p></div><span class="status status-approved">{{ $item['type'] }}</span></article>
                        @empty
                            <div class="module-empty"><h3>Geen items</h3><p>Er staat niets klaar voor de bot.</p></div>
                        @endforelse
                    </div>
                </div>
            </div>
            <div class="discord-sync-columns">
                <div>
                    <h3>Endpoint voorbeelden</h3>
                    <div class="module-list compact-list">
                        <article><div><strong>Volledige sync</strong><p><code>{{ $discordSyncDiagnostics['all_url'] ?? url('/api/discord-sync?channel=all') }}</code></p></div><span class="status status-approved">all</span></article>
                        <article><div><strong>Enkel paneel</strong><p><code>{{ $discordSyncDiagnostics['single_channel_example'] ?? url('/api/discord-sync?channel=awards-info') }}</code></p></div><span class="status status-approved">single</span></article>
                        <article><div><strong>Refresh</strong><p>Standaard {{ $discordSyncDiagnostics['default_refresh_after_seconds'] ?? 300 }} seconden · {{ $discordSyncDiagnostics['active_panels'] ?? 0 }} actieve panelen</p></div><span class="status status-approved">meta</span></article>
                    </div>
                </div>
                <div>
                    <h3>Bot headers</h3>
                    <div class="module-list compact-list">
                        <article><div><strong>x-api-key</strong><p>Gebruik dezelfde sleutel als in de botconfig en server `.env`.</p></div><span class="status status-approved">required</span></article>
                        <article><div><strong>known_version</strong><p>Bij `channel=awards-info` kun je de laatst bekende versie meesturen voor snelle `changed` checks.</p></div><span class="status status-approved">optional</span></article>
                        <article><div><strong>Response</strong><p>JSON only, met `generated_at`, `refresh_after_seconds`, `version` en `payload`.</p></div><span class="status status-approved">json</span></article>
                    </div>
                </div>
            </div>
            <div class="module-list compact-list">
                <h3>API requests</h3>
                @forelse($discordSyncRequests ?? [] as $requestLog)
                    <article><div><strong>{{ $requestLog->requested_at->translatedFormat('d M H:i:s') }}</strong><p><code>{{ $requestLog->channel_key ?: 'all' }}</code> · status {{ $requestLog->status_code ?? ($requestLog->success ? 200 : 500) }} · {{ $requestLog->error_message ?: 'Geen foutmelding.' }}</p></div><span class="status status-{{ $requestLog->success ? 'approved' : 'rejected' }}">{{ $requestLog->success ? 'success' : 'failed' }}</span></article>
                @empty
                    <div class="module-empty"><h3>Nog geen API requests</h3><p>De bot heeft het endpoint nog niet opgehaald.</p></div>
                @endforelse
            </div>
        </section>
        <section class="module-card discord-sync-editor">
            <div class="module-card-heading">
                <div><span>PANEEL TEKSTEN</span><h2>Sync panelen beheren</h2></div>
            </div>
            <div class="discord-sync-editor-grid">
                @foreach($discordSyncPanels ?? [] as $panel)
                    <form method="post" action="{{ route('mijncn.discord.sync.update', $panel['key']) }}" class="discord-sync-panel-form">
                        @csrf
                        @method('put')
                        <div class="discord-sync-panel-head">
                            <div>
                                <strong>{{ $panel['title'] ?? $panel['key'] }}</strong>
                                <p><code>{{ $panel['key'] }}</code></p>
                            </div>
                            <label class="check-label"><input type="checkbox" name="is_active" value="1" @checked($panel['active'])> Actief</label>
                        </div>
                        <label>Titel<input name="title" maxlength="120" required value="{{ old('title', $panel['title'] ?? $panel['key']) }}"></label>
                        <label>Beschrijving<textarea name="description" rows="3" maxlength="1000" required>{{ old('description', $panel['description'] ?? '') }}</textarea></label>
                        <div class="module-form-grid">
                            <label>Primaire knop<input name="button_label" maxlength="80" required value="{{ old('button_label', $panel['button_label'] ?? 'Open MijnCN') }}"></label>
                            <label>Primaire URL<input name="button_url" type="url" maxlength="500" required value="{{ old('button_url', $panel['button_url'] ?? route('dashboard')) }}"></label>
                            <label>Secundaire knop<input name="secondary_button_label" maxlength="80" value="{{ old('secondary_button_label', $panel['secondary_button_label'] ?? '') }}"></label>
                            <label>Secundaire URL<input name="secondary_button_url" type="url" maxlength="500" value="{{ old('secondary_button_url', $panel['secondary_button_url'] ?? '') }}"></label>
                            <label>Refresh (sec)<input name="refresh_after_seconds" type="number" min="30" max="3600" required value="{{ old('refresh_after_seconds', $panel['refresh_after_seconds'] ?? 300) }}"></label>
                        </div>
                        <button class="button button-secondary button-small">Paneel opslaan</button>
                    </form>
                @endforeach
            </div>
        </section>
        <section class="module-card">
            <div class="module-card-heading"><div><span>BOT PUSHES</span><h2>Kanalen koppelen</h2></div></div>
            <div class="discord-channel-list">
                @foreach($discordPurposes as $purpose => $config)
                    @php($channel = $discordChannels->get($purpose))
                    <article>
                        <div>
                            <strong>{{ $config['name'] }}</strong>
                            <p>{{ $config['description'] }}</p>
                        </div>
                        @if($discordReady)
                            <form method="post" action="{{ route('mijncn.discord.channel.save') }}" class="discord-channel-form">
                                @csrf
                                <input type="hidden" name="purpose" value="{{ $purpose }}">
                                <input name="name" value="{{ old('name', $channel?->name ?? $config['name']) }}" required>
                                <input name="discord_channel_id" value="{{ old('discord_channel_id', $channel?->discord_channel_id ?? '') }}" placeholder="Discord kanaal ID">
                                <input type="hidden" name="webhook_url" value="{{ old('webhook_url', $channel?->webhook_url) }}">
                                <label><input type="checkbox" name="is_active" value="1" @checked($channel?->is_active ?? true)> Actief</label>
                                <button class="button button-secondary button-small">Opslaan</button>
                            </form>
                            @if($channel)
                                <form method="post" action="{{ route('mijncn.discord.channel.test', $channel) }}">
                                    @csrf
                                    <button class="text-action">Testbericht sturen</button>
                                </form>
                                @if(in_array($purpose, ['cn-pulse', 'staff-status', 'awards-info', 'stem-nu', 'trending', 'leaderboard', 'award-logs'], true))
                                    <form method="post" action="{{ route('mijncn.discord.channel.panel', $channel) }}">
                                        @csrf
                                        <button class="text-action">{{ $channel->static_message_id ? 'Vast bericht updaten' : 'Vast bericht plaatsen' }}</button>
                                        @if($channel->static_message_updated_at)
                                            <small>Laatst: {{ $channel->static_message_updated_at->diffForHumans() }}</small>
                                        @endif
                                    </form>
                                @endif
                            @endif
                        @endif
                    </article>
                @endforeach
            </div>
        </section>
        <section class="module-card">
            <div class="module-card-heading"><div><span>DELIVERY LOG</span><h2>Laatste bot-pushes</h2></div></div>
            <div class="module-list">
                @forelse($discordDeliveries as $delivery)
                    <article>
                        <div class="list-icon">@include('components.icon', ['name' => 'mail'])</div>
                        <div><strong>{{ $delivery->event }} → {{ $delivery->channel?->name ?: 'standaard webhook' }}</strong><p>{{ $delivery->response ?: 'Geen foutmelding.' }}</p></div>
                        <span class="status status-{{ $delivery->status === 'sent' ? 'approved' : ($delivery->status === 'failed' ? 'rejected' : 'controle') }}">{{ $delivery->status }}</span>
                    </article>
                @empty
                    <div class="module-empty"><h3>Nog geen bot-pushes</h3><p>Stuur een testbericht of laat een automation draaien.</p></div>
                @endforelse
            </div>
        </section>

    @elseif($module === 'partners')
        @php($rankingReady = $partnerRankingsReady ?? true)
        @unless($rankingReady)
            <div class="module-alert error partner-upgrade-alert">
                <span>Partners werkt nu in basisbeheer. Klik op database bijwerken om score, positie, categorie, omschrijving en homepage-weergave aan te zetten.</span>
                <form method="post" action="{{ route('mijncn.partners.upgrade') }}">@csrf<button class="button button-primary button-small">Database bijwerken</button></form>
            </div>
        @endunless
        <section class="module-card partner-manager">
            <div class="module-card-heading">
                <div><span>{{ $rankingReady ? 'RANGLIJST' : 'BASISBEHEER' }}</span><h2>Nieuw project toevoegen</h2></div>
                <a class="text-action" href="{{ route('partners') }}">Publieke pagina bekijken</a>
            </div>
            <form class="module-form partner-form" method="post" action="{{ route('mijncn.partners.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="module-form-grid">
                    <label>Naam<input name="name" required maxlength="80" placeholder="Bijvoorbeeld NightMC"></label>
                    <label>Website of Discord link<input name="website" type="url" placeholder="https://discord.gg/..."></label>
                    @if($rankingReady)
                        <label>Discord invite<input name="discord_invite" type="url" placeholder="https://discord.gg/..."></label>
                        <label>Banner URL<input name="banner_url" type="url" placeholder="Wordt automatisch gevuld"></label>
                    @endif
                    @if($rankingReady)
                        <label>Type<select name="category"><option value="server">Server</option><option value="project">Project</option><option value="partner">Partner</option><option value="creator">Creator</option></select></label>
                    @endif
                    <label>Status<select name="status"><option value="active">Actief</option><option value="pending">In aanvraag</option><option value="warning">Waarschuwing</option><option value="ended">Gestopt</option><option value="lead">Lead</option></select></label>
                    <label>Tier<input name="tier" value="community" required maxlength="40"></label>
                    @if($rankingReady)
                        <label>Score<input name="score" type="number" min="0" max="100" value="80" required></label>
                        <label>Positie<input name="position" type="number" min="1" max="999" value="{{ $partners->count() + 1 }}" required></label>
                    @endif
                    <label>Afbeelding<input name="logo" type="file" accept="image/jpeg,image/png,image/webp"></label>
                </div>
                @if($rankingReady)
                    <label>Omschrijving<textarea name="description" rows="3" maxlength="240" placeholder="Korte omschrijving voor de publieke kaart."></textarea></label>
                @endif
                <label>Interne notitie<textarea name="notes" rows="2" maxlength="1000" placeholder="Niet publiek zichtbaar."></textarea></label>
                @if($rankingReady)
                    <label class="check-label"><input type="checkbox" name="is_featured" value="1" checked> Tonen op homepage</label>
                @endif
                <button class="button button-primary">Project toevoegen</button>
            </form>
        </section>

        <section class="partner-manager-grid">
            @foreach($partners as $partner)
                <article class="module-card partner-edit-card">
                    <div class="partner-edit-preview">
                        <div class="partner-rank-logo">
                            @if($partner->logo_url)
                                <img src="{{ $partner->logo_url }}" alt="">
                            @else
                                <span>{{ strtoupper(substr($partner->name, 0, 1)) }}</span>
                            @endif
                        </div>
                        <div>
                            <span>{{ $rankingReady ? '#'.($partner->position ?? $loop->iteration).' · '.strtoupper($partner->category ?? 'partner') : strtoupper($partner->status) }}</span>
                            <h3>{{ $partner->name }}</h3>
                            <p>{{ $rankingReady ? (($partner->score ?? 0).'/100 punten') : 'Basispartner' }}</p>
                        </div>
                    </div>
                    <form class="module-form partner-form compact" method="post" action="{{ route('mijncn.partners.update', $partner) }}" enctype="multipart/form-data">
                        @csrf @method('PUT')
                        <input name="name" value="{{ $partner->name }}" required maxlength="80">
                        @if($rankingReady)
                            <textarea name="description" rows="2" maxlength="240" placeholder="Omschrijving">{{ $partner->description }}</textarea>
                        @endif
                        <input name="website" type="url" value="{{ $partner->website }}" placeholder="Website of Discord link">
                        @if($rankingReady)
                            <input name="discord_invite" type="url" value="{{ $partner->discord_invite }}" placeholder="Discord invite">
                            <input name="banner_url" type="url" value="{{ $partner->banner_url }}" placeholder="Banner URL">
                        @endif
                        <div class="module-form-grid small-grid">
                            @if($rankingReady)
                                <select name="category"><option value="server" @selected($partner->category === 'server')>Server</option><option value="project" @selected($partner->category === 'project')>Project</option><option value="partner" @selected($partner->category === 'partner')>Partner</option><option value="creator" @selected($partner->category === 'creator')>Creator</option></select>
                            @endif
                            <select name="status"><option value="active" @selected($partner->status === 'active')>Actief</option><option value="pending" @selected($partner->status === 'pending')>In aanvraag</option><option value="warning" @selected($partner->status === 'warning')>Waarschuwing</option><option value="ended" @selected($partner->status === 'ended')>Gestopt</option><option value="lead" @selected($partner->status === 'lead')>Lead</option></select>
                            <input name="tier" value="{{ $partner->tier }}" required maxlength="40">
                            @if($rankingReady)
                                <input name="score" type="number" min="0" max="100" value="{{ $partner->score }}" required>
                                <input name="position" type="number" min="1" max="999" value="{{ $partner->position }}" required>
                            @endif
                            <input name="logo" type="file" accept="image/jpeg,image/png,image/webp">
                        </div>
                        <textarea name="notes" rows="2" maxlength="1000" placeholder="Interne notitie">{{ $partner->notes }}</textarea>
                        @if($rankingReady)
                            <label class="check-label"><input type="checkbox" name="is_featured" value="1" @checked($partner->is_featured)> Tonen op homepage</label>
                        @endif
                        <button class="button button-primary button-small">Opslaan</button>
                    </form>
                    <form method="post" action="{{ route('mijncn.partners.destroy', $partner) }}" onsubmit="return confirm('Project verwijderen?')">
                        @csrf @method('DELETE')
                        <button class="text-action danger">Verwijderen</button>
                    </form>
                </article>
            @endforeach
        </section>

    @elseif($module === 'nomi')
        <div class="nomi-layout">
            <section class="module-card nomi-intro"><div class="nomi-robot">@include('components.icon', ['name' => 'spark'])</div><span>NOMI AI ASSISTENT</span><h2>Waar kan ik je mee helpen?</h2><p>Nomi gebruikt de ingestelde CN-kennisbron voor vragen over de community, Academy en interne processen.</p></section>
            <section class="module-card">
                <form class="module-form" method="post" action="{{ route('mijncn.nomi.ask') }}">@csrf
                    <label>Onderwerp<select name="context"><option value="community">Community</option><option value="academy">Academy</option>@if($user->hasPermission('staff.access'))<option value="staff">Staff</option>@endif</select></label>
                    <label>Jouw vraag<textarea name="question" rows="6" required placeholder="Bijvoorbeeld: hoe werkt nomineren bij de CN Awards?">{{ old('question') }}</textarea></label>
                    <button class="button button-primary">Vraag aan Nomi</button>
                </form>
                @if(session('nomi_answer'))<div class="nomi-answer"><strong>Nomi</strong><p>{{ session('nomi_answer') }}</p></div>@endif
            </section>
        </div>
    @endif
</div>
@endsection
