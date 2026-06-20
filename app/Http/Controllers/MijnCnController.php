<?php

namespace App\Http\Controllers;

use App\Models\Badge;
use App\Models\AbsenceRequest;
use App\Models\AwardEdition;
use App\Models\DiscordChannel;
use App\Models\DiscordDelivery;
use App\Models\DiscordMember;
use App\Models\LearningPath;
use App\Models\Lesson;
use App\Models\Nomination;
use App\Models\Partner;
use App\Models\Task;
use App\Models\User;
use App\Services\CnPulseService;
use App\Services\CommunityAutomationService;
use App\Services\DiscordService;
use App\Services\NomiAiService;
use App\Services\TaskWorkflowService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;

class MijnCnController extends Controller
{
    public function show(Request $request, string $module): View
    {
        abort_unless(in_array($module, $this->modules(), true), 404);

        $user = $request->user();
        $data = ['module' => $module, 'user' => $user];

        if ($module === 'notifications') {
            $data['notifications'] = $user->notifications()->latest()->paginate(20);
        } elseif ($module === 'inbox') {
            DB::table('messages')->where('recipient_id', $user->id)->whereNull('read_at')->update(['read_at' => now()]);
            $data['messages'] = DB::table('messages')
                ->leftJoin('users as senders', 'senders.id', '=', 'messages.sender_id')
                ->where(fn ($query) => $query->where('messages.recipient_id', $user->id)->orWhere('messages.sender_id', $user->id))
                ->select('messages.*', 'senders.name as sender_name')
                ->latest('messages.created_at')
                ->paginate(20);
        } elseif ($module === 'nominations') {
            $data['nominations'] = $user->nominations()->with('category.edition')->latest()->paginate(20);
        } elseif ($module === 'votes') {
            $data['votes'] = $user->votes()->with('nomination.category.edition')->latest()->paginate(20);
        } elseif ($module === 'results') {
            $data['results'] = DB::table('award_winners')
                ->join('nominations', 'nominations.id', '=', 'award_winners.nomination_id')
                ->join('award_categories', 'award_categories.id', '=', 'award_winners.award_category_id')
                ->join('award_editions', 'award_editions.id', '=', 'award_categories.award_edition_id')
                ->where('nominations.user_id', $user->id)
                ->select('award_winners.*', 'nominations.nominee_name', 'award_categories.name as category_name', 'award_editions.name as edition_name')
                ->latest('award_winners.created_at')
                ->paginate(20);
        } elseif (in_array($module, ['lessons', 'exams'], true)) {
            $types = $module === 'exams' ? ['quiz', 'exam', 'scenario'] : ['lesson', 'assignment'];
            $data['lessons'] = Lesson::with('path')
                ->whereIn('type', $types)
                ->whereHas('path', fn ($query) => $query->where('is_published', true))
                ->orderBy('learning_path_id')
                ->orderBy('position')
                ->paginate(20);
            $data['progress'] = DB::table('lesson_progress')->where('user_id', $user->id)->get()->keyBy('lesson_id');
        } elseif ($module === 'certificates') {
            $data['certificates'] = DB::table('certificates')
                ->join('learning_paths', 'learning_paths.id', '=', 'certificates.learning_path_id')
                ->where('certificates.user_id', $user->id)
                ->select('certificates.*', 'learning_paths.name as path_name')
                ->latest('issued_at')
                ->paginate(20);
        } elseif ($module === 'badges') {
            $data['badges'] = Badge::orderBy('xp_required')->get();
            $data['earnedBadgeIds'] = $user->badges()->pluck('badges.id')->all();
        } elseif ($module === 'tasks') {
            $data['tasks'] = Task::with('creator')
                ->whereNull('archived_at')
                ->where(function ($query) use ($user) {
                    $query->where('is_public', true)
                        ->orWhere('claimed_by', $user->id)
                        ->orWhereHas('assignees', fn ($assignees) => $assignees->whereKey($user->id));
                })
                ->orderByRaw("CASE status WHEN 'in_progress' THEN 1 WHEN 'open' THEN 2 WHEN 'waiting' THEN 3 WHEN 'completed' THEN 4 ELSE 5 END")
                ->orderByRaw("CASE priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'normal' THEN 3 ELSE 4 END")
                ->paginate(30);
        } elseif ($module === 'profile') {
            $data['loginHistory'] = DB::table('login_histories')->where('user_id', $user->id)->latest('logged_in_at')->limit(8)->get();
        } elseif ($module === 'settings') {
            $data['paths'] = LearningPath::where('is_published', true)->orderBy('name')->get();
        } elseif ($module === 'absences') {
            abort_unless($user->role->value !== 'member', 403);
            $data['absences'] = $user->absenceRequests()->latest()->paginate(20);
            $data['currentAbsence'] = $user->absenceRequests()->current()->first();
            $data['approvedAbsenceBlocks'] = $user->absenceRequests()
                ->where('status', 'approved')
                ->where(function ($query) {
                    if (Schema::hasColumns('absence_requests', ['starts_at', 'ends_at'])) {
                        $query->whereNotNull('starts_at')->where('ends_at', '>=', now()->subWeeks(2));
                    } else {
                        $query->whereDate('ends_on', '>=', today()->subWeeks(2));
                    }
                })
                ->get()
                ->map(fn (AbsenceRequest $absence) => [
                    'start' => ($absence->starts_at ?? $absence->starts_on->startOfDay())->toIso8601String(),
                    'end' => ($absence->ends_at ?? $absence->ends_on->endOfDay())->toIso8601String(),
                    'reason' => $absence->reason,
                ])
                ->values();
            $data['teamAbsenceUsers'] = User::whereNot('role', 'member')
                ->with(['staffProfile', 'absenceRequests' => fn ($query) => $query
                    ->where('status', 'approved')
                    ->where(function ($period) {
                        if (Schema::hasColumns('absence_requests', ['starts_at', 'ends_at'])) {
                            $period->where('ends_at', '>=', now()->subWeek());
                        } else {
                            $period->whereDate('ends_on', '>=', today()->subWeek());
                        }
                    })
                    ->orderByRaw(Schema::hasColumn('absence_requests', 'starts_at') ? 'COALESCE(starts_at, starts_on)' : 'starts_on')
                    ->limit(12)])
                ->orderByRaw("CASE role WHEN 'owner' THEN 1 WHEN 'management' THEN 2 WHEN 'admin' THEN 3 WHEN 'moderator' THEN 4 WHEN 'helper' THEN 5 WHEN 'jury' THEN 6 ELSE 7 END")
                ->orderBy('name')
                ->get();
        } elseif ($module === 'birthdays') {
            $isStaff = $user->role->value !== 'member';
            $data['birthdays'] = User::whereNotNull('birth_date')
                ->where(function ($query) use ($isStaff, $user) {
                    $query->where('id', $user->id)
                        ->orWhere('birthday_visibility', 'community');
                    if ($isStaff) {
                        $query->orWhere('birthday_visibility', 'staff');
                    }
                })
                ->get()
                ->map(function (User $birthdayUser) {
                    $nextBirthday = today()->setDate(today()->year, $birthdayUser->birth_date->month, $birthdayUser->birth_date->day);
                    if ($nextBirthday->isBefore(today())) {
                        $nextBirthday->addYear();
                    }
                    $birthdayUser->next_birthday = $nextBirthday;
                    $birthdayUser->days_until_birthday = today()->diffInDays($nextBirthday);
                    $birthdayUser->next_age = $nextBirthday->year - $birthdayUser->birth_date->year;

                    return $birthdayUser;
                })
                ->sortBy('days_until_birthday')
                ->values();
        } elseif ($module === 'community') {
            $search = trim($request->string('q')->toString());
            $membersQuery = DiscordMember::query()
                ->where('is_active', true)
                ->where('is_bot', false);

            $data['communityStats'] = [
                'members' => (clone $membersQuery)->count(),
                'online' => (clone $membersQuery)->whereHas('mijncnUser', fn ($query) => $query->where('last_seen_at', '>=', now()->subMinutes(5)))->count(),
                'staff' => (clone $membersQuery)->whereNot('platform_role', 'member')->count(),
                'badges' => DB::table('badge_user')
                    ->join('users', 'users.id', '=', 'badge_user.user_id')
                    ->join('discord_members', 'discord_members.discord_id', '=', 'users.discord_id')
                    ->where('discord_members.is_active', true)
                    ->where('discord_members.is_bot', false)
                    ->count(),
            ];
            $data['members'] = $membersQuery
                ->with(['mijncnUser' => fn ($query) => $query->withCount(['badges', 'nominations'])])
                ->when($search !== '', fn ($query) => $query->where(function ($memberQuery) use ($search) {
                    $memberQuery->where('display_name', 'like', '%'.$search.'%')
                        ->orWhere('username', 'like', '%'.$search.'%');
                }))
                ->orderByRaw("CASE platform_role
                    WHEN 'owner' THEN 1
                    WHEN 'management' THEN 2
                    WHEN 'admin' THEN 3
                    WHEN 'moderator' THEN 4
                    WHEN 'helper' THEN 5
                    WHEN 'jury' THEN 6
                    ELSE 7 END")
                ->orderBy('display_name')
                ->paginate(24)
                ->withQueryString();
            $data['communitySearch'] = $search;
        } elseif ($module === 'pulse') {
            $pulse = app(CnPulseService::class);
            $data['pulseItems'] = $pulse->feed();
            $data['statusCards'] = $pulse->statusCards();
        } elseif ($module === 'discord') {
            abort_unless($this->canManageDiscord($user), 403);
            $pulse = app(CnPulseService::class);
            $data['discordReady'] = Schema::hasTable('discord_channels') && Schema::hasTable('discord_deliveries');
            $data['discordChannels'] = $data['discordReady']
                ? DiscordChannel::orderBy('purpose')->get()->keyBy('purpose')
                : collect();
            $data['discordDeliveries'] = $data['discordReady']
                ? DiscordDelivery::with('channel')->latest()->limit(12)->get()
                : collect();
            $data['discordPurposes'] = $this->discordPurposes() + [
                'leaderboard' => ['name' => 'leaderboard', 'description' => 'Actuele ranglijst met topnominaties en community-activiteit.'],
            ];
            $data['pulseItems'] = $pulse->feed(8);
            $data['statusCards'] = $pulse->statusCards();
        } elseif ($module === 'partners') {
            abort_unless($this->canManagePartners($user), 403);
            $partnerQuery = Partner::query();
            if (Schema::hasColumn('partners', 'position')) {
                $partnerQuery->orderBy('position');
            }
            if (Schema::hasColumn('partners', 'score')) {
                $partnerQuery->orderByDesc('score');
            }
            $data['partners'] = $partnerQuery->orderBy('name')->get();
            $data['partnerRankingsReady'] = Schema::hasColumns('partners', ['description', 'category', 'score', 'position', 'is_featured']);
        }

        return view('dashboard.module', $data);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $rules = [
            'profile_bio' => ['nullable', 'string', 'max:280'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'birthday_visibility' => ['required', 'in:private,staff,community'],
            'birthday_notifications' => ['nullable', 'boolean'],
        ];

        if ($request->user()->role->value !== 'member') {
            $rules += [
                'staff_bio' => ['nullable', 'string', 'max:500'],
                'staff_position' => ['nullable', 'string', 'max:80'],
                'staff_public_status' => ['nullable', 'in:active,busy,away,vacation'],
                'staff_discord_url' => ['nullable', 'url', 'max:255'],
                'staff_specialties' => ['nullable', 'string', 'max:255'],
            ];
        }

        $data = $request->validate($rules);
        $data['birthday_notifications'] = $request->boolean('birthday_notifications');
        $request->user()->update(collect($data)->only([
            'profile_bio', 'birth_date', 'birthday_visibility', 'birthday_notifications',
        ])->all());

        if ($request->user()->role->value !== 'member') {
            $profileData = [
                'position' => $data['staff_position'] ?: $request->user()->role->label(),
                'bio' => $data['staff_bio'] ?? null,
                'status' => in_array($data['staff_public_status'] ?? 'active', ['away', 'vacation'], true) ? 'absent' : 'active',
                'joined_at' => $request->user()->staffProfile?->joined_at ?? $request->user()->created_at->toDateString(),
            ];

            if (Schema::hasColumn('staff_profiles', 'public_status')) {
                $profileData['public_status'] = $data['staff_public_status'] ?? 'active';
            }
            if (Schema::hasColumn('staff_profiles', 'discord_url')) {
                $profileData['discord_url'] = $data['staff_discord_url'] ?? null;
            }
            if (Schema::hasColumn('staff_profiles', 'specialties')) {
                $profileData['specialties'] = collect(explode(',', (string) ($data['staff_specialties'] ?? '')))
                    ->map(fn (string $specialty) => trim($specialty))
                    ->filter()
                    ->take(6)
                    ->values()
                    ->all();
            }

            $request->user()->staffProfile()->updateOrCreate(['user_id' => $request->user()->id], $profileData);
        }

        return back()->with('success', 'Je MijnCN-profiel is bijgewerkt.');
    }

    public function markNotificationsRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('success', 'Alle meldingen zijn als gelezen gemarkeerd.');
    }

    public function askNomi(Request $request, NomiAiService $nomi): RedirectResponse
    {
        $data = $request->validate([
            'question' => ['required', 'string', 'min:4', 'max:1000'],
            'context' => ['required', 'in:community,academy,staff'],
        ]);

        try {
            $answer = $nomi->ask($data['question'], $data['context']);
        } catch (RuntimeException $exception) {
            return back()->withInput()->withErrors(['question' => $exception->getMessage()]);
        }

        return back()->withInput()->with('nomi_answer', $answer);
    }

    public function claimTask(Request $request, Task $task, TaskWorkflowService $workflow): RedirectResponse
    {
        $workflow->claim($task, $request->user());

        return back()->with('success', 'De taak staat nu op jouw naam.');
    }

    public function completeTask(Request $request, Task $task, TaskWorkflowService $workflow): RedirectResponse
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:1000']]);
        $workflow->complete($task, $request->user(), $data['note'] ?? null);

        return back()->with('success', 'De taak is voltooid.');
    }

    public function reportAbsence(Request $request): RedirectResponse
    {
        abort_unless($request->user()->role->value !== 'member', 403);
        $data = $request->validate([
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'absence_ranges' => ['nullable', 'json'],
            'absence_type' => ['nullable', 'in:afwezig,druk,vakantie,school,werk,prive'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $ranges = collect(json_decode((string) ($data['absence_ranges'] ?? '[]'), true))
            ->filter(fn ($range) => is_array($range) && isset($range['start'], $range['end']))
            ->map(fn ($range) => [
                'start' => Carbon::parse($range['start']),
                'end' => Carbon::parse($range['end']),
            ])
            ->filter(fn ($range) => $range['end']->gt($range['start']))
            ->take(28)
            ->values();

        if ($ranges->isEmpty() && !empty($data['starts_at']) && !empty($data['ends_at'])) {
            $ranges = collect([[
                'start' => Carbon::parse($data['starts_at']),
                'end' => Carbon::parse($data['ends_at']),
            ]]);
        }

        if ($ranges->isEmpty()) {
            return back()->withErrors(['starts_at' => 'Selecteer minimaal één afwezigheidsperiode.'])->withInput();
        }

        $created = 0;
        foreach ($ranges as $range) {
            $absence = [
                'starts_on' => $range['start']->toDateString(),
                'ends_on' => $range['end']->toDateString(),
                'reason' => '['.($data['absence_type'] ?? 'afwezig').'] '.$data['reason'],
                'status' => 'approved',
            ];
            if (Schema::hasColumns('absence_requests', ['starts_at', 'ends_at'])) {
                $absence['starts_at'] = $range['start'];
                $absence['ends_at'] = $range['end'];
            }
            $request->user()->absenceRequests()->create($absence);
            $created++;
        }

        $currentlyAbsent = $ranges->contains(fn ($range) => $range['start']->lte(now()) && $range['end']->gte(now()));
        if ($currentlyAbsent) {
            $request->user()->staffProfile()->updateOrCreate(
                ['user_id' => $request->user()->id],
                [
                    'position' => $request->user()->role->label(),
                    'status' => 'absent',
                    ...(Schema::hasColumn('staff_profiles', 'public_status') ? ['public_status' => ($data['absence_type'] ?? 'afwezig') === 'vakantie' ? 'vacation' : 'away'] : []),
                    'joined_at' => $request->user()->created_at->toDateString(),
                ]
            );
        }

        return back()->with('success', $created === 1
            ? 'Je staat voor deze periode op niet beschikbaar.'
            : 'Je afwezigheid is voor '.$created.' periodes geregistreerd.');
    }

    public function cancelAbsence(Request $request, AbsenceRequest $absence): RedirectResponse
    {
        abort_unless($absence->user_id === $request->user()->id, 403);
        $absence->update(['status' => 'rejected']);

        if (!$request->user()->isCurrentlyAbsent()) {
            $request->user()->staffProfile()->update([
                'status' => 'active',
                ...(Schema::hasColumn('staff_profiles', 'public_status') ? ['public_status' => 'active'] : []),
            ]);
        }

        return back()->with('success', 'Je afwezigheidsmelding is ingetrokken.');
    }

    public function storePartner(Request $request): RedirectResponse
    {
        abort_unless($this->canManagePartners($request->user()), 403);
        $data = $this->validatePartner($request);
        $data['slug'] = Str::slug($data['name']);
        if (Schema::hasColumn('partners', 'is_featured')) {
            $data['is_featured'] = $request->boolean('is_featured');
        }
        if ($request->hasFile('logo')) {
            $data['logo'] = $request->file('logo')->store('partners', 'public');
        }

        Partner::create($this->filterPartnerData($data));

        return back()->with('success', 'Project toegevoegd aan de ranglijst.');
    }

    public function updatePartner(Request $request, Partner $partner): RedirectResponse
    {
        abort_unless($this->canManagePartners($request->user()), 403);
        $data = $this->validatePartner($request, $partner);
        $data['slug'] = Str::slug($data['name']);
        if (Schema::hasColumn('partners', 'is_featured')) {
            $data['is_featured'] = $request->boolean('is_featured');
        }
        if ($request->hasFile('logo')) {
            if ($partner->logo && !str_starts_with($partner->logo, 'http')) {
                Storage::disk('public')->delete($partner->logo);
            }
            $data['logo'] = $request->file('logo')->store('partners', 'public');
        }
        $partner->update($this->filterPartnerData($data));

        return back()->with('success', 'Project bijgewerkt.');
    }

    public function destroyPartner(Request $request, Partner $partner): RedirectResponse
    {
        abort_unless($this->canManagePartners($request->user()), 403);
        if ($partner->logo && !str_starts_with($partner->logo, 'http')) {
            Storage::disk('public')->delete($partner->logo);
        }
        $partner->delete();

        return back()->with('success', 'Project verwijderd uit de ranglijst.');
    }

    public function upgradePartnerRankings(Request $request): RedirectResponse
    {
        abort_unless($this->canManagePartners($request->user()), 403);

        $missingColumns = collect(['description', 'category', 'score', 'position', 'is_featured'])
            ->reject(fn (string $column) => Schema::hasColumn('partners', $column));

        if ($missingColumns->isNotEmpty()) {
            Schema::table('partners', function (Blueprint $table) use ($missingColumns) {
                if ($missingColumns->contains('description')) {
                    $table->text('description')->nullable();
                }
                if ($missingColumns->contains('category')) {
                    $table->string('category')->default('server');
                }
                if ($missingColumns->contains('score')) {
                    $table->unsignedSmallInteger('score')->default(0);
                }
                if ($missingColumns->contains('position')) {
                    $table->unsignedSmallInteger('position')->default(100)->index();
                }
                if ($missingColumns->contains('is_featured')) {
                    $table->boolean('is_featured')->default(true);
                }
            });
        }

        $projects = [
            ['Stumpertjes', 'Creatieve Discord-community met actieve leden.', 'https://discord.gg/dG7HRqVa9J', 94, 1],
            ['Game On', 'Gamingproject met focus op events en gezelligheid.', 'https://discord.gg/dG7HRqVa9J', 91, 2],
            ['NightMC', 'Minecraft-server met een herkenbare community.', 'https://discord.gg/dG7HRqVa9J', 89, 3],
            ['ValoraMC', 'Serverproject met groeiende spelersgroep.', 'https://discord.gg/dG7HRqVa9J', 87, 4],
            ['GamingTubex', 'Communityproject rond content en gaming.', 'https://discord.gg/dG7HRqVa9J', 85, 5],
            ['NL & BE Roleplay', 'Roleplay-community voor Nederlandse en Belgische spelers.', 'https://discord.gg/dG7HRqVa9J', 83, 6],
            ['PixelForge', 'Creatieve server voor makers en developers.', 'https://discord.gg/dG7HRqVa9J', 81, 7],
            ['Creative Hub', 'Partnerproject voor design, bouw en content.', 'https://discord.gg/dG7HRqVa9J', 79, 8],
            ['Nexus', 'Communityserver met focus op samenwerking.', 'https://discord.gg/dG7HRqVa9J', 77, 9],
            ['Studio Nova', 'Creatieve partner voor community-identiteit.', 'https://discord.gg/dG7HRqVa9J', 75, 10],
        ];

        foreach ($projects as [$name, $description, $website, $score, $position]) {
            Partner::updateOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'description' => $description,
                    'website' => $website,
                    'status' => 'active',
                    'tier' => 'community',
                    'category' => 'server',
                    'score' => $score,
                    'position' => $position,
                    'is_featured' => true,
                ]
            );
        }

        return back()->with('success', 'Partner-ranglijst is bijgewerkt. Je kunt nu score, positie, categorie en homepage-weergave beheren.');
    }

    public function upgradeDiscordIntegration(Request $request): RedirectResponse
    {
        abort_unless($this->canManageDiscord($request->user()), 403);

        if (!Schema::hasTable('discord_channels')) {
            Schema::create('discord_channels', function (Blueprint $table) {
                $table->id();
                $table->string('discord_channel_id')->unique();
                $table->string('name');
                $table->string('purpose');
                $table->string('webhook_url')->nullable();
                $table->string('static_message_id')->nullable();
                $table->timestamp('static_message_updated_at')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        $missingChannelColumns = collect(['static_message_id', 'static_message_updated_at'])
            ->reject(fn (string $column) => Schema::hasColumn('discord_channels', $column));

        if ($missingChannelColumns->isNotEmpty()) {
            Schema::table('discord_channels', function (Blueprint $table) use ($missingChannelColumns) {
                if ($missingChannelColumns->contains('static_message_id')) {
                    $table->string('static_message_id')->nullable();
                }
                if ($missingChannelColumns->contains('static_message_updated_at')) {
                    $table->timestamp('static_message_updated_at')->nullable();
                }
            });
        }

        if (!Schema::hasTable('discord_deliveries')) {
            Schema::create('discord_deliveries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('discord_channel_id')->nullable()->constrained()->nullOnDelete();
                $table->string('event');
                $table->json('payload');
                $table->enum('status', ['pending', 'sent', 'failed'])->default('pending');
                $table->text('response')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamps();
            });
        }

        foreach ($this->discordPurposes() as $purpose => $config) {
            DiscordChannel::firstOrCreate(
                ['purpose' => $purpose],
                [
                    'discord_channel_id' => $purpose,
                    'name' => $config['name'],
                    'webhook_url' => null,
                    'is_active' => true,
                ]
            );
        }
        DiscordChannel::firstOrCreate(
            ['purpose' => 'leaderboard'],
            [
                'discord_channel_id' => 'leaderboard',
                'name' => 'leaderboard',
                'webhook_url' => null,
                'is_active' => true,
            ]
        );

        return back()->with('success', 'CN Pulse & Discord-kanalen zijn klaargezet. Vul per kanaal het Discord kanaal-ID in en stuur een testbericht via de bot.');
    }

    public function saveDiscordChannel(Request $request): RedirectResponse
    {
        abort_unless($this->canManageDiscord($request->user()), 403);
        abort_unless(Schema::hasTable('discord_channels'), 404);

        $data = $request->validate([
            'purpose' => ['required', 'string', 'max:80'],
            'name' => ['required', 'string', 'max:120'],
            'discord_channel_id' => ['nullable', 'string', 'max:120'],
            'webhook_url' => ['nullable', 'url', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        DiscordChannel::updateOrCreate(
            ['purpose' => $data['purpose']],
            [
                'name' => $data['name'],
                'discord_channel_id' => $data['discord_channel_id'] ?: $data['purpose'],
                'webhook_url' => $data['webhook_url'] ?? null,
                'is_active' => $request->boolean('is_active'),
            ]
        );

        return back()->with('success', 'Discord-kanaal opgeslagen.');
    }

    public function testDiscordChannel(Request $request, DiscordChannel $channel, DiscordService $discord): RedirectResponse
    {
        abort_unless($this->canManageDiscord($request->user()), 403);

        $payload = [
            'content' => null,
            'embeds' => [[
                'title' => 'CN Pulse testbericht',
                'description' => 'Dit kanaal is gekoppeld aan **'.$channel->name.'**. Bot-pushes voor `'.$channel->purpose.'` komen hier terecht.',
                'color' => 14883619,
                'fields' => [[
                    'name' => 'MijnCN',
                    'value' => route('mijncn.module', 'discord'),
                    'inline' => false,
                ]],
            ]],
        ];

        $delivery = DiscordDelivery::create([
            'discord_channel_id' => $channel->id,
            'event' => 'test',
            'payload' => $payload,
            'status' => 'pending',
        ]);

        try {
            $discord->sendChannelMessage($channel->discord_channel_id, $payload);
            $delivery->update(['status' => 'sent', 'sent_at' => now()]);
        } catch (\Throwable $exception) {
            $delivery->update(['status' => 'failed', 'response' => Str::limit($exception->getMessage(), 1000)]);

            return back()->withErrors(['discord' => 'Testbericht mislukt: '.$exception->getMessage()]);
        }

        return back()->with('success', 'Testbericht verstuurd naar '.$channel->name.'.');
    }

    public function publishDiscordPanel(Request $request, DiscordChannel $channel, DiscordService $discord, CnPulseService $pulse): RedirectResponse
    {
        abort_unless($this->canManageDiscord($request->user()), 403);
        abort_unless($this->isStaticDiscordPurpose($channel->purpose), 422);

        $payload = $this->discordStaticPanelPayload($channel->purpose, $pulse);
        $delivery = DiscordDelivery::create([
            'discord_channel_id' => $channel->id,
            'event' => 'static_panel:'.$channel->purpose,
            'payload' => $payload,
            'status' => 'pending',
        ]);

        try {
            $response = $channel->static_message_id
                ? $discord->editChannelMessage($channel->discord_channel_id, $channel->static_message_id, $payload)
                : $discord->sendChannelMessage($channel->discord_channel_id, $payload);

            $channel->update([
                'static_message_id' => (string) ($response['id'] ?? $channel->static_message_id),
                'static_message_updated_at' => now(),
            ]);
            $delivery->update(['status' => 'sent', 'sent_at' => now()]);
        } catch (\Throwable $exception) {
            $delivery->update(['status' => 'failed', 'response' => Str::limit($exception->getMessage(), 1000)]);

            return back()->withErrors(['discord' => 'Vast bericht bijwerken mislukt: '.$exception->getMessage()]);
        }

        return back()->with('success', 'Vast kanaalbericht bijgewerkt voor '.$channel->name.'.');
    }

    public function runDiscordAutomation(Request $request, CommunityAutomationService $automation): RedirectResponse
    {
        abort_unless($this->canManageDiscord($request->user()), 403);

        $result = $automation->run();

        return back()->with('success', 'Automatisering uitgevoerd: awards '.$result['award_phases'].', verjaardagen '.$result['birthdays'].'.');
    }

    private function modules(): array
    {
        return ['profile', 'notifications', 'inbox', 'nominations', 'votes', 'results', 'lessons', 'exams', 'certificates', 'badges', 'tasks', 'nomi', 'settings', 'absences', 'birthdays', 'community', 'pulse', 'discord', 'partners'];
    }

    private function canManagePartners(User $user): bool
    {
        return in_array($user->role->value, ['owner', 'management', 'partner_manager'], true)
            || $user->hasPermission('partners.manage');
    }

    private function canManageDiscord(User $user): bool
    {
        return in_array($user->role->value, ['owner', 'management', 'admin'], true)
            || $user->hasPermission('content.manage');
    }

    private function discordPurposes(): array
    {
        return [
            'cn-pulse' => ['name' => '📡┃cn-pulse', 'description' => 'Algemene live feed uit MijnCN met nominaties, partners, Academy en community-updates.'],
            'nieuws' => ['name' => '📰┃nieuws', 'description' => 'CN nieuws, NU.nl/NOS feed en handmatige nieuwsberichten.'],
            'verjaardagen' => ['name' => '🎂┃verjaardagen', 'description' => 'Automatische verjaardagsmeldingen.'],
            'staff-status' => ['name' => '👥┃staff-status', 'description' => 'Afwezigheid, beschikbaarheid en teamrooster-updates.'],
            'dagelijkse-statistieken' => ['name' => '📊┃dagelijkse-statistieken', 'description' => 'Dagelijkse of wekelijkse statistieken over leden, stemmen en activiteit.'],
            'awards-info' => ['name' => '🏆┃awards-info', 'description' => 'Awards-uitleg, fases en nominatie-aankondigingen.'],
            'stem-nu' => ['name' => '🗳️┃stem-nu', 'description' => 'Actieve stemrondes met directe link naar stemmen.'],
            'trending' => ['name' => '🔥┃trending', 'description' => 'Trending nominaties, categorieën en populaire finalistprofielen.'],
            'award-logs' => ['name' => '📥┃award-logs', 'description' => 'Interne awardlogs: goedgekeurd, afgewezen, samengevoegd en juryupdates.'],
        ];
    }

    private function isStaticDiscordPurpose(string $purpose): bool
    {
        return in_array($purpose, ['cn-pulse', 'staff-status', 'awards-info', 'stem-nu', 'trending', 'leaderboard', 'award-logs'], true);
    }

    private function discordStaticPanelPayload(string $purpose, CnPulseService $pulse): array
    {
        return match ($purpose) {
            'cn-pulse' => $this->discordPanelPayload(
                'CN Pulse',
                'Live overzicht van wat er speelt binnen CN Community.',
                collect($pulse->statusCards())->map(fn ($card) => [
                    'name' => $card['label'],
                    'value' => $card['value'].' - '.$card['hint'],
                    'inline' => true,
                ])->all(),
                route('mijncn.module', 'pulse')
            ),
            'staff-status' => $this->discordPanelPayload(
                'Staff status',
                'Bekijk wie beschikbaar, druk of afwezig is.',
                [[
                    'name' => 'Rooster',
                    'value' => 'Staff kan afwezigheid beheren via MijnCN. Dit paneel wordt bijgewerkt vanuit de website.',
                    'inline' => false,
                ]],
                route('mijncn.module', 'absences')
            ),
            'awards-info' => $this->discordPanelPayload(
                'CN Awards 2026',
                'Nomineren, stemmen, jury en finale draaien via het CN Community Platform.',
                $this->awardPanelFields(),
                route('awards')
            ),
            'stem-nu' => $this->discordPanelPayload(
                'Stemronde',
                'Wanneer stemmen open is, gebruik je deze knop om direct te stemmen.',
                $this->awardPanelFields('public_vote'),
                route('awards')
            ),
            'trending', 'leaderboard' => $this->discordPanelPayload(
                $purpose === 'trending' ? 'Trending nominaties' : 'Awards leaderboard',
                'Actuele top op basis van stemmen en nominatie-activiteit.',
                $this->topNominationFields(),
                route('awards')
            ),
            'award-logs' => $this->discordPanelPayload(
                'Award logboek',
                'Interne awardstatus: reviews, merges, jury en publicaties.',
                [[
                    'name' => 'Status',
                    'value' => 'Logs worden los gepusht. Dit vaste paneel markeert het kanaal als actief.',
                    'inline' => false,
                ]],
                route('staff.awards')
            ),
        };
    }

    private function discordPanelPayload(string $title, string $description, array $fields, string $url): array
    {
        return [
            'content' => null,
            'embeds' => [[
                'title' => $title,
                'description' => $description,
                'url' => $url,
                'color' => 14883619,
                'fields' => $fields,
                'footer' => ['text' => 'CN Community Platform 2026 - automatisch bijgewerkt'],
                'timestamp' => now()->toIso8601String(),
            ]],
            'components' => [[
                'type' => 1,
                'components' => [[
                    'type' => 2,
                    'style' => 5,
                    'label' => 'Open MijnCN',
                    'url' => route('dashboard'),
                ], [
                    'type' => 2,
                    'style' => 5,
                    'label' => 'Bekijk pagina',
                    'url' => $url,
                ]],
            ]],
        ];
    }

    private function awardPanelFields(?string $roundType = null): array
    {
        $edition = AwardEdition::with('rounds')
            ->where('type', 'cn_awards')
            ->latest('year')
            ->first();

        if (!$edition) {
            return [['name' => 'Status', 'value' => 'Er is nog geen actieve Awards-editie.', 'inline' => false]];
        }

        $round = $roundType
            ? $edition->rounds->firstWhere('type', $roundType)
            : $edition->rounds->sortBy('starts_at')->first();

        return [[
            'name' => 'Editie',
            'value' => $edition->name.' - '.$edition->status,
            'inline' => true,
        ], [
            'name' => 'Nominaties',
            'value' => (string) Nomination::whereHas('category', fn ($query) => $query->where('award_edition_id', $edition->id))->count(),
            'inline' => true,
        ], [
            'name' => 'Planning',
            'value' => $round ? $round->starts_at->format('d-m H:i').' tot '.$round->ends_at->format('d-m H:i') : 'Nog niet ingesteld',
            'inline' => false,
        ]];
    }

    private function topNominationFields(): array
    {
        if (!Schema::hasTable('nominations')) {
            return [['name' => 'Leaderboard', 'value' => 'Nog geen nominaties beschikbaar.', 'inline' => false]];
        }

        $items = Nomination::withCount('votes')
            ->whereIn('status', ['approved', 'finalist', 'winner'])
            ->orderByDesc('votes_count')
            ->orderByDesc('reputation_score')
            ->limit(5)
            ->get();

        if ($items->isEmpty()) {
            return [['name' => 'Leaderboard', 'value' => 'Nog geen goedgekeurde nominaties.', 'inline' => false]];
        }

        return $items->values()->map(fn (Nomination $nomination, int $index) => [
            'name' => '#'.($index + 1).' '.$nomination->nominee_name,
            'value' => $nomination->votes_count.' stemmen - reputatie '.number_format((float) $nomination->reputation_score, 1, ',', '.'),
            'inline' => false,
        ])->all();
    }

    private function validatePartner(Request $request, ?Partner $partner = null): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:80'],
            'website' => ['nullable', 'url', 'max:255'],
            'discord_id' => ['nullable', 'string', 'max:120'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'status' => ['required', 'in:lead,pending,active,warning,ended'],
            'tier' => ['required', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];

        foreach ([
            'description' => ['nullable', 'string', 'max:240'],
            'category' => ['required', 'string', 'max:40'],
            'score' => ['required', 'integer', 'min:0', 'max:100'],
            'position' => ['required', 'integer', 'min:1', 'max:999'],
            'is_featured' => ['nullable', 'boolean'],
        ] as $column => $rule) {
            if (Schema::hasColumn('partners', $column)) {
                $rules[$column] = $rule;
            }
        }

        return $request->validate($rules);
    }

    private function filterPartnerData(array $data): array
    {
        $columns = array_flip(Schema::getColumnListing('partners'));

        return array_intersect_key($data, $columns);
    }
}
