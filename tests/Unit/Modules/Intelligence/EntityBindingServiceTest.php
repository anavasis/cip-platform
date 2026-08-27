<?php

namespace Tests\Unit\Modules\Intelligence;

use App\Application\Services\ConfigurationService;
use App\Application\Services\OrganizationService;
use App\Application\Services\ProjectService;
use App\Infrastructure\Persistence\Models\User;
use App\Modules\Acquisition\Infrastructure\Persistence\Models\Source;
use App\Modules\Announcement\Infrastructure\Persistence\Models\Announcement;
use App\Modules\Intelligence\Application\EntityBindingService;
use App\Modules\Intelligence\Infrastructure\Persistence\Models\ContentEntityModel;
use App\Modules\Intelligence\Infrastructure\Persistence\Models\EntityAnnouncementBindingModel;
use App\Modules\Intelligence\Infrastructure\Persistence\Models\RemotePostBindingModel;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class EntityBindingServiceTest extends TestCase
{
    private EntityBindingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EntityBindingService::class);
    }

    public function test_unresolved_plan_writes_zero_entities(): void
    {
        $ctx = $this->tenantContext();
        $announcement = $this->makeAnnouncement('No profile title', $ctx);

        $result = $this->service->bindAnnouncement($announcement);

        $this->assertFalse($result['bound']);
        $this->assertSame(0, ContentEntityModel::query()->count());
    }

    public function test_ambiguous_plan_writes_zero_entities(): void
    {
        $profile = $this->satelliteProfile();
        $profile['entity_rules'][] = [
            'entity_id' => 'entity-b',
            'label' => 'Entity B',
            'patterns' => ['6\\s*[ΚK]\\s*\\/\\s*2026'],
            'content_role' => 'satellite',
            'canonical_target_url' => 'https://example.test/entity-b',
        ];
        $ctx = $this->seedProfile($profile);
        $announcement = $this->makeAnnouncement('ΑΣΕΠ 6Κ/2026', $ctx);

        $result = $this->service->bindAnnouncement($announcement);

        $this->assertFalse($result['bound']);
        $this->assertSame(0, ContentEntityModel::query()->count());
    }

    public function test_no_publish_writes_zero_entities(): void
    {
        $profile = $this->satelliteProfile();
        $profile['entity_rules'][0]['publish'] = false;
        $ctx = $this->seedProfile($profile);
        $announcement = $this->makeAnnouncement('ΑΣΕΠ 6Κ/2026', $ctx);

        $result = $this->service->bindAnnouncement($announcement);

        $this->assertFalse($result['bound']);
        $this->assertSame('no_publish', $result['reason']);
        $this->assertSame(0, ContentEntityModel::query()->count());
    }

    public function test_one_resolved_announcement_creates_one_content_entity(): void
    {
        $ctx = $this->seedProfile($this->satelliteProfile());
        $announcement = $this->makeAnnouncement('ΑΣΕΠ 6Κ/2026', $ctx);

        $result = $this->service->bindAnnouncement($announcement);

        $this->assertTrue($result['bound']);
        $this->assertSame(1, ContentEntityModel::query()->count());
        $this->assertDatabaseHas('content_entities', [
            'project_id' => $ctx['project']->id,
            'entity_id' => 'entity-a-2026',
        ]);
    }

    public function test_body_only_match_does_not_persist_entity_or_bindings(): void
    {
        $profile = $this->satelliteProfile();
        $profile['entity_rules'][0]['match_scope'] = 'title_and_body';
        $ctx = $this->seedProfile($profile);
        $announcement = $this->makeAnnouncement(
            'ΑΣΕΠ 2026: Hub overview',
            $ctx,
            'https://example.test/hub-body-only',
            ['description' => 'Mentions ΑΣΕΠ 6Κ/2026 in the body only.'],
        );

        $result = $this->service->bindAnnouncement($announcement);

        $this->assertFalse($result['bound']);
        $this->assertSame('primary_binding_ineligible', $result['reason']);
        $this->assertSame('entity-a-2026', $result['entity_id']);
        $this->assertNull($result['content_entity_id']);
        $this->assertSame(0, ContentEntityModel::query()->count());
        $this->assertSame(0, EntityAnnouncementBindingModel::query()->count());
        $this->assertSame(0, RemotePostBindingModel::query()->count());
    }

    public function test_genuine_title_match_persists_entity_and_binding(): void
    {
        $profile = $this->satelliteProfile();
        $profile['entity_rules'][0]['match_scope'] = 'title_and_body';
        $ctx = $this->seedProfile($profile);
        $announcement = $this->makeAnnouncement(
            'ΑΣΕΠ 6Κ/2026: Ανοιχτές αιτήσεις για 315 μόνιμες θέσεις ΠΕ και ΤΕ',
            $ctx,
            'https://example.test/genuine-6k',
            ['description' => 'Supporting details'],
        );

        $result = $this->service->bindAnnouncement($announcement);

        $this->assertTrue($result['bound']);
        $this->assertSame(1, ContentEntityModel::query()->count());
        $this->assertSame(1, EntityAnnouncementBindingModel::query()->count());
        $this->assertDatabaseHas('entity_announcement_bindings', [
            'announcement_id' => $announcement->id,
        ]);
    }

    public function test_allow_body_primary_match_opt_in_persists_binding(): void
    {
        $profile = $this->satelliteProfile();
        $profile['entity_rules'][0]['match_scope'] = 'title_and_body';
        $profile['entity_rules'][0]['allow_body_primary_match'] = true;
        $ctx = $this->seedProfile($profile);
        $announcement = $this->makeAnnouncement(
            'Unrelated hub title',
            $ctx,
            'https://example.test/body-primary-opt-in',
            ['description' => 'Mentions ΑΣΕΠ 6Κ/2026 in the body.'],
        );

        $result = $this->service->bindAnnouncement($announcement);

        $this->assertTrue($result['bound']);
        $this->assertSame(1, EntityAnnouncementBindingModel::query()->count());
    }

    public function test_two_announcements_same_entity_create_one_entity_and_two_bindings(): void
    {
        $ctx = $this->seedProfile($this->satelliteProfile());
        $announcementA = $this->makeAnnouncement('ΑΣΕΠ 6Κ/2026', $ctx, 'https://example.test/a');
        $announcementB = $this->makeAnnouncement('ΑΣΕΠ 6Κ/2026', $ctx, 'https://example.test/b');

        $this->service->bindAnnouncement($announcementA);
        $this->service->bindAnnouncement($announcementB);

        $this->assertSame(1, ContentEntityModel::query()->count());
        $this->assertSame(2, EntityAnnouncementBindingModel::query()->count());
    }

    public function test_same_announcement_rebind_does_not_duplicate_binding(): void
    {
        $ctx = $this->seedProfile($this->satelliteProfile());
        $announcement = $this->makeAnnouncement('ΑΣΕΠ 6Κ/2026', $ctx);

        $this->service->bindAnnouncement($announcement);
        $this->service->bindAnnouncement($announcement);

        $this->assertSame(1, EntityAnnouncementBindingModel::query()->count());
    }

    public function test_changed_revision_updates_existing_binding(): void
    {
        $ctx = $this->seedProfile($this->satelliteProfile());
        $announcement = $this->makeAnnouncement('ΑΣΕΠ 6Κ/2026', $ctx);
        $this->service->bindAnnouncement($announcement);

        $announcement->revision_no = 2;
        $announcement->content_hash = hash('sha256', 'updated-content');
        $announcement->save();

        $this->service->bindAnnouncement($announcement->fresh());

        $binding = EntityAnnouncementBindingModel::query()->sole();
        $this->assertSame(2, $binding->source_revision_at_bind);
        $this->assertSame(hash('sha256', 'updated-content'), $binding->content_hash_at_bind);
    }

    public function test_different_entities_remain_separate(): void
    {
        $profile = $this->satelliteProfile();
        $profile['entity_rules'][] = [
            'entity_id' => 'entity-b-2026',
            'label' => 'Entity B',
            'patterns' => ['7\\s*[ΚK]\\s*\\/\\s*2026'],
            'content_role' => 'satellite',
            'canonical_target_url' => 'https://example.test/entity-b-2026',
        ];
        $ctx = $this->seedProfile($profile);

        $this->service->bindAnnouncement($this->makeAnnouncement('ΑΣΕΠ 6Κ/2026', $ctx, 'https://example.test/six-k'));
        $this->service->bindAnnouncement($this->makeAnnouncement('ΑΣΕΠ 7Κ/2026', $ctx, 'https://example.test/seven-k'));

        $this->assertSame(2, ContentEntityModel::query()->count());
    }

    public function test_organization_isolation(): void
    {
        $ctxA = $this->seedProfile($this->satelliteProfile(), 'Org A', 'Project A');
        $userB = User::factory()->create(['password' => Hash::make('password-secret')]);
        $organizationB = app(OrganizationService::class)->create($userB, 'Org B');
        $projectB = app(ProjectService::class)->create($organizationB, $userB, 'Project B');
        $ctxB = $this->seedProfile($this->satelliteProfile(), 'Org B', 'Project B', $organizationB, $projectB);

        $this->service->bindAnnouncement($this->makeAnnouncement('ΑΣΕΠ 6Κ/2026', $ctxA));
        $this->service->bindAnnouncement($this->makeAnnouncement('ΑΣΕΠ 6Κ/2026', $ctxB));

        $this->assertSame(1, ContentEntityModel::query()->where('organization_id', $ctxA['organization']->id)->count());
        $this->assertSame(1, ContentEntityModel::query()->where('organization_id', $ctxB['organization']->id)->count());
    }

    public function test_project_isolation(): void
    {
        $ctx = $this->seedProfile($this->satelliteProfile());
        $user = $ctx['user'];
        $organization = $ctx['organization'];
        $projectB = app(ProjectService::class)->create($organization, $user, 'Project B');
        $sourceB = Source::create([
            'organization_id' => $organization->id,
            'project_id' => $projectB->id,
            'slug' => 'source-b-'.uniqid(),
            'name' => 'Source B',
            'source_type' => 'rss',
            'base_url' => 'https://example.test',
            'feed_url' => 'https://example.test/feed',
            'feed_url_hash' => hash('sha256', uniqid('', true)),
            'allowed_domains' => ['example.test'],
            'parser_profile' => 'rss_v1',
            'enabled' => true,
            'manual_only' => false,
            'acquire_interval_seconds' => 3600,
        ]);

        $this->service->bindAnnouncement($this->makeAnnouncement('ΑΣΕΠ 6Κ/2026', $ctx));
        $announcementB = Announcement::create([
            'organization_id' => $organization->id,
            'project_id' => $projectB->id,
            'source_id' => $sourceB->id,
            'identity_hash' => hash('sha256', 'https://example.test/project-b'),
            'identity_basis' => 'canonical_url',
            'canonical_url' => 'https://example.test/project-b',
            'raw_title' => 'ΑΣΕΠ 6Κ/2026',
            'content_hash' => hash('sha256', 'project-b-title'),
            'raw_payload' => ['title' => 'ΑΣΕΠ 6Κ/2026'],
            'revision_no' => 1,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);
        app(ConfigurationService::class)->set(
            $organization->id,
            'editorial.content_intelligence_profile',
            ['value' => $this->satelliteProfile()],
            $projectB->id,
        );
        $this->service->bindAnnouncement($announcementB);

        $this->assertSame(1, ContentEntityModel::query()->where('project_id', $ctx['project']->id)->count());
        $this->assertSame(1, ContentEntityModel::query()->where('project_id', $projectB->id)->count());
    }

    public function test_new_entity_defaults_to_verification_required(): void
    {
        $ctx = $this->seedProfile($this->satelliteProfile());
        $this->service->bindAnnouncement($this->makeAnnouncement('ΑΣΕΠ 6Κ/2026', $ctx));

        $entity = ContentEntityModel::query()->sole();
        $this->assertSame('verification_required', $entity->lifecycle_status);
        $this->assertSame('verification_required', $entity->verification_status);
    }

    public function test_new_entity_defaults_hub_member_false(): void
    {
        $ctx = $this->seedProfile($this->satelliteProfile());
        $this->service->bindAnnouncement($this->makeAnnouncement('ΑΣΕΠ 6Κ/2026', $ctx));

        $this->assertFalse((bool) ContentEntityModel::query()->value('hub_member'));
    }

    public function test_new_entity_defaults_publish_eligible_false(): void
    {
        $ctx = $this->seedProfile($this->satelliteProfile());
        $this->service->bindAnnouncement($this->makeAnnouncement('ΑΣΕΠ 6Κ/2026', $ctx));

        $this->assertFalse((bool) ContentEntityModel::query()->value('publish_eligible'));
    }

    public function test_canonical_url_creates_unconfirmed_remote_post_binding(): void
    {
        $ctx = $this->seedProfile($this->satelliteProfile());
        $this->service->bindAnnouncement($this->makeAnnouncement('ΑΣΕΠ 6Κ/2026', $ctx));

        $binding = RemotePostBindingModel::query()->sole();
        $this->assertSame('wordpress', $binding->remote_system);
        $this->assertSame('https://example.test/entity-a-2026', $binding->canonical_url);
        $this->assertNull($binding->remote_post_id);
        $this->assertNull($binding->confirmed_at);
    }

    public function test_no_canonical_url_creates_no_remote_post_binding(): void
    {
        $profile = $this->satelliteProfile();
        unset($profile['entity_rules'][0]['canonical_target_url']);
        $ctx = $this->seedProfile($profile);
        $this->service->bindAnnouncement($this->makeAnnouncement('ΑΣΕΠ 6Κ/2026', $ctx));

        $this->assertSame(0, RemotePostBindingModel::query()->count());
    }

    public function test_confirmed_remote_binding_is_not_overwritten_by_ingestion(): void
    {
        $ctx = $this->seedProfile($this->satelliteProfile());
        $announcement = $this->makeAnnouncement('ΑΣΕΠ 6Κ/2026', $ctx, 'https://example.test/rebind-same');
        $this->service->bindAnnouncement($announcement);

        $entity = ContentEntityModel::query()->sole();
        RemotePostBindingModel::query()->where('content_entity_id', $entity->id)->update([
            'canonical_url' => 'https://example.test/confirmed-url',
            'remote_post_id' => '99999',
            'slug' => 'confirmed-slug',
            'confirmed_at' => now(),
        ]);

        $profile = $this->satelliteProfile();
        $profile['entity_rules'][0]['canonical_target_url'] = 'https://example.test/new-plan-url';
        app(ConfigurationService::class)->set(
            $ctx['organization']->id,
            'editorial.content_intelligence_profile',
            ['value' => $profile],
            $ctx['project']->id,
        );

        $this->service->bindAnnouncement($announcement->fresh());

        $binding = RemotePostBindingModel::query()->sole();
        $this->assertSame('https://example.test/confirmed-url', $binding->canonical_url);
        $this->assertSame('99999', $binding->remote_post_id);
        $this->assertSame('confirmed-slug', $binding->slug);
    }

    public function test_existing_verified_lifecycle_fields_are_not_reset_during_re_ingestion(): void
    {
        $ctx = $this->seedProfile($this->satelliteProfile());
        $announcement = $this->makeAnnouncement('ΑΣΕΠ 6Κ/2026', $ctx, 'https://example.test/preserve-state');
        $this->service->bindAnnouncement($announcement);

        $entity = ContentEntityModel::query()->sole();
        $entity->update([
            'lifecycle_status' => 'open',
            'verification_status' => 'verified',
            'application_deadline_at' => now()->addDays(10),
            'positions_count' => 42,
            'next_step_label' => 'Apply now',
            'last_verified_at' => now()->subDay(),
            'last_changed_at' => now()->subDay(),
            'hub_display_section' => 'open_now',
            'hub_member' => true,
            'publish_eligible' => true,
            'source_family' => 'asep',
            'thematic_categories' => ['health'],
            'verified_announcement_id' => Str::uuid()->toString(),
            'verified_content_hash' => hash('sha256', 'verified'),
        ]);

        $this->service->bindAnnouncement($announcement->fresh());

        $entity->refresh();
        $this->assertSame('open', $entity->lifecycle_status);
        $this->assertSame('verified', $entity->verification_status);
        $this->assertSame(42, $entity->positions_count);
        $this->assertSame('Apply now', $entity->next_step_label);
        $this->assertTrue($entity->hub_member);
        $this->assertTrue($entity->publish_eligible);
        $this->assertSame('asep', $entity->source_family);
        $this->assertSame(['health'], $entity->thematic_categories);
    }

    /**
     * @return array<string, mixed>
     */
    private function satelliteProfile(): array
    {
        return [
            'version' => 1,
            'publishing_mode' => 'plan_only',
            'primary_domain' => 'example.test',
            'entity_rules' => [
                [
                    'entity_id' => 'entity-a-2026',
                    'label' => 'Entity A 2026',
                    'patterns' => ['6\\s*[ΚK]\\s*\\/\\s*2026'],
                    'match_scope' => 'title',
                    'content_role' => 'satellite',
                    'canonical_target_url' => 'https://example.test/entity-a-2026',
                    'seo' => ['slug' => 'entity-a-2026'],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $profile
     * @return array{user: User, organization: mixed, project: mixed, source: Source}
     */
    private function seedProfile(
        array $profile,
        string $orgName = 'Binding Org',
        string $projectName = 'Binding Project',
        mixed $organization = null,
        mixed $project = null,
    ): array {
        $ctx = $this->tenantContext($orgName, $projectName, $organization, $project);
        app(ConfigurationService::class)->set(
            $ctx['organization']->id,
            'editorial.content_intelligence_profile',
            ['value' => $profile],
            $ctx['project']->id,
        );

        return $ctx;
    }

    /**
     * @return array{user: User, organization: mixed, project: mixed, source: Source}
     */
    private function tenantContext(
        string $orgName = 'Binding Org',
        string $projectName = 'Binding Project',
        mixed $organization = null,
        mixed $project = null,
    ): array {
        $user = User::factory()->create(['password' => Hash::make('password-secret')]);
        $organization = $organization ?? app(OrganizationService::class)->create($user, $orgName);
        $project = $project ?? app(ProjectService::class)->create($organization, $user, $projectName);
        $source = Source::create([
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'slug' => 'source-'.uniqid(),
            'name' => 'Source',
            'source_type' => 'rss',
            'base_url' => 'https://example.test',
            'feed_url' => 'https://example.test/feed',
            'feed_url_hash' => hash('sha256', uniqid('', true)),
            'allowed_domains' => ['example.test'],
            'parser_profile' => 'rss_v1',
            'enabled' => true,
            'manual_only' => false,
            'acquire_interval_seconds' => 3600,
        ]);

        return compact('user', 'organization', 'project', 'source');
    }

    /**
     * @param  array{organization: mixed, project: mixed, source: Source}  $ctx
     * @param  array<string, mixed>  $rawPayload
     */
    private function makeAnnouncement(
        string $title,
        array $ctx,
        ?string $url = null,
        array $rawPayload = [],
    ): Announcement {
        $url = $url ?? ('https://example.test/item/'.uniqid('', true));

        return Announcement::create([
            'organization_id' => $ctx['organization']->id,
            'project_id' => $ctx['project']->id,
            'source_id' => $ctx['source']->id,
            'identity_hash' => hash('sha256', $url),
            'identity_basis' => 'canonical_url',
            'canonical_url' => $url,
            'raw_title' => $title,
            'content_hash' => hash('sha256', $title.'|'.$url),
            'raw_payload' => $rawPayload !== [] ? $rawPayload : ['title' => $title],
            'revision_no' => 1,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);
    }
}
