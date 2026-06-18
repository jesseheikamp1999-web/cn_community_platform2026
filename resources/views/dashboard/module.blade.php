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
                @else
                    <form class="module-form" method="post" action="{{ route('mijncn.absences.store') }}">
                        @csrf
                        <div class="module-form-grid">
                            <label>Vanaf<input type="datetime-local" name="starts_at" value="{{ old('starts_at', now()->format('Y-m-d\TH:i')) }}" required></label>
                            <label>Tot en met<input type="datetime-local" name="ends_at" value="{{ old('ends_at', now()->addHours(4)->format('Y-m-d\TH:i')) }}" required></label>
                        </div>
                        <label>Reden<textarea name="reason" rows="5" maxlength="1000" required placeholder="Laat kort weten waarom en wanneer je weer bereikbaar bent.">{{ old('reason') }}</textarea></label>
                        <p class="form-help">Tijdens deze periode staat op de publieke staffpagina automatisch "Niet beschikbaar".</p>
                        <button class="button button-primary">Afwezig melden</button>
                    </form>
                @endif
            </section>
            <section class="module-card">
                <div class="module-card-heading"><div><span>HISTORIE</span><h2>Eerdere meldingen</h2></div></div>
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

    @elseif($module === 'partners')
        <section class="module-card partner-manager">
            <div class="module-card-heading">
                <div><span>RANGLIJST</span><h2>Nieuw project toevoegen</h2></div>
                <a class="text-action" href="{{ route('partners') }}">Publieke pagina bekijken</a>
            </div>
            <form class="module-form partner-form" method="post" action="{{ route('mijncn.partners.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="module-form-grid">
                    <label>Naam<input name="name" required maxlength="80" placeholder="Bijvoorbeeld NightMC"></label>
                    <label>Website of Discord link<input name="website" type="url" placeholder="https://discord.gg/..."></label>
                    <label>Type<select name="category"><option value="server">Server</option><option value="project">Project</option><option value="partner">Partner</option><option value="creator">Creator</option></select></label>
                    <label>Status<select name="status"><option value="active">Actief</option><option value="pending">In aanvraag</option><option value="warning">Waarschuwing</option><option value="ended">Gestopt</option><option value="lead">Lead</option></select></label>
                    <label>Tier<input name="tier" value="community" required maxlength="40"></label>
                    <label>Score<input name="score" type="number" min="0" max="100" value="80" required></label>
                    <label>Positie<input name="position" type="number" min="1" max="999" value="{{ $partners->count() + 1 }}" required></label>
                    <label>Afbeelding<input name="logo" type="file" accept="image/jpeg,image/png,image/webp"></label>
                </div>
                <label>Omschrijving<textarea name="description" rows="3" maxlength="240" placeholder="Korte omschrijving voor de publieke kaart."></textarea></label>
                <label>Interne notitie<textarea name="notes" rows="2" maxlength="1000" placeholder="Niet publiek zichtbaar."></textarea></label>
                <label class="check-label"><input type="checkbox" name="is_featured" value="1" checked> Tonen op homepage</label>
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
                        <div><span>#{{ $partner->position }} · {{ strtoupper($partner->category) }}</span><h3>{{ $partner->name }}</h3><p>{{ $partner->score }}/100 punten</p></div>
                    </div>
                    <form class="module-form partner-form compact" method="post" action="{{ route('mijncn.partners.update', $partner) }}" enctype="multipart/form-data">
                        @csrf @method('PUT')
                        <input name="name" value="{{ $partner->name }}" required maxlength="80">
                        <textarea name="description" rows="2" maxlength="240" placeholder="Omschrijving">{{ $partner->description }}</textarea>
                        <input name="website" type="url" value="{{ $partner->website }}" placeholder="Website of Discord link">
                        <div class="module-form-grid small-grid">
                            <select name="category"><option value="server" @selected($partner->category === 'server')>Server</option><option value="project" @selected($partner->category === 'project')>Project</option><option value="partner" @selected($partner->category === 'partner')>Partner</option><option value="creator" @selected($partner->category === 'creator')>Creator</option></select>
                            <select name="status"><option value="active" @selected($partner->status === 'active')>Actief</option><option value="pending" @selected($partner->status === 'pending')>In aanvraag</option><option value="warning" @selected($partner->status === 'warning')>Waarschuwing</option><option value="ended" @selected($partner->status === 'ended')>Gestopt</option><option value="lead" @selected($partner->status === 'lead')>Lead</option></select>
                            <input name="tier" value="{{ $partner->tier }}" required maxlength="40">
                            <input name="score" type="number" min="0" max="100" value="{{ $partner->score }}" required>
                            <input name="position" type="number" min="1" max="999" value="{{ $partner->position }}" required>
                            <input name="logo" type="file" accept="image/jpeg,image/png,image/webp">
                        </div>
                        <textarea name="notes" rows="2" maxlength="1000" placeholder="Interne notitie">{{ $partner->notes }}</textarea>
                        <label class="check-label"><input type="checkbox" name="is_featured" value="1" @checked($partner->is_featured)> Tonen op homepage</label>
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
