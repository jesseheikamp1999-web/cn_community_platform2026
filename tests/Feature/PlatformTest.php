<?php

namespace Tests\Feature;

use App\Models\AwardCategory;
use App\Models\AwardEdition;
use App\Models\AwardRound;
use App\Models\Application;
use App\Models\DiscordMember;
use App\Models\Badge;
use App\Models\LearningPath;
use App\Models\Nomination;
use App\Models\Content;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Partner;
use App\Models\Permission;
use App\Models\QuestionBank;
use App\Models\Task;
use App\Models\User;
use App\Services\TaskWorkflowService;
use App\Services\DiscordService;
use App\Services\CommunityAutomationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PlatformTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_is_available(): void
    {
        $this->get('/')->assertOk()->assertSee('Samen zijn we');
    }

    public function test_homepage_member_count_only_uses_discord_connected_mijncn_users(): void
    {
        DiscordMember::create([
            'discord_id' => 'connected-community-member',
            'username' => 'connected',
            'display_name' => 'Connected Member',
            'platform_role' => 'member',
            'is_active' => true,
            'is_bot' => false,
        ]);
        DiscordMember::create([
            'discord_id' => 'inactive-community-member',
            'username' => 'inactive',
            'display_name' => 'Inactive Member',
            'platform_role' => 'member',
            'is_active' => false,
            'is_bot' => false,
        ]);
        User::factory()->create(['discord_id' => null]);
        $expected = DiscordMember::where('is_active', true)->where('is_bot', false)->count();

        $this->get(route('home'))
            ->assertOk()
            ->assertSee(number_format($expected).'+');
    }

    public function test_homepage_shows_ranked_partner_projects(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Stumpertjes')
            ->assertSee('NightMC')
            ->assertSee('#1')
            ->assertSee('94');
    }

    public function test_dashboard_requires_authentication(): void
    {
        $this->get('/mijn-cn')->assertRedirect();
    }

    public function test_discord_account_is_required_for_nomination(): void
    {
        $user = User::factory()->create(['discord_id' => null]);
        $edition = AwardEdition::create(['name' => 'Test', 'slug' => 'test', 'type' => 'cn_awards', 'year' => 2026]);
        $category = AwardCategory::create(['award_edition_id' => $edition->id, 'name' => 'Test', 'slug' => 'test']);

        $this->actingAs($user)->post(route('awards.nominate', $category), [
            'nominee_name' => 'Testlid',
            'motivation' => str_repeat('Goede motivatie. ', 4),
        ])->assertSessionHasErrors('discord');
    }

    public function test_member_can_nominate_during_an_open_nomination_round(): void
    {
        $user = User::factory()->create(['discord_id' => 'discord-member']);
        $edition = AwardEdition::create([
            'name' => 'CN Awards 2026',
            'slug' => 'awards-open',
            'type' => 'cn_awards',
            'year' => 2026,
            'status' => 'nominations',
        ]);
        AwardRound::create([
            'award_edition_id' => $edition->id,
            'name' => 'Nominaties',
            'type' => 'nomination',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'is_active' => true,
        ]);
        $category = AwardCategory::create([
            'award_edition_id' => $edition->id,
            'name' => 'Beste Community',
            'slug' => 'beste-community-open',
        ]);

        $this->actingAs($user)->post(route('awards.nominate', $category), [
            'nominee_name' => 'Community Nederland',
            'motivation' => str_repeat('Een sterke en betrokken community. ', 2),
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('nominations', [
            'award_category_id' => $category->id,
            'user_id' => $user->id,
            'nominee_name' => 'Community Nederland',
            'status' => 'pending',
        ]);
    }

    public function test_member_can_change_vote_once_per_category_and_round_with_history(): void
    {
        $submitter = User::factory()->create(['discord_id' => 'submitter']);
        $voter = User::factory()->create(['discord_id' => 'voter']);
        $edition = AwardEdition::create([
            'name' => 'CN Awards 2026',
            'slug' => 'awards-voting',
            'type' => 'cn_awards',
            'year' => 2026,
            'status' => 'voting',
        ]);
        $round = AwardRound::create([
            'award_edition_id' => $edition->id,
            'name' => 'Publieksstemmen',
            'type' => 'public_vote',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'is_active' => true,
        ]);
        $category = AwardCategory::create([
            'award_edition_id' => $edition->id,
            'name' => 'Beste Community',
            'slug' => 'beste-community-voting',
        ]);
        $first = Nomination::create([
            'award_category_id' => $category->id,
            'user_id' => $submitter->id,
            'nominee_name' => 'Kandidaat Een',
            'motivation' => 'Een uitgebreide motivatie voor kandidaat een.',
            'status' => 'approved',
        ]);
        $second = Nomination::create([
            'award_category_id' => $category->id,
            'user_id' => $submitter->id,
            'nominee_name' => 'Kandidaat Twee',
            'motivation' => 'Een uitgebreide motivatie voor kandidaat twee.',
            'status' => 'approved',
        ]);

        $this->actingAs($voter)->post(route('awards.vote', $first), ['round_id' => $round->id])
            ->assertSessionHasNoErrors();
        $this->actingAs($voter)->post(route('awards.vote', $second), ['round_id' => $round->id])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('votes', 2);
        $this->assertDatabaseHas('votes', ['nomination_id' => $first->id, 'is_valid' => false]);
        $this->assertDatabaseHas('votes', ['nomination_id' => $second->id, 'is_valid' => true, 'superseded_at' => null]);
        $this->assertDatabaseHas('vote_histories', [
            'from_nomination_id' => $first->id,
            'to_nomination_id' => $second->id,
        ]);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $voter->id,
            'type' => 'awards.vote_recorded',
        ]);
    }

    public function test_awards_phase_and_discord_announcement_are_automated_once(): void
    {
        Http::fake();
        config(['services.discord.webhook_url' => 'https://discord.test/webhook']);
        AwardRound::query()->update(['is_active' => false]);
        $edition = AwardEdition::create([
            'name' => 'Automatische Awards',
            'slug' => 'automatische-awards',
            'type' => 'cn_awards',
            'year' => 2029,
            'status' => 'draft',
        ]);
        $round = AwardRound::create([
            'award_edition_id' => $edition->id,
            'name' => 'Stemronde',
            'type' => 'public_vote',
            'starts_at' => now()->subMinute(),
            'ends_at' => now()->addDay(),
            'is_active' => true,
        ]);

        $automation = app(CommunityAutomationService::class);
        $this->assertSame(1, $automation->processAwardPhases());
        $this->assertSame(0, $automation->processAwardPhases());

        $this->assertSame('voting', $edition->fresh()->status);
        $this->assertDatabaseHas('automation_logs', [
            'key' => 'awards:round-opened:'.$round->id,
            'type' => 'awards_round',
        ]);
        Http::assertSentCount(1);
    }

    public function test_awards_page_shows_one_selected_category_and_vote_state_per_category(): void
    {
        $submitter = User::factory()->create(['discord_id' => 'awards-submitter']);
        $voter = User::factory()->create(['discord_id' => 'awards-voter']);
        $edition = AwardEdition::create([
            'name' => 'CN Awards 2026',
            'slug' => 'awards-category-picker',
            'type' => 'cn_awards',
            'year' => 2026,
            'status' => 'voting',
        ]);
        $round = AwardRound::create([
            'award_edition_id' => $edition->id,
            'name' => 'Publieksstemmen',
            'type' => 'public_vote',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'is_active' => true,
        ]);
        $firstCategory = AwardCategory::create([
            'award_edition_id' => $edition->id,
            'name' => 'Eerste Categorie',
            'slug' => 'eerste-categorie',
            'sort_order' => 10,
        ]);
        $secondCategory = AwardCategory::create([
            'award_edition_id' => $edition->id,
            'name' => 'Tweede Categorie',
            'slug' => 'tweede-categorie',
            'sort_order' => 20,
        ]);
        $firstNomination = Nomination::create([
            'award_category_id' => $firstCategory->id,
            'user_id' => $submitter->id,
            'nominee_name' => 'Alleen Eerste Kandidaat',
            'motivation' => 'Een uitgebreide motivatie voor de eerste kandidaat.',
            'status' => 'approved',
        ]);
        Nomination::create([
            'award_category_id' => $secondCategory->id,
            'user_id' => $submitter->id,
            'nominee_name' => 'Alleen Tweede Kandidaat',
            'motivation' => 'Een uitgebreide motivatie voor de tweede kandidaat.',
            'status' => 'approved',
        ]);

        $this->actingAs($voter)->post(route('awards.vote', $firstNomination), ['round_id' => $round->id])
            ->assertRedirect(route('awards', ['categorie' => $firstCategory->slug]));

        $this->actingAs($voter)
            ->get(route('awards', ['categorie' => $firstCategory->slug]))
            ->assertOk()
            ->assertSee('Alleen Eerste Kandidaat')
            ->assertDontSee('Alleen Tweede Kandidaat')
            ->assertSee('Jouw stem');

        $this->actingAs($voter)
            ->get(route('awards', ['categorie' => $secondCategory->slug]))
            ->assertOk()
            ->assertSee('Alleen Tweede Kandidaat')
            ->assertDontSee('Alleen Eerste Kandidaat')
            ->assertDontSee('Stem aanpassen');
    }

    public function test_owner_can_manage_awards_categories_from_mijncn(): void
    {
        $owner = User::factory()->create(['role' => \App\Enums\UserRole::Owner]);
        $edition = AwardEdition::create([
            'name' => 'CN Awards 2026',
            'slug' => 'awards-category-management',
            'type' => 'cn_awards',
            'year' => 2026,
            'status' => 'draft',
        ]);

        $this->actingAs($owner)->post(route('staff.awards.categories.store', $edition), [
            'name' => 'Beste Nieuwkomer',
            'description' => 'Voor een sterke nieuwe community.',
            'sort_order' => 30,
            'public_weight' => 60,
            'jury_weight' => 40,
            'is_active' => 1,
        ])->assertSessionHasNoErrors();

        $category = AwardCategory::where('award_edition_id', $edition->id)
            ->where('name', 'Beste Nieuwkomer')
            ->firstOrFail();

        $this->actingAs($owner)->put(route('staff.awards.categories.update', $category), [
            'name' => 'Beste Opkomende Community',
            'description' => 'Aangepaste omschrijving.',
            'sort_order' => 15,
            'public_weight' => 70,
            'jury_weight' => 30,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('award_categories', [
            'id' => $category->id,
            'name' => 'Beste Opkomende Community',
            'slug' => 'beste-opkomende-community',
            'sort_order' => 15,
            'is_active' => false,
        ]);
        $this->actingAs($owner)->get(route('staff.awards'))
            ->assertOk()
            ->assertSee('Categorieën beheren')
            ->assertSee('Beste Opkomende Community');
    }

    public function test_mini_awards_use_independent_categories_nominations_and_votes(): void
    {
        $submitter = User::factory()->create(['discord_id' => 'mini-submitter']);
        $voter = User::factory()->create(['discord_id' => 'mini-voter']);
        $normalEdition = AwardEdition::create([
            'name' => 'CN Awards 2026',
            'slug' => 'normal-independent-awards',
            'type' => 'cn_awards',
            'year' => 2026,
            'status' => 'voting',
        ]);
        $miniEdition = AwardEdition::create([
            'name' => 'Mini Awards Test',
            'slug' => 'independent-mini-awards',
            'type' => 'mini_awards',
            'year' => 2027,
            'status' => 'voting',
        ]);
        $normalCategory = AwardCategory::create([
            'award_edition_id' => $normalEdition->id,
            'name' => 'Normale Categorie',
            'slug' => 'normale-categorie-independent',
        ]);
        $miniCategory = AwardCategory::create([
            'award_edition_id' => $miniEdition->id,
            'name' => 'Mini Categorie',
            'slug' => 'mini-categorie-independent',
            'jury_weight' => 0,
            'public_weight' => 100,
        ]);
        $normalNomination = Nomination::create([
            'award_category_id' => $normalCategory->id,
            'user_id' => $submitter->id,
            'nominee_name' => 'Normale Kandidaat',
            'motivation' => 'Deze kandidaat hoort uitsluitend bij de normale Awards.',
            'status' => 'approved',
        ]);
        $miniNomination = Nomination::create([
            'award_category_id' => $miniCategory->id,
            'user_id' => $submitter->id,
            'nominee_name' => 'Mini Kandidaat',
            'motivation' => 'Deze kandidaat hoort uitsluitend bij de Mini Awards.',
            'status' => 'approved',
        ]);
        $miniRound = AwardRound::create([
            'award_edition_id' => $miniEdition->id,
            'name' => 'Mini stemronde',
            'type' => 'public_vote',
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addDay(),
            'is_active' => true,
        ]);

        $this->get(route('mini.awards'))
            ->assertOk()
            ->assertSee('Mini Categorie')
            ->assertSee('Mini Kandidaat')
            ->assertDontSee('Normale Categorie')
            ->assertDontSee('Normale Kandidaat');

        $this->actingAs($voter)
            ->post(route('awards.vote', $miniNomination), ['round_id' => $miniRound->id])
            ->assertRedirect(route('mini.awards', ['categorie' => $miniCategory->slug]));

        $this->assertDatabaseHas('votes', [
            'nomination_id' => $miniNomination->id,
            'round_id' => $miniRound->id,
            'user_id' => $voter->id,
        ]);
        $this->assertDatabaseMissing('votes', ['nomination_id' => $normalNomination->id]);
    }

    public function test_owner_has_separate_mini_awards_management(): void
    {
        $owner = User::factory()->create(['role' => \App\Enums\UserRole::Owner]);
        AwardEdition::create([
            'name' => 'Normale Awards',
            'slug' => 'normal-management-awards',
            'type' => 'cn_awards',
            'year' => 2026,
            'status' => 'draft',
        ]);
        $miniEdition = AwardEdition::create([
            'name' => 'Mini Beheer Editie',
            'slug' => 'mini-management-awards',
            'type' => 'mini_awards',
            'year' => 2027,
            'status' => 'nominations',
        ]);
        AwardCategory::create([
            'award_edition_id' => $miniEdition->id,
            'name' => 'Eigen Mini Categorie',
            'slug' => 'eigen-mini-categorie',
            'jury_weight' => 0,
            'public_weight' => 100,
        ]);

        $this->actingAs($owner)
            ->get(route('staff.mini-awards'))
            ->assertOk()
            ->assertSee('Mini Beheer Editie')
            ->assertSee('Eigen Mini Categorie')
            ->assertDontSee('Juryrapport toevoegen');
    }

    public function test_missing_mini_awards_edition_is_created_for_an_owner(): void
    {
        AwardEdition::where('type', 'mini_awards')->delete();
        $owner = User::factory()->create(['role' => \App\Enums\UserRole::Owner]);

        $this->actingAs($owner)->get(route('staff.mini-awards'))
            ->assertOk()
            ->assertSee('Mini Awards '.now()->year)
            ->assertSee('Rondes en tijdstippen');

        $this->assertDatabaseHas('award_editions', [
            'type' => 'mini_awards',
            'year' => now()->year,
            'status' => 'draft',
        ]);
    }

    public function test_owner_can_schedule_normal_and_mini_awards_with_times(): void
    {
        $owner = User::factory()->create(['role' => \App\Enums\UserRole::Owner]);
        $normalEdition = AwardEdition::create([
            'name' => 'Planning Awards',
            'slug' => 'planning-awards',
            'type' => 'cn_awards',
            'year' => 2026,
        ]);
        $miniEdition = AwardEdition::create([
            'name' => 'Planning Mini Awards',
            'slug' => 'planning-mini-awards',
            'type' => 'mini_awards',
            'year' => 2027,
        ]);

        $this->actingAs($owner)->post(route('staff.awards.rounds.store', $normalEdition), [
            'name' => 'Stemronde',
            'type' => 'public_vote',
            'starts_at' => '2026-09-01 18:30:00',
            'ends_at' => '2026-09-14 21:45:00',
            'is_active' => '1',
        ])->assertSessionHasNoErrors();

        $this->actingAs($owner)->post(route('staff.awards.rounds.store', $miniEdition), [
            'name' => 'Mini nominaties',
            'type' => 'nomination',
            'starts_at' => '2026-07-01 12:15:00',
            'ends_at' => '2026-07-08 20:00:00',
            'is_active' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('award_rounds', [
            'award_edition_id' => $normalEdition->id,
            'type' => 'public_vote',
            'starts_at' => '2026-09-01 18:30:00',
            'ends_at' => '2026-09-14 21:45:00',
        ]);
        $this->assertDatabaseHas('award_rounds', [
            'award_edition_id' => $miniEdition->id,
            'type' => 'nomination',
            'starts_at' => '2026-07-01 12:15:00',
            'ends_at' => '2026-07-08 20:00:00',
        ]);

        $this->actingAs($owner)->get(route('staff.mini-awards'))
            ->assertOk()
            ->assertSee('Rondes en tijdstippen')
            ->assertSee('2026-07-01T12:15', false);
    }

    public function test_mini_awards_reject_a_jury_round(): void
    {
        $owner = User::factory()->create(['role' => \App\Enums\UserRole::Owner]);
        $edition = AwardEdition::create([
            'name' => 'Mini zonder jury',
            'slug' => 'mini-zonder-jury',
            'type' => 'mini_awards',
            'year' => 2028,
        ]);

        $this->actingAs($owner)->post(route('staff.awards.rounds.store', $edition), [
            'name' => 'Jury',
            'type' => 'jury',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(2),
            'is_active' => '1',
        ])->assertSessionHasErrors('type');
    }

    public function test_owner_can_submit_a_jury_assessment(): void
    {
        $owner = User::factory()->create(['role' => \App\Enums\UserRole::Owner, 'discord_id' => 'owner']);
        $submitter = User::factory()->create();
        $edition = AwardEdition::create([
            'name' => 'CN Awards 2026',
            'slug' => 'awards-jury',
            'type' => 'cn_awards',
            'year' => 2026,
            'status' => 'jury',
        ]);
        $category = AwardCategory::create([
            'award_edition_id' => $edition->id,
            'name' => 'Beste Community',
            'slug' => 'beste-community-jury',
        ]);
        $nomination = Nomination::create([
            'award_category_id' => $category->id,
            'user_id' => $submitter->id,
            'nominee_name' => 'Jury Kandidaat',
            'motivation' => 'Een uitgebreide motivatie voor de jury.',
            'status' => 'approved',
        ]);

        $this->actingAs($owner)->post(route('staff.awards.jury.score', $nomination), [
            'impact_score' => 8,
            'activity_score' => 7,
            'professionalism_score' => 6,
            'innovation_score' => 9,
            'future_score' => 10,
            'strengths' => str_repeat('Sterke community impact met duidelijke betrokkenheid en professionele uitstraling. ', 4),
            'improvements' => str_repeat('De kandidaat kan communicatie en documentatie nog verder verbeteren. ', 4),
            'personal_note' => str_repeat('Mijn toelichting benoemt waarom deze kandidaat binnen CN opvalt en toekomstpotentie laat zien. ', 5),
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('jury_scores', [
            'nomination_id' => $nomination->id,
            'jury_id' => $owner->id,
            'score' => 80,
        ]);
    }

    public function test_duplicate_awards_nomination_is_merged_into_the_original(): void
    {
        $firstUser = User::factory()->create(['discord_id' => 'discord-first']);
        $secondUser = User::factory()->create(['discord_id' => 'discord-second']);
        $edition = AwardEdition::create([
            'name' => 'CN Awards 2026',
            'slug' => 'awards-duplicates',
            'type' => 'cn_awards',
            'year' => 2026,
            'status' => 'nominations',
        ]);
        AwardRound::create([
            'award_edition_id' => $edition->id,
            'name' => 'Nominaties',
            'type' => 'nomination',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'is_active' => true,
        ]);
        $category = AwardCategory::create([
            'award_edition_id' => $edition->id,
            'name' => 'Beste Community',
            'slug' => 'beste-community-duplicates',
        ]);

        $this->actingAs($firstUser)->post(route('awards.nominate', $category), [
            'nominee_name' => 'CN Community',
            'motivation' => str_repeat('Sterke nominatie met bewijs. ', 3),
        ])->assertSessionHasNoErrors();
        $this->actingAs($secondUser)->post(route('awards.nominate', $category), [
            'nominee_name' => 'cn community',
            'motivation' => str_repeat('Nog een nominatie voor dezelfde community. ', 3),
        ])->assertSessionHasNoErrors();

        $original = Nomination::where('nominee_name', 'CN Community')->firstOrFail();
        $this->assertSame(1, $original->fresh()->duplicate_count);
        $this->assertDatabaseHas('nominations', [
            'nominee_name' => 'cn community',
            'status' => 'duplicate',
            'canonical_nomination_id' => $original->id,
        ]);
    }

    public function test_approved_nomination_moves_from_review_queue_to_jury_queue(): void
    {
        $owner = User::factory()->create(['role' => \App\Enums\UserRole::Owner]);
        $submitter = User::factory()->create();
        $edition = AwardEdition::create([
            'name' => 'CN Awards 2026',
            'slug' => 'awards-review-queue',
            'type' => 'cn_awards',
            'year' => 2026,
            'status' => 'jury',
        ]);
        $category = AwardCategory::create([
            'award_edition_id' => $edition->id,
            'name' => 'Beste Community',
            'slug' => 'beste-community-review-queue',
        ]);
        $nomination = Nomination::create([
            'award_category_id' => $category->id,
            'user_id' => $submitter->id,
            'nominee_name' => 'Te Beoordelen Community',
            'motivation' => 'Een uitgebreide motivatie voor beoordeling.',
            'status' => 'pending',
        ]);

        $before = $this->actingAs($owner)->get(route('staff.awards'))->assertOk();
        $this->assertTrue($before->viewData('nominations')->contains('id', $nomination->id));

        $this->actingAs($owner)->patch(route('staff.awards.review', $nomination), [
            'status' => 'approved',
            'review_note' => 'Goedgekeurd voor de juryronde.',
        ])->assertSessionHasNoErrors();

        $after = $this->actingAs($owner)->get(route('staff.awards'))->assertOk();
        $this->assertFalse($after->viewData('nominations')->contains('id', $nomination->id));
        $this->assertTrue($after->viewData('juryNominations')->contains('id', $nomination->id));
    }

    public function test_public_nomination_profile_reuses_legacy_profile_fields(): void
    {
        $submitter = User::factory()->create();
        $edition = AwardEdition::create([
            'name' => 'CN Awards 2026',
            'slug' => 'awards-profile',
            'type' => 'cn_awards',
            'year' => 2026,
            'status' => 'voting',
        ]);
        $category = AwardCategory::create([
            'award_edition_id' => $edition->id,
            'name' => 'Beste Community',
            'slug' => 'beste-community-profile',
        ]);
        $nomination = Nomination::create([
            'award_category_id' => $category->id,
            'user_id' => $submitter->id,
            'nominee_name' => 'Profiel Community',
            'motivation' => 'Een uitgebreide motivatie voor het publieke profiel.',
            'website_url' => 'https://example.com',
            'discord_invite' => 'https://discord.gg/example',
            'is_verified' => true,
            'status' => 'approved',
        ]);

        $this->get(route('awards.nomination', $nomination))
            ->assertOk()
            ->assertSee('Profiel Community')
            ->assertSee('Geverifieerde finalist')
            ->assertSee('https://example.com', false);
    }

    public function test_contact_form_is_stored(): void
    {
        $this->post(route('forms.store', 'contact'), [
            'name' => 'CN Lid',
            'email' => 'lid@example.nl',
            'subject' => 'Vraag',
            'message' => 'Dit is een geldige vraag voor het communityteam.',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('inquiries', ['type' => 'contact', 'email' => 'lid@example.nl']);
    }

    public function test_public_staff_application_enters_the_hr_workflow(): void
    {
        $this->post(route('forms.store', 'application'), [
            'name' => 'Nieuwe Helper',
            'email' => 'helper@example.nl',
            'subject' => 'Helper',
            'age' => 21,
            'experience' => 'Ik heb ervaring met het begeleiden van leden in meerdere communities.',
            'availability' => 'Maandag, woensdag en in het weekend.',
            'message' => 'Ik help graag nieuwe leden en ben meerdere avonden per week beschikbaar.',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('applications', [
            'name' => 'Nieuwe Helper',
            'email' => 'helper@example.nl',
            'position' => 'Helper',
            'status' => 'new',
        ]);
    }

    public function test_owner_can_review_an_application_in_hr(): void
    {
        $owner = User::factory()->create(['role' => \App\Enums\UserRole::Owner]);
        $application = Application::create([
            'name' => 'Kandidaat',
            'email' => 'kandidaat@example.nl',
            'position' => 'Moderator',
            'answers' => ['motivation' => 'Ik wil bijdragen aan een veilige community.'],
            'status' => 'new',
        ]);

        $this->actingAs($owner)->get(route('staff.hr'))
            ->assertOk()
            ->assertSee('Kandidaat');

        $this->actingAs($owner)->patch(route('staff.hr.applications.update', $application), [
            'status' => 'interview',
            'internal_note' => 'Uitnodigen voor een kennismakingsgesprek.',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('applications', [
            'id' => $application->id,
            'status' => 'interview',
            'reviewed_by' => $owner->id,
        ]);
    }

    public function test_completed_hr_application_is_archived_and_hidden_from_active_work(): void
    {
        $owner = User::factory()->create(['role' => \App\Enums\UserRole::Owner]);
        $application = Application::create([
            'name' => 'Afgeronde Kandidaat',
            'email' => 'afgerond@example.nl',
            'position' => 'Helper',
            'answers' => ['motivation' => 'Ik wil bijdragen aan de community.'],
            'status' => 'interview',
        ]);

        $this->actingAs($owner)->patch(route('staff.hr.applications.update', $application), [
            'status' => 'accepted',
            'internal_note' => 'Aangenomen na een goed gesprek.',
        ])->assertRedirect(route('staff.hr'));

        $this->assertNotNull($application->fresh()->archived_at);
        $this->actingAs($owner)->get(route('staff.hr'))
            ->assertOk()
            ->assertDontSee('Afgeronde Kandidaat');
        $this->actingAs($owner)->get(route('staff.hr', ['status' => 'archived']))
            ->assertOk()
            ->assertSee('Afgeronde Kandidaat')
            ->assertSee('Aangenomen');
    }

    public function test_members_cannot_access_the_hr_workspace(): void
    {
        $member = User::factory()->create();

        $this->actingAs($member)->get(route('staff.hr'))->assertForbidden();
    }

    public function test_birthday_page_respects_member_and_staff_visibility(): void
    {
        $member = User::factory()->create(['name' => 'Kijker']);
        $communityBirthday = User::factory()->create([
            'name' => 'Community Verjaardag',
            'birth_date' => today()->subYears(20),
            'birthday_visibility' => 'community',
        ]);
        $staffBirthday = User::factory()->create([
            'name' => 'Staff Verjaardag',
            'birth_date' => today()->addDay()->subYears(24),
            'birthday_visibility' => 'staff',
        ]);
        User::factory()->create([
            'name' => 'Prive Verjaardag',
            'birth_date' => today()->addDays(2)->subYears(30),
            'birthday_visibility' => 'private',
        ]);

        $this->actingAs($member)->get(route('mijncn.module', 'birthdays'))
            ->assertOk()
            ->assertSee($communityBirthday->name)
            ->assertDontSee($staffBirthday->name)
            ->assertDontSee('Prive Verjaardag');

        $owner = User::factory()->create(['role' => \App\Enums\UserRole::Owner]);
        $this->actingAs($owner)->get(route('mijncn.module', 'birthdays'))
            ->assertOk()
            ->assertSee($communityBirthday->name)
            ->assertSee($staffBirthday->name)
            ->assertDontSee('Prive Verjaardag');
    }

    public function test_birthday_avatar_has_a_safe_discord_fallback(): void
    {
        $viewer = User::factory()->create();
        User::factory()->create([
            'name' => 'Luca',
            'discord_id' => 'broken-avatar-id',
            'discord_avatar' => 'https://cdn.discordapp.com/not-found.png',
            'birth_date' => now()->addMonth()->toDateString(),
            'birthday_visibility' => 'community',
        ]);

        $this->actingAs($viewer)
            ->get(route('mijncn.module', 'birthdays'))
            ->assertOk()
            ->assertSee('onerror=', false)
            ->assertSee('LU');
    }

    public function test_owner_can_manage_partner_projects_from_mijncn(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create(['role' => \App\Enums\UserRole::Owner]);

        $this->actingAs($owner)
            ->get(route('mijncn.module', 'partners'))
            ->assertOk()
            ->assertSee('Projecten &amp; partners', false);

        $image = UploadedFile::fake()->createWithContent(
            'project.png',
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=')
        );
        $this->actingAs($owner)->post(route('mijncn.partners.store'), [
            'name' => 'CN Test Project',
            'description' => 'Een testproject voor de ranglijst.',
            'website' => 'https://example.com',
            'status' => 'active',
            'tier' => 'community',
            'category' => 'project',
            'score' => 96,
            'position' => 2,
            'is_featured' => '1',
            'logo' => $image,
        ])->assertSessionHasNoErrors();

        $partner = Partner::where('slug', 'cn-test-project')->firstOrFail();
        Storage::disk('public')->assertExists($partner->logo);
        $this->assertDatabaseHas('partners', [
            'id' => $partner->id,
            'score' => 96,
            'position' => 2,
            'is_featured' => true,
        ]);

        $this->actingAs($owner)->put(route('mijncn.partners.update', $partner), [
            'name' => 'CN Test Project',
            'description' => 'Bijgewerkte omschrijving.',
            'website' => 'https://example.com',
            'status' => 'active',
            'tier' => 'strategic',
            'category' => 'server',
            'score' => 99,
            'position' => 1,
            'is_featured' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('partners', [
            'id' => $partner->id,
            'tier' => 'strategic',
            'score' => 99,
            'position' => 1,
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('CN Test Project')
            ->assertSee('99');
    }

    public function test_owner_can_upgrade_partner_rankings_from_mijncn_without_artisan(): void
    {
        $owner = User::factory()->create(['role' => \App\Enums\UserRole::Owner]);

        $this->actingAs($owner)
            ->post(route('mijncn.partners.upgrade'))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertTrue(Schema::hasColumns('partners', ['description', 'category', 'score', 'position', 'is_featured']));
        $this->assertDatabaseHas('partners', [
            'slug' => 'nightmc',
            'score' => 89,
            'position' => 3,
            'is_featured' => true,
        ]);
    }

    public function test_member_cannot_upgrade_partner_rankings(): void
    {
        $member = User::factory()->create(['role' => \App\Enums\UserRole::Member]);

        $this->actingAs($member)
            ->post(route('mijncn.partners.upgrade'))
            ->assertForbidden();
    }

    public function test_birthday_notifications_are_sent_once_to_mijncn_and_discord(): void
    {
        Http::fake();
        config(['services.discord.webhook_url' => 'https://discord.test/webhook']);
        $birthdayUser = User::factory()->create([
            'name' => 'Jarige Gebruiker',
            'discord_id' => 'birthday-discord-id',
            'birth_date' => today()->subYears(25),
            'birthday_visibility' => 'community',
        ]);
        $recipient = User::factory()->create(['birthday_notifications' => true]);
        $optedOut = User::factory()->create(['birthday_notifications' => false]);

        $automation = app(CommunityAutomationService::class);
        $this->assertSame(1, $automation->processBirthdays());
        $this->assertSame(0, $automation->processBirthdays());

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $recipient->id,
            'type' => 'community.birthday',
        ]);
        $this->assertDatabaseMissing('notifications', [
            'notifiable_id' => $optedOut->id,
            'type' => 'community.birthday',
        ]);
        $this->assertDatabaseHas('automation_logs', [
            'key' => 'birthday:'.$birthdayUser->id.':'.today()->format('Y'),
        ]);
        Http::assertSentCount(1);
    }

    public function test_authenticated_dashboard_renders_real_empty_states(): void
    {
        $user = User::factory()->create(['xp' => 1250]);

        $this->actingAs($user)
            ->get('/mijn-cn')
            ->assertOk()
            ->assertSee('Jouw activiteit begint hier')
            ->assertSee('Level 3');
    }

    public function test_dashboard_handles_badge_awarded_at_from_database_as_a_string(): void
    {
        $user = User::factory()->create();
        $badge = Badge::create([
            'name' => 'Testbadge',
            'slug' => 'testbadge',
            'description' => 'Een testbadge.',
        ]);
        DB::table('badge_user')->insert([
            'badge_id' => $badge->id,
            'user_id' => $user->id,
            'awarded_at' => '2026-06-12 12:00:00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Badge behaald: Testbadge')
            ->assertDontSee('MijnCN kon niet volledig worden geladen');
    }

    public function test_all_mijncn_modules_are_available_for_an_authenticated_user(): void
    {
        $user = User::factory()->create();

        foreach (['profile', 'notifications', 'inbox', 'community', 'birthdays', 'nominations', 'votes', 'results', 'lessons', 'exams', 'certificates', 'badges', 'tasks', 'nomi', 'settings'] as $module) {
            $this->actingAs($user)
                ->get(route('mijncn.module', $module))
                ->assertOk();
        }
    }

    public function test_community_directory_only_lists_discord_connected_mijncn_users(): void
    {
        $viewer = User::factory()->create(['discord_id' => 'viewer-discord']);
        $connected = User::factory()->create([
            'name' => 'Echt Communitylid',
            'discord_id' => 'community-discord',
            'discord_username' => 'communitynaam',
            'last_seen_at' => now(),
        ]);
        DiscordMember::create([
            'discord_id' => $viewer->discord_id,
            'username' => $viewer->discord_username,
            'display_name' => $viewer->name,
            'platform_role' => 'member',
            'is_active' => true,
            'is_bot' => false,
        ]);
        DiscordMember::create([
            'discord_id' => $connected->discord_id,
            'username' => $connected->discord_username,
            'display_name' => $connected->name,
            'platform_role' => 'member',
            'is_active' => true,
            'is_bot' => false,
        ]);
        DiscordMember::create([
            'discord_id' => 'discord-only-member',
            'username' => 'alleendiscord',
            'display_name' => 'Alleen Discord',
            'platform_role' => 'member',
            'is_active' => true,
            'is_bot' => false,
        ]);
        User::factory()->create([
            'name' => 'Lokaal Testaccount',
            'discord_id' => null,
        ]);

        $this->actingAs($viewer)
            ->get(route('mijncn.module', ['module' => 'community', 'q' => 'communitynaam']))
            ->assertOk()
            ->assertSee($connected->name)
            ->assertSee('Nu actief in MijnCN')
            ->assertDontSee('Lokaal Testaccount');

        $this->actingAs($viewer)
            ->get(route('mijncn.module', ['module' => 'community', 'q' => 'alleendiscord']))
            ->assertOk()
            ->assertSee('Alleen Discord')
            ->assertSee('Discord-lid');
    }

    public function test_discord_member_sync_stores_humans_and_excludes_bots(): void
    {
        config([
            'services.discord.bot_token' => 'test-token',
            'services.discord.guild_id' => 'test-guild',
        ]);
        Http::fake([
            'https://discord.com/api/v10/guilds/test-guild/members*' => Http::response([
                [
                    'user' => ['id' => 'discord-human', 'username' => 'mens', 'global_name' => 'Discord Mens', 'avatar' => 'hash'],
                    'roles' => [],
                    'joined_at' => now()->toIso8601String(),
                ],
                [
                    'user' => ['id' => 'discord-bot', 'username' => 'bot', 'bot' => true],
                    'roles' => [],
                ],
            ]),
        ]);

        $count = app(\App\Services\DiscordMemberSyncService::class)->sync();

        $this->assertSame(1, $count);
        $this->assertDatabaseHas('discord_members', [
            'discord_id' => 'discord-human',
            'display_name' => 'Discord Mens',
            'is_active' => true,
        ]);
        $this->assertDatabaseMissing('discord_members', ['discord_id' => 'discord-bot']);
    }

    public function test_discord_member_sync_updates_linked_mijncn_role_and_standard_staff_title(): void
    {
        config(['services.discord.roles.management' => 'management-role']);

        $user = User::factory()->create([
            'discord_id' => 'discord-luca',
            'role' => \App\Enums\UserRole::Helper,
        ]);
        $user->staffProfile()->create([
            'position' => 'Helper',
            'status' => 'active',
            'joined_at' => today(),
        ]);

        app(\App\Services\DiscordMemberSyncService::class)->storeMember([
            'user' => [
                'id' => 'discord-luca',
                'username' => 'luca',
                'global_name' => 'Luca',
            ],
            'roles' => ['management-role'],
        ]);

        $this->assertSame(\App\Enums\UserRole::Management, $user->fresh()->role);
        $this->assertSame('Management', $user->fresh()->staffProfile->position);
        $this->assertSame('Management', $user->fresh()->publicPosition());
    }

    public function test_members_only_see_role_based_staff_academies(): void
    {
        $member = User::factory()->create(['role' => \App\Enums\UserRole::Member]);
        $helperPath = LearningPath::where('target_role', 'helper')->where('is_published', true)->firstOrFail();

        $this->actingAs($member)->get(route('academy.index'))
            ->assertOk()
            ->assertDontSee('Lid Opleiding')
            ->assertSee('Vergrendeld voor jouw rol');
        $this->assertDatabaseMissing('learning_paths', ['target_role' => 'lid', 'is_published' => true]);
        $this->actingAs($member)->get(route('academy.path', $helperPath))->assertForbidden();
    }

    public function test_staff_academies_follow_the_exact_discord_role(): void
    {
        $helper = User::factory()->create(['role' => \App\Enums\UserRole::Helper]);
        $helperPath = LearningPath::where('target_role', 'helper')->where('is_published', true)->firstOrFail();
        $moderatorPath = LearningPath::where('target_role', 'moderator')->where('is_published', true)->firstOrFail();

        $this->actingAs($helper)->get(route('academy.path', $helperPath))->assertOk();
        $this->actingAs($helper)->get(route('academy.path', $moderatorPath))->assertForbidden();
    }

    public function test_management_can_assign_a_staff_academy_to_another_user(): void
    {
        $owner = User::factory()->create(['role' => \App\Enums\UserRole::Owner]);
        $member = User::factory()->create(['role' => \App\Enums\UserRole::Member]);
        $helperPath = LearningPath::where('target_role', 'helper')->where('is_published', true)->firstOrFail();

        $this->actingAs($owner)->post(route('staff.academy.enroll'), [
            'learning_path_id' => $helperPath->id,
            'user_id' => $member->id,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('academy_enrollments', [
            'learning_path_id' => $helperPath->id,
            'user_id' => $member->id,
            'status' => 'active',
        ]);
        $this->actingAs($member)->get(route('academy.path', $helperPath))->assertOk();
    }

    public function test_academy_2026_contains_fifty_lessons_five_quizzes_assignments_and_an_exam_per_path(): void
    {
        $paths = LearningPath::where('is_published', true)
            ->whereIn('target_role', ['helper', 'moderator', 'admin', 'management'])
            ->get();
        $this->assertCount(4, $paths);

        foreach ($paths as $path) {
            $this->assertSame(50, $path->lessons()->where('type', 'lesson')->count(), $path->name);
            $this->assertSame(5, $path->lessons()->where('type', 'quiz')->count(), $path->name);
            $this->assertSame(5, $path->lessons()->where('type', 'assignment')->count(), $path->name);
            $this->assertSame(1, $path->lessons()->where('type', 'exam')->count(), $path->name);
        }
    }

    public function test_discord_avatar_hash_is_converted_to_a_cdn_url(): void
    {
        $user = User::factory()->make([
            'discord_id' => '123456789',
            'discord_avatar' => 'avatarhash',
        ]);

        $this->assertSame(
            'https://cdn.discordapp.com/avatars/123456789/avatarhash.png?size=256',
            $user->discord_avatar_url
        );
    }

    public function test_staff_absence_is_shown_on_the_public_staff_page(): void
    {
        $staff = User::factory()->create([
            'name' => 'Jesse',
            'role' => \App\Enums\UserRole::Helper,
            'discord_id' => '123456789',
            'discord_avatar' => 'avatarhash',
        ]);

        $this->actingAs($staff)->post(route('mijncn.absences.store'), [
            'starts_at' => now()->subMinute()->format('Y-m-d H:i:s'),
            'ends_at' => now()->addDays(2)->format('Y-m-d H:i:s'),
            'reason' => 'Tijdelijk niet aanwezig.',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('absence_requests', [
            'user_id' => $staff->id,
            'status' => 'approved',
        ]);

        $this->get(route('staff'))
            ->assertOk()
            ->assertSee('Niet beschikbaar')
            ->assertSee('Helper')
            ->assertSee('https://cdn.discordapp.com/avatars/123456789/avatarhash.png?size=256', false);
    }

    public function test_public_pages_keep_working_before_absence_time_migration_is_applied(): void
    {
        Schema::table('absence_requests', function (Blueprint $table) {
            $table->dropIndex(['starts_at']);
            $table->dropIndex(['ends_at']);
        });
        Schema::table('absence_requests', function (Blueprint $table) {
            $table->dropColumn(['starts_at', 'ends_at']);
        });
        User::factory()->create(['role' => \App\Enums\UserRole::Helper]);

        $this->get(route('home'))->assertOk();
        $this->get(route('staff'))->assertOk();
    }

    public function test_members_cannot_report_staff_absence(): void
    {
        $member = User::factory()->create(['role' => \App\Enums\UserRole::Member]);

        $this->actingAs($member)->post(route('mijncn.absences.store'), [
            'starts_at' => now()->format('Y-m-d H:i:s'),
            'ends_at' => now()->addHour()->format('Y-m-d H:i:s'),
            'reason' => 'Niet van toepassing.',
        ])->assertForbidden();
    }

    public function test_future_staff_absence_only_becomes_public_at_its_start_time(): void
    {
        $staff = User::factory()->create([
            'name' => 'Toekomstige Afwezigheid',
            'role' => \App\Enums\UserRole::Helper,
        ]);

        $this->actingAs($staff)->post(route('mijncn.absences.store'), [
            'starts_at' => now()->addHours(2)->format('Y-m-d H:i:s'),
            'ends_at' => now()->addHours(4)->format('Y-m-d H:i:s'),
            'reason' => 'Later vandaag niet aanwezig.',
        ])->assertSessionHasNoErrors();

        $this->assertFalse($staff->fresh()->isCurrentlyAbsent());
        $this->get(route('staff'))
            ->assertOk()
            ->assertSee('Toekomstige Afwezigheid')
            ->assertDontSee('Niet beschikbaar');
    }

    public function test_public_staff_is_sorted_by_platform_rank(): void
    {
        User::factory()->create(['name' => 'Helper Persoon', 'role' => \App\Enums\UserRole::Helper]);
        User::factory()->create(['name' => 'Eigenaar Persoon', 'role' => \App\Enums\UserRole::Owner]);
        User::factory()->create(['name' => 'Moderator Persoon', 'role' => \App\Enums\UserRole::Moderator]);
        User::factory()->create(['name' => 'Management Persoon', 'role' => \App\Enums\UserRole::Management]);
        User::factory()->create(['name' => 'Admin Persoon', 'role' => \App\Enums\UserRole::Admin]);

        $this->get(route('staff'))->assertOk()->assertSeeInOrder([
            'Eigenaar Persoon',
            'Management Persoon',
            'Admin Persoon',
            'Moderator Persoon',
            'Helper Persoon',
        ]);
    }

    public function test_authenticated_user_can_log_out(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('logout'))
            ->assertRedirect(route('home'));

        $this->assertGuest();
    }

    public function test_legacy_database_data_is_upgraded_in_place(): void
    {
        Schema::create('legacy_users', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->string('discord_id');
            $table->string('email')->nullable();
            $table->string('password_hash')->nullable();
            $table->string('auth_provider')->default('discord');
            $table->string('username');
            $table->string('avatar')->nullable();
            $table->text('profile_bio')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('birthday_visibility')->default('mijncn');
            $table->boolean('birthday_notify')->default(true);
            $table->string('staff_title')->nullable();
            $table->string('role')->default('lid');
            $table->timestamps();
        });
        Schema::create('categories', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->string('icon');
            $table->string('name');
            $table->text('description');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
        });
        Schema::create('submissions', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->unsignedInteger('user_id');
            $table->string('title');
            $table->text('description');
            $table->string('status')->default('pending');
            $table->timestamps();
        });
        Schema::create('submission_categories', function (Blueprint $table) {
            $table->unsignedInteger('submission_id');
            $table->unsignedInteger('category_id');
        });
        Schema::create('cn_absences', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id')->nullable();
            $table->string('reason');
            $table->text('note')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('status');
            $table->unsignedInteger('reviewed_by')->nullable();
            $table->timestamps();
        });

        DB::table('legacy_users')->insert([
            'id' => 77,
            'discord_id' => 'legacy-discord',
            'username' => 'Legacy Jesse',
            'staff_title' => 'Community Eigenaar',
            'role' => 'owner',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('categories')->insert([
            'id' => 9,
            'icon' => 'award',
            'name' => 'Beste Community',
            'description' => 'Oude categorie',
            'sort_order' => 10,
            'is_active' => true,
        ]);
        DB::table('submissions')->insert([
            'id' => 15,
            'user_id' => 77,
            'title' => 'Legacy Community',
            'description' => 'Oude nominatie',
            'status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('submission_categories')->insert(['submission_id' => 15, 'category_id' => 9]);
        DB::table('cn_absences')->insert([
            'user_id' => 77,
            'reason' => 'Vakantie',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(\App\Services\LegacyDatabaseUpgradeService::class)->run();

        $this->assertDatabaseHas('users', ['id' => 77, 'discord_id' => 'legacy-discord', 'role' => 'owner']);
        $this->assertDatabaseHas('staff_profiles', ['user_id' => 77, 'position' => 'Community Eigenaar', 'status' => 'absent']);
        $this->assertDatabaseHas('nominations', ['id' => 15, 'nominee_name' => 'Legacy Community', 'status' => 'approved']);
        $this->assertDatabaseHas('absence_requests', ['user_id' => 77, 'reason' => 'Vakantie', 'status' => 'approved']);
        $this->get(route('awards'))->assertOk()->assertSee('Legacy Community');
    }

    public function test_staff_member_can_claim_an_available_task(): void
    {
        $user = User::factory()->create(['role' => \App\Enums\UserRole::Helper]);
        $boardId = \Illuminate\Support\Facades\DB::table('boards')->insertGetId([
            'name' => 'Test', 'slug' => 'test', 'is_private' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $task = Task::create([
            'board_id' => $boardId,
            'creator_id' => $user->id,
            'title' => 'Testtaak',
            'status' => 'open',
            'required_role' => 'helper',
        ]);

        app(TaskWorkflowService::class)->claim($task, $user);

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'claimed_by' => $user->id, 'status' => 'in_progress']);
        $this->assertDatabaseHas('task_logs', ['task_id' => $task->id, 'action' => 'claimed']);
    }

    public function test_discord_owner_role_becomes_platform_owner(): void
    {
        config(['services.discord.roles.owner' => 'owner-role-id']);

        $role = app(DiscordService::class)->platformRole(['roles' => ['member-role-id', 'owner-role-id']]);

        $this->assertSame(\App\Enums\UserRole::Owner, $role);
    }

    public function test_published_news_has_a_public_detail_page(): void
    {
        $article = Content::create([
            'type' => 'news',
            'title' => 'Academy krijgt echte examens',
            'slug' => 'academy-krijgt-echte-examens',
            'excerpt' => 'Een duidelijke update over de vernieuwde Academy.',
            'body' => '<p>Dit artikel bevat voldoende inhoud voor een echte CN Community-publicatie.</p>',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $this->get(route('nieuws'))->assertOk()->assertSee($article->title);
        $this->get(route('news.show', $article))->assertOk()->assertSee($article->title);
    }

    public function test_content_editor_can_create_news_but_member_cannot(): void
    {
        $permission = Permission::firstOrCreate(
            ['name' => 'content.manage'],
            ['label' => 'Nieuws beheren', 'group' => 'Content']
        );
        $editor = User::factory()->create(['role' => \App\Enums\UserRole::Helper]);
        $editor->permissions()->sync([
            Permission::firstOrCreate(['name' => 'staff.access'], ['label' => 'Staff', 'group' => 'Staff'])->id,
            $permission->id,
        ]);
        $this->assertTrue($editor->hasPermission('staff.access'));
        $this->assertTrue($editor->hasPermission('content.manage'));
        $member = User::factory()->create();

        $this->actingAs($member)->get(route('staff.news.index'))->assertForbidden();
        $response = $this->actingAs($editor)->post(route('staff.news.store'), [
            'title' => 'Nieuws door de redactie',
            'excerpt' => 'Een samenvatting die duidelijk maakt waar dit nieuws over gaat.',
            'body' => str_repeat('Dit is inhoud voor het nieuwsbericht. ', 4),
            'status' => 'published',
        ]);
        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('contents', ['slug' => 'nieuws-door-de-redactie', 'status' => 'published']);
    }

    public function test_owner_can_lock_custom_role_permissions(): void
    {
        $owner = User::factory()->create(['role' => \App\Enums\UserRole::Owner]);
        $member = User::factory()->create();
        $permission = Permission::firstOrCreate(
            ['name' => 'content.manage'],
            ['label' => 'Nieuws beheren', 'group' => 'Content']
        );

        $this->actingAs($owner)->put(route('staff.access.update', $member), [
            'role' => 'helper',
            'permissions' => [$permission->id],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', ['id' => $member->id, 'role' => 'helper', 'permissions_locked' => true]);
        $this->assertTrue($member->fresh()->permissions->contains($permission));
    }

    public function test_academy_question_banks_are_large_unique_and_role_specific(): void
    {
        $helper = LearningPath::where('target_role', 'helper')->where('is_published', true)->firstOrFail();
        $moderator = LearningPath::where('target_role', 'moderator')->where('is_published', true)->firstOrFail();

        $this->assertGreaterThanOrEqual(450, QuestionBank::where('course_id', $helper->id)->count());
        $this->assertSame(
            QuestionBank::where('course_id', $helper->id)->count(),
            QuestionBank::where('course_id', $helper->id)->distinct()->count('question_hash')
        );
        $this->assertNotSame(
            QuestionBank::where('course_id', $helper->id)->value('question'),
            QuestionBank::where('course_id', $moderator->id)->value('question')
        );
    }

    public function test_academy_attempt_requires_all_answers_and_saves_feedback_data(): void
    {
        $helper = User::factory()->create(['role' => \App\Enums\UserRole::Helper]);
        $path = LearningPath::where('target_role', 'helper')->where('is_published', true)->firstOrFail();
        $lesson = $path->lessons()->where('type', 'lesson')->firstOrFail();

        $this->actingAs($helper)->get(route('academy.lesson', $lesson))->assertOk();
        $attempt = session('academy_attempt_'.$lesson->id);
        $questions = QuestionBank::whereIn('id', $attempt['question_ids'])->get();

        $this->actingAs($helper)->post(route('academy.lesson.complete', $lesson), [
            'answers' => [$questions->first()->id => $questions->first()->correct_answer],
        ])->assertSessionHasErrors('answers');

        $this->actingAs($helper)->get(route('academy.lesson', $lesson))->assertOk();
        $attempt = session('academy_attempt_'.$lesson->id);
        $questions = QuestionBank::whereIn('id', $attempt['question_ids'])->get();
        $answers = $questions->mapWithKeys(fn ($question) => [$question->id => $question->correct_answer])->all();

        $this->actingAs($helper)->post(route('academy.lesson.complete', $lesson), [
            'answers' => $answers,
            'tab_switches' => 1,
        ])->assertRedirect(route('academy.attempt.result', $attempt['attempt_id']));

        $this->assertDatabaseHas('quiz_attempts', ['id' => $attempt['attempt_id'], 'passed' => true, 'score' => 100]);
        $this->assertDatabaseCount('quiz_attempt_answers', 5);
    }

    public function test_staff_messenger_is_role_protected_and_creates_default_channels(): void
    {
        $member = User::factory()->create(['role' => \App\Enums\UserRole::Member]);
        $helper = User::factory()->create(['role' => \App\Enums\UserRole::Helper]);
        $management = User::factory()->create(['role' => \App\Enums\UserRole::Management]);

        $this->actingAs($member)->get(route('mijncn.chat'))->assertForbidden();

        $this->actingAs($helper)->get(route('mijncn.chat'))
            ->assertOk()
            ->assertSee('Staff Chat')
            ->assertDontSee('Management Chat');

        $this->actingAs($management)->get(route('mijncn.chat'))
            ->assertOk()
            ->assertSee('Staff Chat')
            ->assertSee('Management Chat');

        $this->assertDatabaseHas('chat_conversations', ['type' => 'staff', 'name' => 'Staff Chat']);
        $this->assertDatabaseHas('chat_conversations', ['type' => 'management', 'name' => 'Management Chat']);
    }

    public function test_staff_can_send_incrementally_read_edit_and_delete_direct_messages(): void
    {
        $sender = User::factory()->create(['role' => \App\Enums\UserRole::Helper]);
        $recipient = User::factory()->create(['role' => \App\Enums\UserRole::Moderator]);
        $outsider = User::factory()->create(['role' => \App\Enums\UserRole::Admin]);

        $this->actingAs($sender)->post(route('mijncn.chat.start'), [
            'user_id' => $recipient->id,
        ])->assertRedirect();

        $conversation = ChatConversation::where('type', 'direct')->firstOrFail();
        $response = $this->actingAs($sender)->postJson(route('chat.api.send'), [
            'conversation_id' => $conversation->id,
            'body' => 'Eerste intern bericht',
        ])->assertCreated();
        $messageId = $response->json('message.id');

        $this->actingAs($recipient)->getJson(route('chat.api.messages', [
            'conversation_id' => $conversation->id,
            'after' => 0,
        ]))->assertOk()
            ->assertJsonCount(1, 'messages')
            ->assertJsonPath('messages.0.body', 'Eerste intern bericht');
        $this->assertDatabaseHas('chat_message_reads', [
            'message_id' => $messageId,
            'user_id' => $recipient->id,
        ]);

        $this->actingAs($recipient)->getJson(route('chat.api.messages', [
            'conversation_id' => $conversation->id,
            'after' => $messageId,
        ]))->assertOk()->assertJsonCount(0, 'messages');

        $this->actingAs($outsider)->getJson(route('chat.api.messages', [
            'conversation_id' => $conversation->id,
        ]))->assertForbidden();

        $this->actingAs($sender)->patchJson(route('chat.api.messages.update', $messageId), [
            'body' => 'Bijgewerkt intern bericht',
        ])->assertOk()->assertJsonPath('message.edited', true);

        $this->assertDatabaseHas('chat_messages', [
            'id' => $messageId,
            'body' => 'Bijgewerkt intern bericht',
        ]);

        $this->actingAs($sender)
            ->deleteJson(route('chat.api.messages.delete', $messageId))
            ->assertOk();

        $this->assertNotNull(ChatMessage::findOrFail($messageId)->deleted_at);
    }

    public function test_staff_messenger_accepts_a_valid_image_without_text(): void
    {
        Storage::fake('public');
        $sender = User::factory()->create(['role' => \App\Enums\UserRole::Helper]);
        $recipient = User::factory()->create(['role' => \App\Enums\UserRole::Moderator]);
        $conversation = ChatConversation::create([
            'type' => 'direct',
            'created_by' => $sender->id,
        ]);
        $conversation->participants()->attach([$sender->id, $recipient->id]);
        $image = UploadedFile::fake()->createWithContent(
            'screen.png',
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=')
        );

        $this->actingAs($sender)->post(route('chat.api.send'), [
            'conversation_id' => $conversation->id,
            'image' => $image,
        ], ['Accept' => 'application/json'])->assertCreated()->assertJsonCount(1, 'message.attachments');

        $attachment = DB::table('chat_message_attachments')->first();
        $this->assertNotNull($attachment);
        Storage::disk('public')->assertExists($attachment->path);
    }

    public function test_staff_messenger_keeps_the_composer_available_with_a_long_chat_and_attachment(): void
    {
        Storage::fake('public');
        $sender = User::factory()->create(['role' => \App\Enums\UserRole::Helper]);
        $recipient = User::factory()->create(['role' => \App\Enums\UserRole::Moderator]);
        $conversation = ChatConversation::create([
            'type' => 'direct',
            'created_by' => $sender->id,
        ]);
        $conversation->participants()->attach([$sender->id, $recipient->id]);

        foreach (range(1, 40) as $number) {
            ChatMessage::create([
                'conversation_id' => $conversation->id,
                'sender_id' => $number % 2 ? $sender->id : $recipient->id,
                'body' => 'Testbericht '.$number,
            ]);
        }

        $lastMessage = $conversation->messages()->latest('id')->firstOrFail();
        $lastMessage->attachments()->create([
            'disk' => 'public',
            'path' => 'chat/test/voorbeeld.png',
            'original_name' => 'voorbeeld.png',
            'mime_type' => 'image/png',
            'size' => 1024,
        ]);

        $this->actingAs($sender)
            ->get(route('mijncn.chat', ['gesprek' => $conversation->id]))
            ->assertOk()
            ->assertSee('data-chat-messages', false)
            ->assertSee('data-chat-form', false)
            ->assertSee('data-chat-file', false)
            ->assertSee('voorbeeld.png');
    }

    public function test_group_admin_can_manage_and_use_advanced_message_actions(): void
    {
        $owner = User::factory()->create(['role' => \App\Enums\UserRole::Owner]);
        $helper = User::factory()->create(['role' => \App\Enums\UserRole::Helper]);
        $conversation = ChatConversation::create([
            'name' => 'Projectgroep',
            'type' => 'staff',
            'created_by' => $owner->id,
        ]);
        $conversation->participants()->attach($owner->id, ['is_admin' => true]);
        $conversation->participants()->attach($helper->id);

        $original = ChatMessage::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $helper->id,
            'body' => 'Kunnen we dit morgen uitvoeren?',
        ]);
        $reply = $this->actingAs($owner)->postJson(route('chat.api.send'), [
            'conversation_id' => $conversation->id,
            'reply_to_id' => $original->id,
            'body' => 'Ja, ik zet dit direct vast.',
            'is_announcement' => true,
            'requires_ack' => true,
        ])->assertCreated()
            ->assertJsonPath('message.reply.id', $original->id)
            ->assertJsonPath('message.announcement', true)
            ->assertJsonPath('message.requires_ack', true);

        $messageId = $reply->json('message.id');
        $this->actingAs($owner)
            ->postJson(route('chat.api.messages.pin', $messageId))
            ->assertOk()
            ->assertJsonPath('pinned', true);
        $this->actingAs($helper)
            ->postJson(route('chat.api.messages.acknowledge', $messageId))
            ->assertOk();
        $this->actingAs($owner)
            ->postJson(route('chat.api.messages.task', $messageId))
            ->assertCreated();
        $this->actingAs($owner)
            ->getJson(route('chat.api.search', [
                'conversation_id' => $conversation->id,
                'q' => 'morgen',
            ]))
            ->assertOk()
            ->assertJsonPath('messages.0.id', $original->id);

        $this->actingAs($owner)->put(route('mijncn.chat.groups.update', $conversation), [
            'name' => 'Projectgroep vernieuwd',
            'retention_days' => 90,
            'user_ids' => [$owner->id, $helper->id],
            'admin_ids' => [$owner->id],
        ])->assertRedirect(route('mijncn.chat', ['gesprek' => $conversation->id]));

        $this->assertDatabaseHas('chat_conversations', [
            'id' => $conversation->id,
            'name' => 'Projectgroep vernieuwd',
            'retention_days' => 90,
        ]);
        $this->assertDatabaseHas('chat_message_acknowledgements', [
            'message_id' => $messageId,
            'user_id' => $helper->id,
        ]);
        $this->assertNotNull(ChatMessage::findOrFail($messageId)->task_id);
    }

    public function test_only_owner_can_run_the_messenger_installer(): void
    {
        $helper = User::factory()->create(['role' => \App\Enums\UserRole::Helper]);
        $owner = User::factory()->create(['role' => \App\Enums\UserRole::Owner]);

        $this->actingAs($helper)->post(route('mijncn.chat.install'))->assertForbidden();
        $this->actingAs($owner)->post(route('mijncn.chat.install'))
            ->assertRedirect(route('mijncn.chat'));
    }

    public function test_member_can_manage_their_nomination_profile_with_safe_markdown(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create(['discord_id' => 'nomination-owner']);
        $edition = AwardEdition::create([
            'name' => 'CN Awards 2026',
            'slug' => 'profile-editor-awards',
            'type' => 'cn_awards',
            'year' => 2026,
        ]);
        $category = AwardCategory::create([
            'award_edition_id' => $edition->id,
            'name' => 'Beste Discord Community',
            'slug' => 'beste-discord-community-profile',
        ]);
        $nomination = Nomination::create([
            'award_category_id' => $category->id,
            'user_id' => $owner->id,
            'nominee_name' => 'Oude Naam',
            'motivation' => 'Een uitgebreide motivatie voor de oude naam.',
            'status' => 'approved',
        ]);

        $this->actingAs($owner)->put(route('mijncn.nominations.update', $nomination), [
            'nominee_name' => 'Maatjescraft',
            'motivation' => "# Welkom\n**Sterke community** met duidelijke events. <script>alert(1)</script>",
            'evidence_text' => '- Actieve chats',
            'website_url' => 'https://maatjescraft.example',
            'discord_invite' => 'https://discord.gg/test',
            'logo_upload' => UploadedFile::fake()->createWithContent(
                'logo.png',
                base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=')
            ),
            'banner_upload' => UploadedFile::fake()->createWithContent(
                'banner.png',
                base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=')
            ),
        ])->assertRedirect(route('mijncn.nominations.edit', $nomination));

        $nomination->refresh();
        $this->assertSame('Maatjescraft', $nomination->nominee_name);
        $this->assertNotNull($nomination->logo_url);
        $this->assertNotNull($nomination->banner_url);

        $this->actingAs($owner)
            ->get(route('awards.nomination', $nomination))
            ->assertOk()
            ->assertSee('<h2>Welkom</h2>', false)
            ->assertSee('<strong>Sterke community</strong>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_member_cannot_manage_someone_elses_nomination_profile(): void
    {
        $owner = User::factory()->create(['discord_id' => 'nomination-owner-two']);
        $other = User::factory()->create(['discord_id' => 'nomination-other']);
        $edition = AwardEdition::create([
            'name' => 'CN Awards 2026',
            'slug' => 'profile-editor-awards-forbidden',
            'type' => 'cn_awards',
            'year' => 2026,
        ]);
        $category = AwardCategory::create([
            'award_edition_id' => $edition->id,
            'name' => 'Beste Project',
            'slug' => 'beste-project-profile',
        ]);
        $nomination = Nomination::create([
            'award_category_id' => $category->id,
            'user_id' => $owner->id,
            'nominee_name' => 'Project',
            'motivation' => 'Een uitgebreide motivatie voor dit project.',
            'status' => 'pending',
        ]);

        $this->actingAs($other)
            ->get(route('mijncn.nominations.edit', $nomination))
            ->assertForbidden();
    }
}
