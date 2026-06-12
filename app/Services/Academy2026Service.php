<?php

namespace App\Services;

use App\Models\Badge;
use App\Models\LearningPath;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class Academy2026Service
{
    private const PATHS = [
        'helper' => ['name' => 'Helper Opleiding', 'icon' => 'shield', 'color' => '#24935f'],
        'moderator' => ['name' => 'Moderator Opleiding', 'icon' => 'hammer', 'color' => '#df1822'],
        'admin' => ['name' => 'Admin Opleiding', 'icon' => 'settings', 'color' => '#7257d8'],
        'management' => ['name' => 'Management Opleiding', 'icon' => 'award', 'color' => '#c28a16'],
    ];

    public function sync(): array
    {
        $counts = ['paths' => 0, 'lessons' => 0, 'questions' => 0, 'badges' => 0];
        $this->archiveLidAcademy();

        foreach (self::PATHS as $role => $meta) {
            $path = LearningPath::where('slug', $role.'-opleiding-2026')->first()
                ?? LearningPath::where('target_role', $role)->oldest('id')->first()
                ?? new LearningPath();
            $path->fill([
                'slug' => $role.'-opleiding-2026',
                'name' => $meta['name'],
                'description' => $this->description($role),
                'target_role' => $role,
                'is_published' => true,
            ])->save();
            LearningPath::where('target_role', $role)->whereKeyNot($path->id)->update(['is_published' => false]);
            $this->removeUnusedDemoLessons($path);
            $counts['paths']++;

            $position = 1;
            foreach ($this->modules($role) as $moduleIndex => $module) {
                foreach ($module['lessons'] as $title) {
                    $lesson = $this->upsertLesson($path, $title, 'lesson', $position++, 75, [
                        'academy_version' => 2026,
                        'module' => $module['name'],
                        'module_number' => $moduleIndex + 1,
                        'estimated_minutes' => 8,
                        'min_read_seconds' => app()->environment('testing') ? 0 : 180,
                        'media_slots' => ['image', 'video', 'discord_screenshot'],
                        'knowledge_check' => ['questions' => 5, 'pass_score' => 80],
                    ]);
                    $counts['lessons'] += $lesson->wasRecentlyCreated ? 1 : 0;
                    $counts['questions'] += $this->syncQuestions($lesson, [$title], 5);
                }

                $quiz = $this->upsertLesson($path, 'Tussentoets '.($moduleIndex + 1).' - '.$module['name'], 'quiz', $position++, 175, [
                    'academy_version' => 2026,
                    'module' => $module['name'],
                    'module_number' => $moduleIndex + 1,
                    'timer_minutes' => 15,
                    'pass_score' => 80,
                    'question_count' => 20,
                    'unlocks_next_module' => true,
                ]);
                $counts['lessons'] += $quiz->wasRecentlyCreated ? 1 : 0;
                $counts['questions'] += $this->syncQuestions($quiz, $module['lessons'], 20);

                $assignmentTitle = $this->assignment($role, $moduleIndex + 1);
                $assignment = $this->upsertLesson($path, 'Praktijkopdracht '.($moduleIndex + 1).' - '.$assignmentTitle, 'assignment', $position++, 250, [
                    'academy_version' => 2026,
                    'module' => $module['name'],
                    'module_number' => $moduleIndex + 1,
                    'mentor_review' => true,
                    'mentor_names' => ['Jesse', 'Melvin', 'Stan'],
                ]);
                $counts['lessons'] += $assignment->wasRecentlyCreated ? 1 : 0;
            }

            $exam = $this->upsertLesson($path, $meta['name'].' Eindexamen', 'exam', $position, 1000, [
                'academy_version' => 2026,
                'module' => 'Eindexamen',
                'timer_minutes' => 45,
                'pass_score' => 80,
                'question_count' => 50,
                'retry_days' => 7,
                'requires_all_previous' => true,
            ]);
            $counts['lessons'] += $exam->wasRecentlyCreated ? 1 : 0;
            $counts['questions'] += $this->syncQuestions($exam, collect($this->modules($role))->flatMap(fn ($module) => $module['lessons'])->all(), 100);

            Badge::updateOrCreate(
                ['slug' => $role.'-academy-2026'],
                [
                    'name' => $meta['name'].' Badge',
                    'description' => 'Behaald na succesvolle afronding van de volledige '.$meta['name'].'.',
                    'icon' => $meta['icon'],
                    'color' => $meta['color'],
                    'xp_required' => 0,
                ]
            );
            $counts['badges']++;
        }

        return $counts;
    }

    public function isPathUnlocked(User $user, LearningPath $path): bool
    {
        $roleAccess = match ($path->target_role) {
            'helper' => ['helper'],
            'moderator' => ['moderator'],
            'admin' => ['admin'],
            'management' => ['management', 'owner'],
            default => [],
        };
        if (in_array($user->role->value, $roleAccess, true)) {
            return true;
        }

        return Schema::hasTable('academy_enrollments') && DB::table('academy_enrollments')
            ->where('learning_path_id', $path->id)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->exists();
    }

    public function isLessonUnlocked(User $user, Lesson $lesson): bool
    {
        if (!$this->isPathUnlocked($user, $lesson->path)) {
            return false;
        }

        return !DB::table('lessons')
            ->where('learning_path_id', $lesson->learning_path_id)
            ->where('position', '<', $lesson->position)
            ->whereNotExists(function ($query) use ($user) {
                $query->selectRaw('1')
                    ->from('lesson_progress')
                    ->whereColumn('lesson_progress.lesson_id', 'lessons.id')
                    ->where('lesson_progress.user_id', $user->id)
                    ->where('lesson_progress.status', 'passed');
            })
            ->exists();
    }

    public function completeAssessment(User $user, Lesson $lesson, float $score, array $answers, int $timeSpentSeconds, int $tabSwitches): void
    {
        DB::transaction(function () use ($user, $lesson, $score, $answers, $timeSpentSeconds, $tabSwitches) {
            $passed = $score >= $this->passScore($lesson);
            $existing = DB::table('lesson_progress')
                ->where('lesson_id', $lesson->id)
                ->where('user_id', $user->id)
                ->first();

            DB::table('lesson_progress')->updateOrInsert(
                ['lesson_id' => $lesson->id, 'user_id' => $user->id],
                [
                    'status' => $passed ? 'passed' : 'failed',
                    'score' => $score,
                    'completed_at' => $passed ? now() : null,
                    'created_at' => $existing?->created_at ?? now(),
                    'updated_at' => now(),
                ]
            );

            if (Schema::hasTable('academy_attempts')) {
                DB::table('academy_attempts')->insert([
                    'lesson_id' => $lesson->id,
                    'user_id' => $user->id,
                    'type' => $lesson->type,
                    'score' => $score,
                    'passed' => $passed,
                    'answers' => json_encode($answers),
                    'time_spent_seconds' => $timeSpentSeconds,
                    'tab_switches' => $tabSwitches,
                    'started_at' => now()->subSeconds($timeSpentSeconds),
                    'submitted_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if ($passed && (!$existing || $existing->status !== 'passed')) {
                $user->increment('xp', $lesson->xp_reward);
            }
            if ($passed && $lesson->type === 'exam') {
                $this->certify($user, $lesson->path);
            }
        });
    }

    public function submitAssignment(User $user, Lesson $lesson, string $submission): void
    {
        $existing = DB::table('lesson_progress')
            ->where('lesson_id', $lesson->id)
            ->where('user_id', $user->id)
            ->first();
        DB::table('lesson_progress')->updateOrInsert(
            ['lesson_id' => $lesson->id, 'user_id' => $user->id],
            [
                'status' => 'submitted',
                'score' => null,
                'submission' => $submission,
                'completed_at' => null,
                'created_at' => $existing?->created_at ?? now(),
                'updated_at' => now(),
            ]
        );
    }

    public function reviewAssignment(User $mentor, Lesson $lesson, User $student, bool $passed, ?string $feedback): void
    {
        $progress = DB::table('lesson_progress')
            ->where('lesson_id', $lesson->id)
            ->where('user_id', $student->id)
            ->first();
        abort_unless($progress && $lesson->type === 'assignment', 404);

        DB::table('lesson_progress')->where('id', $progress->id)->update([
            'status' => $passed ? 'passed' : 'failed',
            'score' => $passed ? 100 : 0,
            'feedback' => $feedback,
            'mentor_id' => $mentor->id,
            'completed_at' => $passed ? now() : null,
            'updated_at' => now(),
        ]);
        if ($passed && $progress->status !== 'passed') {
            $student->increment('xp', $lesson->xp_reward);
        }
    }

    public function passScore(Lesson $lesson): int
    {
        return (int) ($lesson->settings['knowledge_check']['pass_score'] ?? $lesson->settings['pass_score'] ?? 80);
    }

    public function requiredReadSeconds(Lesson $lesson): int
    {
        return (int) ($lesson->settings['min_read_seconds'] ?? 0);
    }

    private function certify(User $user, LearningPath $path): void
    {
        DB::table('certificates')->updateOrInsert(
            ['user_id' => $user->id, 'learning_path_id' => $path->id],
            [
                'uuid' => (string) Str::uuid(),
                'title' => 'Certificaat '.$path->name,
                'issued_at' => now(),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
        $badgeId = Badge::where('slug', $path->target_role.'-academy-2026')->value('id');
        if ($badgeId) {
            DB::table('badge_user')->updateOrInsert(
                ['badge_id' => $badgeId, 'user_id' => $user->id],
                ['awarded_at' => now(), 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }

    private function upsertLesson(LearningPath $path, string $title, string $type, int $position, int $xp, array $settings): Lesson
    {
        return Lesson::updateOrCreate(
            ['learning_path_id' => $path->id, 'slug' => Str::slug($title)],
            [
                'title' => $title,
                'content' => $this->content($title, $settings['module'], $type),
                'type' => $type,
                'xp_reward' => $xp,
                'position' => $position,
                'settings' => $settings,
            ]
        );
    }

    private function syncQuestions(Lesson $lesson, array $topics, int $count): int
    {
        if (Schema::hasTable('question_bank')) {
            return $this->syncQuestionBank($lesson, $topics, $count);
        }

        $created = 0;
        $topics = collect($topics)->values();
        $types = ['multiple_choice', 'true_false', 'scenario', 'multiple_choice', 'scenario'];

        for ($index = 0; $index < $count; $index++) {
            $topic = $topics[$index % max(1, $topics->count())] ?? $lesson->title;
            $type = $types[$index % count($types)];
            $question = $this->questionText($type, $topic, $index + 1);
            $exists = DB::table('questions')->where('lesson_id', $lesson->id)->where('question', $question)->exists();
            $payload = [
                'options' => json_encode($this->questionOptions($type)),
                'correct_answer' => 'A',
                'points' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            if (Schema::hasColumn('questions', 'type')) {
                $payload['type'] = $type;
            }
            if (Schema::hasColumn('questions', 'explanation')) {
                $payload['explanation'] = 'CN verwacht rustig, respectvol en procesmatig handelen met oog voor veiligheid en context.';
            }
            DB::table('questions')->updateOrInsert(
                ['lesson_id' => $lesson->id, 'question' => $question],
                $payload
            );
            $created += $exists ? 0 : 1;
        }

        return $created;
    }

    private function syncQuestionBank(Lesson $lesson, array $topics, int $count): int
    {
        $created = 0;
        $topics = collect($topics)->filter()->values();
        $role = $lesson->path->target_role;
        $module = (int) ($lesson->settings['module_number'] ?? 0) ?: null;
        $patterns = [
            'Wat is binnen de '.$this->roleLabel($role).' opleiding de beste eerste stap bij %s?',
            'Welke aanpak voorkomt bij %s het meeste risico voor de community?',
            'Een lid vraagt hulp over %s. Welke reactie past bij jouw verantwoordelijkheid?',
            'Welke informatie controleer je als eerste voordat je handelt rond %s?',
            'Wat moet je bij %s altijd vastleggen of terugkoppelen?',
            'Een situatie rond %s escaleert. Wat doe je voordat je een zwaardere stap neemt?',
            'Welke keuze toont professioneel eigenaarschap bij %s?',
            'Waarom is zorgvuldig handelen belangrijk wanneer %s speelt?',
            'Je collega twijfelt over %s. Welke samenwerking is het meest passend?',
            'Welke vervolgstap hoort bij een correct afgehandelde situatie rond %s?',
        ];

        for ($index = 0; $index < $count; $index++) {
            $topic = $topics[$index % max(1, $topics->count())] ?? $lesson->title;
            $type = ['multiple_choice', 'scenario', 'true_false'][$index % 3];
            $question = $type === 'true_false'
                ? $this->trueFalseQuestion($role, $topic, $index)
                : sprintf($patterns[$index % count($patterns)], $topic);
            $question .= ' ('.$lesson->title.', vraag '.($index + 1).')';
            $options = $this->roleOptions($role, $topic, $type, $index);
            $correct = array_key_first($options);
            $rotation = $index % count($options);
            $options = collect($options)->slice($rotation)->merge(collect($options)->take($rotation))->all();
            $hash = hash('sha256', mb_strtolower($question));
            $exists = DB::table('question_bank')->where('course_id', $lesson->learning_path_id)->where('question_hash', $hash)->exists();

            DB::table('question_bank')->updateOrInsert(
                ['course_id' => $lesson->learning_path_id, 'question_hash' => $hash],
                [
                    'module_id' => $module,
                    'lesson_id' => $lesson->id,
                    'type' => $type,
                    'question' => $question,
                    'options' => json_encode($options),
                    'correct_answer' => $correct,
                    'explanation' => $this->explanation($role, $topic),
                    'difficulty' => $lesson->type === 'exam' ? 'hard' : ($lesson->type === 'quiz' ? 'medium' : 'easy'),
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
            $created += $exists ? 0 : 1;
        }

        return $created;
    }

    private function roleOptions(string $role, string $topic, string $type, int $index): array
    {
        if ($type === 'true_false') {
            $true = $index % 2 === 0;
            return $true
                ? ['true' => 'Waar', 'false' => 'Niet waar']
                : ['false' => 'Niet waar', 'true' => 'Waar'];
        }

        $correct = match ($role) {
            'helper' => 'Luister, controleer de vraag over '.$topic.', help binnen je bevoegdheid en verwijs door als dat nodig is.',
            'moderator' => 'Controleer context en bewijs rond '.$topic.', leg je afweging vast en pas de afgesproken moderatiestap toe.',
            'admin' => 'Bepaal impact en eigenaar van '.$topic.', maak een plan, communiceer verantwoordelijkheden en bewaak de uitvoering.',
            'management' => 'Analyseer belangen en risico’s rond '.$topic.', raadpleeg betrokkenen en neem een uitlegbaar besluit volgens beleid.',
            default => 'Controleer de context, volg de CN-afspraken en leg de gekozen vervolgstap duidelijk uit.',
        };

        return [
            'correct' => $correct,
            'ignore' => 'Negeer de situatie totdat iemand anders verantwoordelijkheid neemt.',
            'impulsive' => 'Neem direct een zware maatregel zonder context, overleg of verslag.',
            'public' => 'Deel interne informatie openbaar om sneller een reactie af te dwingen.',
        ];
    }

    private function trueFalseQuestion(string $role, string $topic, int $index): string
    {
        if ($index % 2 === 0) {
            return 'Waar of niet waar: bij '.$topic.' controleert een '.$this->roleLabel($role).' eerst de context en de geldende CN-afspraken.';
        }

        return 'Waar of niet waar: bij '.$topic.' is direct handelen zonder controle beter dan overleg en verslaglegging.';
    }

    private function explanation(string $role, string $topic): string
    {
        return 'Bij '.$topic.' verwacht CN van een '.$this->roleLabel($role).' dat context, bevoegdheid, communicatie en verslaglegging samen worden afgewogen. De juiste stap is daardoor controleerbaar en veilig.';
    }

    private function roleLabel(string $role): string
    {
        return match ($role) {
            'helper' => 'Helper',
            'moderator' => 'Moderator',
            'admin' => 'Admin',
            'management' => 'Managementlid',
            default => 'stafflid',
        };
    }

    private function removeUnusedDemoLessons(LearningPath $path): void
    {
        Lesson::where('learning_path_id', $path->id)
            ->whereIn('slug', ['welkom-en-verwachtingen', 'communicatie-in-de-praktijk', 'praktijkscenario', 'eindexamen'])
            ->whereDoesntHave('progress')
            ->delete();
    }

    private function content(string $title, string $module, string $type): string
    {
        if ($type === 'assignment') {
            return '<section><h2>Introductie</h2><p>Deze praktijkopdracht laat zien dat je de kennis uit module '.$module.' kunt toepassen in echte CN-situaties.</p></section><section><h2>Leerdoelen</h2><ul><li>Je toont zichtbaar professioneel gedrag.</li><li>Je legt je keuzes helder uit.</li><li>Je vraagt feedback wanneer dat nodig is.</li></ul></section><section><h2>Opdracht</h2><p>Voer de opdracht uit binnen CN Community en beschrijf concreet wat je hebt gedaan, wie erbij betrokken was, welke afwegingen je maakte en wat het resultaat was.</p></section><section><h2>Beoordeling</h2><p>Een mentor beoordeelt je inzending op volledigheid, houding, samenwerking en aansluiting op de CN-afspraken.</p></section>';
        }
        if (in_array($type, ['quiz', 'exam'], true)) {
            return '<section><h2>Introductie</h2><p>Deze toets controleert of je de modulekennis zelfstandig kunt toepassen. Je krijgt willekeurige vragen en moet minimaal 80% halen.</p></section><section><h2>Voorbereiding</h2><p>Bekijk je eerdere lessen, let op scenario’s, afspraken en processtappen. Tijdens de toets telt nauwkeurig lezen zwaarder dan snelheid.</p></section><section><h2>Regels</h2><ul><li>De timer is verplicht.</li><li>Tabwissels worden geregistreerd.</li><li>Bij onvoldoende score bekijk je de lesstof opnieuw.</li></ul></section>';
        }

        return '<section><h2>Introductie</h2><p>'.$title.' is een belangrijk onderdeel van '.$module.' binnen CN Community. In deze les leer je niet alleen wat het betekent, maar vooral hoe je het in Discord, MijnCN en communitysituaties gebruikt.</p></section>'
            .'<section><h2>Leerdoelen</h2><ul><li>Je kunt uitleggen wat '.$title.' betekent binnen CN.</li><li>Je herkent situaties waarin deze kennis nodig is.</li><li>Je kunt de juiste CN-stap kiezen zonder te gokken.</li><li>Je weet wanneer je hulp of escalatie moet vragen.</li></ul></section>'
            .'<section><h2>Uitleg</h2><p>CN Community werkt vanuit duidelijkheid, respect en verantwoordelijkheid. '.$title.' raakt aan hoe leden, staff en partners elkaar vertrouwen. Een goede aanpak begint met rustig blijven, de context controleren en de afgesproken route volgen. Dat voorkomt misverstanden en zorgt dat iedereen dezelfde standaard ervaart.</p><p>Bij twijfel kies je nooit voor impulsief handelen. Je verzamelt informatie, kijkt welke regel of afspraak van toepassing is en communiceert helder. Binnen CN is professioneel gedrag geen formeel toneelstuk, maar voorspelbaar en betrouwbaar handelen.</p></section>'
            .'<section><h2>Voorbeelden</h2><p>Een lid stelt een vraag die al vaker is beantwoord. Een slechte reactie is kortaf verwijzen naar “zoek maar terug”. Een goede reactie is vriendelijk samenvatten, naar de juiste plek verwijzen en aangeven waar iemand de informatie later terugvindt.</p><p>Een tweede voorbeeld: iemand begrijpt een melding verkeerd. Je corrigeert niet publiekelijk met harde toon, maar legt rustig uit wat de melding betekent en waar verdere hulp beschikbaar is.</p></section>'
            .'<section><h2>Praktijkvoorbeelden</h2><p>In Discord kan '.$title.' zichtbaar worden in chatgesprekken, tickets, events en staffoverleg. In MijnCN zie je hetzelfde terug in meldingen, Academy-voortgang, Awards en taken. De kern is steeds hetzelfde: handel zorgvuldig, leg keuzes uit en voorkom dat kleine verwarring groter wordt.</p><figure class="academy-media-placeholder"><strong>Afbeelding / Discord screenshot</strong><span>Hier kan later een screenshot of visuele uitleg worden geplaatst.</span></figure><figure class="academy-media-placeholder"><strong>Video</strong><span>Ondersteuning voor uitlegvideo of demonstratie.</span></figure></section>'
            .'<section><h2>Scenario’s</h2><p>Scenario 1: een nieuw lid vraagt waar de Awards voor zijn. Je legt kort uit wat nomineren, stemmen en winnaars betekenen en verwijst naar de Awards-pagina. Scenario 2: een discussie wordt fel. Je verlaagt de spanning, vraagt om respectvolle toon en schakelt staff in als dat nodig is.</p></section>'
            .'<section><h2>Belangrijk om te onthouden</h2><ul><li>Controleer altijd de context.</li><li>Communiceer kort, duidelijk en respectvol.</li><li>Gebruik CN-systemen zoals bedoeld.</li><li>Escaleren is volwassen handelen wanneer je grenzen bereikt.</li></ul></section>'
            .'<section><h2>Samenvatting</h2><p>'.$title.' draait om betrouwbaar handelen. Als je begrijpt wat er speelt, rustig blijft en de CN-afspraken volgt, help je mee aan een community waar mensen zich welkom en veilig voelen.</p></section>';
    }

    private function questionText(string $type, string $topic, int $number): string
    {
        return match ($type) {
            'true_false' => 'Waar of niet waar: bij '.$topic.' controleer je eerst de context voordat je reageert.',
            'scenario' => 'Situatievraag '.$number.': een lid raakt in de war over '.$topic.'. Wat is de beste eerste reactie?',
            default => 'Welke aanpak past het beste bij '.$topic.'?',
        };
    }

    private function questionOptions(string $type): array
    {
        if ($type === 'true_false') {
            return ['A' => 'Waar', 'B' => 'Niet waar'];
        }

        return [
            'A' => 'Rustig blijven, context controleren en volgens de CN-afspraken handelen.',
            'B' => 'De situatie negeren omdat iemand anders het waarschijnlijk oplost.',
            'C' => 'Direct streng reageren zonder extra informatie te verzamelen.',
            'D' => 'Interne informatie openbaar delen om sneller duidelijkheid te geven.',
        ];
    }

    private function description(string $role): string
    {
        return match ($role) {
            'helper' => 'Ontwikkel supportvaardigheden en leer leden professioneel begeleiden.',
            'moderator' => 'Leer veilig modereren, bewijs beoordelen en conflicten beheersen.',
            'admin' => 'Leer teams, projecten en alle operationele CN-systemen beheren.',
            'management' => 'Ontwikkel leiderschap, strategie, HR en crisismanagement.',
        };
    }

    private function assignment(string $role, int $module): string
    {
        $assignments = [
            'helper' => ['Verwelkom nieuwe leden', 'Help 5 leden', 'Beantwoord 10 vragen', 'Gebruik CN-systemen in support', 'Reflecteer op teamwerk'],
            'moderator' => ['Beoordeel 5 scenario’s', 'Maak een sanctierapport', 'Analyseer bewijs', 'Werk een escalatie uit', 'Evalueer teamcoordinatie'],
            'admin' => ['Beheer een project', 'Maak een planning', 'Controleer Awards beheer', 'Maak een rapportage', 'Werk een praktijkscenario uit'],
            'management' => ['Schrijf beleid', 'Analyseer communitygroei', 'Werk een partnerbesluit uit', 'Maak een risicobeoordeling', 'Werk een crisisscenario uit'],
        ];

        return $assignments[$role][$module - 1];
    }

    private function modules(string $role): array
    {
        if ($role === 'helper') {
            return $this->group([
                'Basis' => ['Rol van een Helper', 'Verwelkomen van leden', 'Eerste indruk', 'Positieve sfeer', 'Luisteren', 'Vragen beantwoorden', 'Discord etiquette', 'Support basis', 'Escalaties herkennen', 'Samenwerken'],
                'Support' => ['Tickets', 'Veelgestelde vragen', 'Doorverwijzen', 'Prioriteiten', 'Rust bewaren', 'Feedback verwerken', 'Nieuwe leden begeleiden', 'Problemen herkennen', 'Hulpbronnen gebruiken', 'Professioneel reageren'],
                'Praktijk' => array_map(fn ($number) => 'Supportscenario '.$number, range(1, 10)),
                'CN Systemen' => ['MijnCN', 'Awards', 'Mini Awards', 'Partnerprogramma', 'Nieuws', 'Events', 'Academy', 'Takenbord', 'Meldingen', 'Rapportages'],
                'Teamwerk' => ['Staff communicatie', 'Feedback geven', 'Feedback ontvangen', 'Teamwerk', 'Mentorschap', 'Activiteit', 'Verantwoordelijkheid', 'Community voorbeeldfunctie', 'Doorgroeien naar Moderator', 'Eindvoorbereiding'],
            ]);
        }

        $moduleNames = match ($role) {
            'moderator' => ['Moderatie Basis', 'Waarschuwingen', 'Timeouts', 'Bewijslast', 'Conflictbeheersing', 'Logs', 'Sancties', 'Escalaties', 'Praktijkscenario’s', 'Teamcoordinatie'],
            'admin' => ['Teamleiding', 'Dashboard beheer', 'Awards beheer', 'Partnerbeheer', 'Academy beheer', 'Support beheer', 'Rapportages', 'Projectbeheer', 'Personeelsbeheer', 'Praktijkscenario’s'],
            'management' => ['Community Management', 'HR Management', 'Strategische planning', 'Groei van CN', 'Besluitvorming', 'Partnerbeleid', 'Risicobeheer', 'Leiderschap', 'Projectleiding', 'Crisismanagement'],
        };
        $groups = [];
        foreach (array_chunk($moduleNames, 2) as $index => $pair) {
            $lessons = [];
            foreach ($pair as $name) {
                foreach (['Basis en doel', 'Proces en beleid', 'Communicatie', 'Praktische toepassing', 'Evaluatie'] as $part) {
                    $lessons[] = $name.': '.$part;
                }
            }
            $groups['Module '.($index + 1)] = $lessons;
        }

        return $this->group($groups);
    }

    private function group(array $groups): array
    {
        return collect($groups)->map(fn ($lessons, $name) => ['name' => $name, 'lessons' => $lessons])->values()->all();
    }

    private function archiveLidAcademy(): void
    {
        if (!Schema::hasTable('learning_paths')) {
            return;
        }

        LearningPath::where('target_role', 'lid')->get()->each(function (LearningPath $path) {
            $hasProgress = Schema::hasTable('lesson_progress') && DB::table('lesson_progress')
                ->join('lessons', 'lessons.id', '=', 'lesson_progress.lesson_id')
                ->where('lessons.learning_path_id', $path->id)
                ->exists();
            $hasCertificates = Schema::hasTable('certificates') && DB::table('certificates')
                ->where('learning_path_id', $path->id)
                ->exists();

            if ($hasProgress || $hasCertificates) {
                $path->update(['is_published' => false]);

                return;
            }

            $path->delete();
        });

        if (!Schema::hasTable('badges')) {
            return;
        }

        $badge = Badge::where('slug', 'lid-academy-2026')->first();
        if (!$badge) {
            return;
        }

        $isAwarded = Schema::hasTable('badge_user')
            && DB::table('badge_user')->where('badge_id', $badge->id)->exists();
        if (!$isAwarded) {
            $badge->delete();
        }
    }
}
