<?php

namespace Tests\Feature\Web;

use App\Application\Services\ConfigurationService;
use App\Application\Services\OrganizationService;
use App\Application\Services\ProjectService;
use App\Infrastructure\Persistence\Models\User;
use App\Modules\Acquisition\Infrastructure\Persistence\Models\Source;
use App\Modules\Announcement\Infrastructure\Persistence\Models\Announcement;
use App\Modules\Intelligence\Application\ContentIntelligencePlanner;
use App\Support\OperatorContext;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class ContentIntelligencePlanFeatureTest extends TestCase
{
    public function test_settings_page_renders_content_intelligence_profile_field(): void
    {
        $ctx = $this->operatorContext();

        $this->actingAs($ctx['user'])->withSession($this->sessionFor($ctx))
            ->get(route('app.settings.edit'))
            ->assertOk()
            ->assertSee('Content Intelligence', false)
            ->assertSee('Project-specific deterministic entity, Hub/Satellite and SEO planning rules.', false)
            ->assertSee('name="profile_json"', false)
            ->assertSee('Save Content Intelligence profile', false);
    }

    public function test_valid_profile_saves_project_scoped_config(): void
    {
        $ctx = $this->operatorContext();
        $profile = $this->sampleProfileJson();

        $this->actingAs($ctx['user'])->withSession($this->sessionFor($ctx))
            ->post(route('app.settings.content-intelligence'), ['profile_json' => $profile])
            ->assertRedirect()
            ->assertSessionHas('status', 'Content Intelligence profile saved.');

        $entry = app(ConfigurationService::class)->get(
            $ctx['organization']->id,
            ContentIntelligencePlanner::PROFILE_KEY,
            $ctx['project']->id,
        );

        $this->assertNotNull($entry);
        $this->assertSame(1, $entry->value['value']['version']);
        $this->assertSame('plan_only', $entry->value['value']['publishing_mode']);
    }

    public function test_malformed_json_is_rejected(): void
    {
        $ctx = $this->operatorContext();

        $this->actingAs($ctx['user'])->withSession($this->sessionFor($ctx))
            ->post(route('app.settings.content-intelligence'), ['profile_json' => '{not json'])
            ->assertRedirect()
            ->assertSessionHasErrors('profile_json');
    }

    public function test_publishing_mode_other_than_plan_only_is_rejected(): void
    {
        $ctx = $this->operatorContext();
        $profile = json_decode($this->sampleProfileJson(), true);
        $profile['publishing_mode'] = 'draft';

        $this->actingAs($ctx['user'])->withSession($this->sessionFor($ctx))
            ->post(route('app.settings.content-intelligence'), ['profile_json' => json_encode($profile)])
            ->assertRedirect()
            ->assertSessionHasErrors('profile_json');
    }

    public function test_announcement_detail_displays_resolved_plan(): void
    {
        $ctx = $this->operatorContext();
        $this->saveProfile($ctx, json_decode($this->sampleProfileJson(), true));
        $announcement = $this->makeAnnouncement($ctx, 'ΑΣΕΠ 6Κ/2026');

        $this->actingAs($ctx['user'])->withSession($this->sessionFor($ctx))
            ->get(route('app.announcements.show', $announcement))
            ->assertOk()
            ->assertSee('Content Intelligence Plan', false)
            ->assertSee('asep-6k-2026', false)
            ->assertSee('satellite', false)
            ->assertSee('update_existing', false)
            ->assertSee('Search intent', false)
            ->assertSee('SEO title', false)
            ->assertSee('plan_only', false);
    }

    public function test_unresolved_plan_does_not_break_announcement_page(): void
    {
        $ctx = $this->operatorContext();
        $announcement = $this->makeAnnouncement($ctx, 'Unrelated announcement');

        $this->actingAs($ctx['user'])->withSession($this->sessionFor($ctx))
            ->get(route('app.announcements.show', $announcement))
            ->assertOk()
            ->assertSee('Content Intelligence Plan', false)
            ->assertSee('No entity resolved', false)
            ->assertSee('unresolved', false);
    }

    public function test_existing_timeline_and_generation_ui_still_render(): void
    {
        $ctx = $this->operatorContext();
        $announcement = $this->makeAnnouncement($ctx, 'Timeline check');

        $this->actingAs($ctx['user'])->withSession($this->sessionFor($ctx))
            ->get(route('app.announcements.show', $announcement))
            ->assertOk()
            ->assertSee('Timeline', false)
            ->assertSee('Revision history', false)
            ->assertSee('First seen', false)
            ->assertSee('No generation history yet', false);
    }

    private function sampleProfileJson(): string
    {
        return json_encode([
            'version' => 1,
            'publishing_mode' => 'plan_only',
            'primary_domain' => 'studymentor.gr',
            'entity_rules' => [
                [
                    'entity_id' => 'asep-6k-2026',
                    'label' => 'ΑΣΕΠ 6Κ/2026',
                    'patterns' => ['6\\s*[ΚK]\\s*\\/\\s*2026'],
                    'match_scope' => 'title',
                    'content_role' => 'satellite',
                    'canonical_target_url' => 'https://example.test/asep-6k-2026',
                    'parent_hub' => [
                        'entity_id' => 'asep-2026',
                        'label' => 'ΑΣΕΠ 2026',
                        'url' => 'https://example.test/asep-2026',
                    ],
                    'seo' => [
                        'search_intent' => 'Specific ASEP 6K/2026 information',
                        'slug' => 'asep-6k-2026',
                        'seo_title_template' => 'ΑΣΕΠ 6Κ/2026: {announcement_title}',
                        'h1_template' => '{announcement_title}',
                        'meta_description_template' => 'Ενημέρωση για την ΑΣΕΠ 6Κ/2026.',
                    ],
                ],
            ],
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return array{user: User, organization: mixed, project: mixed}
     */
    private function operatorContext(): array
    {
        $user = User::factory()->create(['password' => Hash::make('password-secret')]);
        $organization = app(OrganizationService::class)->create($user, 'CI Feature Org');
        $project = app(ProjectService::class)->create($organization, $user, 'CI Feature Project');

        return compact('user', 'organization', 'project');
    }

    /**
     * @param  array{organization: mixed, project: mixed}  $ctx
     * @return array<string, string>
     */
    private function sessionFor(array $ctx): array
    {
        return [
            OperatorContext::SESSION_ORG => $ctx['organization']->id,
            OperatorContext::SESSION_PROJECT => $ctx['project']->id,
        ];
    }

    /**
     * @param  array{user: User, organization: mixed, project: mixed}  $ctx
     * @param  array<string, mixed>  $profile
     */
    private function saveProfile(array $ctx, array $profile): void
    {
        app(ConfigurationService::class)->set(
            $ctx['organization']->id,
            ContentIntelligencePlanner::PROFILE_KEY,
            ['value' => $profile],
            $ctx['project']->id,
            $ctx['user'],
        );
    }

    /**
     * @param  array{user: User, organization: mixed, project: mixed}  $ctx
     */
    private function makeAnnouncement(array $ctx, string $title): Announcement
    {
        $feedUrl = 'https://example.com/feed.xml';
        $source = Source::create([
            'organization_id' => $ctx['organization']->id,
            'project_id' => $ctx['project']->id,
            'slug' => 'feat-src-'.Str::lower(Str::random(6)),
            'name' => 'Feature Source',
            'source_type' => 'rss',
            'base_url' => 'https://example.com',
            'feed_url' => $feedUrl,
            'feed_url_hash' => hash('sha256', $feedUrl),
            'allowed_domains' => ['example.com'],
            'enabled' => true,
            'manual_only' => true,
            'acquire_interval_seconds' => 3600,
        ]);

        return Announcement::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $ctx['organization']->id,
            'project_id' => $ctx['project']->id,
            'source_id' => $source->id,
            'identity_hash' => hash('sha256', uniqid('id', true)),
            'identity_basis' => 'canonical_url',
            'canonical_url' => 'https://example.com/'.Str::lower(Str::random(8)),
            'raw_title' => $title,
            'content_hash' => hash('sha256', $title),
            'raw_payload' => ['title' => $title],
            'revision_no' => 1,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);
    }
}
