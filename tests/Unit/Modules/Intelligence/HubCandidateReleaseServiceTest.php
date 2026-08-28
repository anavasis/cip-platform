<?php

namespace Tests\Unit\Modules\Intelligence;

use App\Application\Services\OrganizationService;
use App\Application\Services\ProjectService;
use App\Infrastructure\Persistence\Models\User;
use App\Modules\Announcement\Infrastructure\Persistence\Models\Announcement;
use App\Modules\Intelligence\Application\EntityLifecycleService;
use App\Modules\Intelligence\Application\HubCandidateReleaseService;
use App\Modules\Intelligence\Application\HubPayloadBuilder;
use App\Modules\Intelligence\Infrastructure\Persistence\Models\ContentEntityModel;
use App\Modules\Intelligence\Infrastructure\Persistence\Models\EntityAnnouncementBindingModel;
use App\Modules\Intelligence\Infrastructure\Persistence\Models\RemotePostBindingModel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class HubCandidateReleaseServiceTest extends TestCase
{
    private HubCandidateReleaseService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(HubCandidateReleaseService::class);
    }

    public function test_requires_explicit_confirmation(): void
    {
        $ctx = $this->context();
        $result = $this->service->release(
            $ctx['entity'],
            $ctx['announcement'],
            $ctx['user']->id,
            'open',
            'https://example.test/public-satellite',
            false,
        );

        $this->assertFalse($result['ok']);
        $this->assertSame('hub_release_not_confirmed', $result['reason']);
    }

    public function test_requires_valid_canonical_url_and_lifecycle(): void
    {
        $ctx = $this->context();

        $invalidUrl = $this->service->release(
            $ctx['entity'],
            $ctx['announcement'],
            $ctx['user']->id,
            'open',
            'not-a-url',
            true,
        );
        $this->assertFalse($invalidUrl['ok']);
        $this->assertSame('invalid_canonical_url', $invalidUrl['reason']);

        $invalidLifecycle = $this->service->release(
            $ctx['entity'],
            $ctx['announcement'],
            $ctx['user']->id,
            'auto_in_progress',
            'https://example.test/public-satellite',
            true,
        );
        $this->assertFalse($invalidLifecycle['ok']);
        $this->assertSame('invalid_lifecycle_status', $invalidLifecycle['reason']);
    }

    public function test_requires_bound_announcement_in_same_tenant(): void
    {
        $ctx = $this->context(bindAnnouncement: false);
        $result = $this->service->release(
            $ctx['entity'],
            $ctx['announcement'],
            $ctx['user']->id,
            'open',
            'https://example.test/public-satellite',
            true,
        );

        $this->assertFalse($result['ok']);
        $this->assertSame('announcement_not_bound_to_entity', $result['reason']);
    }

    public function test_successful_release_sets_verified_fields_and_confirms_binding_without_wordpress_calls(): void
    {
        Http::fake();

        $ctx = $this->context();
        $result = $this->service->release(
            $ctx['entity'],
            $ctx['announcement'],
            $ctx['user']->id,
            'in_progress',
            'https://example.test/public-satellite',
            true,
        );

        $this->assertTrue($result['ok']);
        Http::assertNothingSent();

        $entity = $ctx['entity']->fresh();
        $this->assertSame('verified', $entity->verification_status);
        $this->assertNotNull($entity->last_verified_at);
        $this->assertSame($ctx['announcement']->id, $entity->verified_announcement_id);
        $this->assertSame($ctx['announcement']->content_hash, $entity->verified_content_hash);
        $this->assertSame('in_progress', $entity->lifecycle_status);
        $this->assertTrue($entity->hub_member);
        $this->assertTrue($entity->publish_eligible);

        $binding = RemotePostBindingModel::query()
            ->where('content_entity_id', $entity->id)
            ->first();
        $this->assertNotNull($binding);
        $this->assertSame('https://example.test/public-satellite', $binding->canonical_url);
        $this->assertNotNull($binding->confirmed_at);
        $this->assertSame($ctx['user']->id, $binding->confirmed_by);
    }

    public function test_expired_open_lifecycle_still_excluded_by_existing_hub_rules(): void
    {
        Http::fake();
        $ctx = $this->context();
        $ctx['entity']->application_deadline_at = Carbon::parse('2026-08-01T00:00:00Z');
        $ctx['entity']->save();

        $this->service->release(
            $ctx['entity'],
            $ctx['announcement'],
            $ctx['user']->id,
            'open',
            'https://example.test/public-satellite',
            true,
        );

        $payload = app(HubPayloadBuilder::class)->build(
            $ctx['entity']->organization_id,
            $ctx['entity']->project_id,
            Carbon::parse('2026-08-28T12:00:00Z'),
        );

        $this->assertSame([], $payload['records']);
    }

    public function test_release_does_not_auto_set_in_progress_from_deadline(): void
    {
        $ctx = $this->context();
        $ctx['entity']->application_deadline_at = Carbon::parse('2026-08-01T00:00:00Z');
        $ctx['entity']->save();

        $this->service->release(
            $ctx['entity'],
            $ctx['announcement'],
            $ctx['user']->id,
            'open',
            'https://example.test/public-satellite',
            true,
        );

        $this->assertSame('open', $ctx['entity']->fresh()->lifecycle_status);
        $evaluation = app(EntityLifecycleService::class)->evaluate(
            $ctx['entity']->fresh(),
            Carbon::parse('2026-08-28T12:00:00Z'),
            168,
        );
        $this->assertSame(EntityLifecycleService::LIFECYCLE_VERIFICATION_REQUIRED, $evaluation['effective_lifecycle_status']);
    }

    /**
     * @return array{
     *     user: User,
     *     entity: ContentEntityModel,
     *     announcement: Announcement
     * }
     */
    private function context(bool $bindAnnouncement = true): array
    {
        $user = User::factory()->create(['password' => Hash::make('password-secret')]);
        $organization = app(OrganizationService::class)->create($user, 'Hub Release Org');
        $project = app(ProjectService::class)->create($organization, $user, 'Hub Release Project');

        $entity = ContentEntityModel::create([
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'entity_id' => 'hub-release-entity',
            'entity_type' => 'contest',
            'label' => 'Hub Release Entity',
            'content_role' => 'satellite',
            'lifecycle_status' => 'verification_required',
            'verification_status' => 'verification_required',
            'hub_member' => false,
            'archive_state' => 'active',
            'publish_eligible' => false,
        ]);

        $source = \App\Modules\Acquisition\Infrastructure\Persistence\Models\Source::create([
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'slug' => 'hub-src-'.Str::lower(Str::random(4)),
            'name' => 'Hub Source',
            'source_type' => 'rss',
            'base_url' => 'https://example.test',
            'feed_url' => 'https://example.test/feed-'.uniqid(),
            'feed_url_hash' => hash('sha256', uniqid('', true)),
            'allowed_domains' => ['example.test'],
            'enabled' => true,
            'manual_only' => true,
            'acquire_interval_seconds' => 3600,
        ]);

        $announcement = Announcement::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'source_id' => $source->id,
            'identity_hash' => hash('sha256', uniqid('', true)),
            'identity_basis' => 'canonical_url',
            'canonical_url' => 'https://example.test/announcement',
            'raw_title' => 'Hub release announcement',
            'content_hash' => hash('sha256', 'hub-release'),
            'raw_payload' => ['title' => 'Hub release announcement'],
            'revision_no' => 1,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        if ($bindAnnouncement) {
            EntityAnnouncementBindingModel::create([
                'organization_id' => $organization->id,
                'project_id' => $project->id,
                'content_entity_id' => $entity->id,
                'announcement_id' => $announcement->id,
                'binding_role' => 'primary',
                'source_revision_at_bind' => 1,
                'content_hash_at_bind' => $announcement->content_hash,
                'bound_at' => now(),
            ]);
        }

        return compact('user', 'entity', 'announcement');
    }
}
