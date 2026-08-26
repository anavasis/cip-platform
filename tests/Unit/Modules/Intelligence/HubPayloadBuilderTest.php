<?php

namespace Tests\Unit\Modules\Intelligence;

use App\Application\Services\ConfigurationService;
use App\Application\Services\OrganizationService;
use App\Application\Services\ProjectService;
use App\Infrastructure\Persistence\Models\User;
use App\Modules\Intelligence\Application\HubPayloadBuilder;
use App\Modules\Intelligence\Infrastructure\Persistence\Models\ContentEntityModel;
use App\Modules\Intelligence\Infrastructure\Persistence\Models\RemotePostBindingModel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class HubPayloadBuilderTest extends TestCase
{
    private HubPayloadBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = app(HubPayloadBuilder::class);
    }

    public function test_only_verified_current_eligible_records_appear(): void
    {
        $ctx = $this->tenantContext();
        $now = Carbon::parse('2026-08-26T12:00:00Z');

        $this->createEligibleEntity($ctx, 'eligible-a', $now);
        $this->createEntity($ctx, [
            'entity_id' => 'not-eligible-b',
            'label' => 'Not Eligible',
            'hub_member' => true,
            'publish_eligible' => true,
            'lifecycle_status' => 'open',
            'verification_status' => 'verified',
            'last_verified_at' => $now->copy()->subDay(),
            'application_deadline_at' => $now->copy()->addDays(5),
        ]);

        $payload = $this->builder->build($ctx['organization']->id, $ctx['project']->id, $now);

        $this->assertCount(1, $payload['records']);
        $this->assertSame('eligible-a', $payload['records'][0]['entity_id']);
    }

    public function test_open_expired_record_excluded(): void
    {
        $ctx = $this->tenantContext();
        $now = Carbon::parse('2026-08-26T12:00:00Z');
        $entity = $this->createEntity($ctx, [
            'entity_id' => 'expired-open',
            'label' => 'Expired Open',
            'hub_member' => true,
            'publish_eligible' => true,
            'lifecycle_status' => 'open',
            'verification_status' => 'verified',
            'last_verified_at' => $now->copy()->subDay(),
            'application_deadline_at' => $now->copy()->subDays(2),
        ]);
        $this->createConfirmedBinding($ctx, $entity, 'https://example.test/expired-open');

        $payload = $this->builder->build($ctx['organization']->id, $ctx['project']->id, $now);

        $this->assertSame([], $payload['records']);
    }

    public function test_stale_last_verified_at_record_excluded(): void
    {
        $ctx = $this->tenantContext();
        $now = Carbon::parse('2026-08-26T12:00:00Z');
        $entity = $this->createEntity($ctx, [
            'entity_id' => 'stale-record',
            'label' => 'Stale Record',
            'hub_member' => true,
            'publish_eligible' => true,
            'lifecycle_status' => 'open',
            'verification_status' => 'verified',
            'last_verified_at' => $now->copy()->subDays(30),
            'application_deadline_at' => $now->copy()->addDays(5),
        ]);
        $this->createConfirmedBinding($ctx, $entity, 'https://example.test/stale-record');

        $payload = $this->builder->build($ctx['organization']->id, $ctx['project']->id, $now);

        $this->assertSame([], $payload['records']);
    }

    public function test_missing_satellite_binding_excluded(): void
    {
        $ctx = $this->tenantContext();
        $now = Carbon::parse('2026-08-26T12:00:00Z');
        $this->createEntity($ctx, [
            'entity_id' => 'no-binding',
            'label' => 'No Binding',
            'hub_member' => true,
            'publish_eligible' => true,
            'lifecycle_status' => 'open',
            'verification_status' => 'verified',
            'last_verified_at' => $now->copy()->subDay(),
            'application_deadline_at' => $now->copy()->addDays(5),
        ]);

        $payload = $this->builder->build($ctx['organization']->id, $ctx['project']->id, $now);

        $this->assertSame([], $payload['records']);
    }

    public function test_unconfirmed_satellite_binding_excluded(): void
    {
        $ctx = $this->tenantContext();
        $now = Carbon::parse('2026-08-26T12:00:00Z');
        $entity = $this->createEntity($ctx, [
            'entity_id' => 'unconfirmed',
            'label' => 'Unconfirmed',
            'hub_member' => true,
            'publish_eligible' => true,
            'lifecycle_status' => 'open',
            'verification_status' => 'verified',
            'last_verified_at' => $now->copy()->subDay(),
            'application_deadline_at' => $now->copy()->addDays(5),
        ]);
        RemotePostBindingModel::create([
            'organization_id' => $ctx['organization']->id,
            'project_id' => $ctx['project']->id,
            'content_entity_id' => $entity->id,
            'remote_system' => 'wordpress',
            'remote_post_id' => null,
            'canonical_url' => 'https://example.test/unconfirmed',
            'confirmed_at' => null,
            'bound_at' => $now,
        ]);

        $payload = $this->builder->build($ctx['organization']->id, $ctx['project']->id, $now);

        $this->assertSame([], $payload['records']);
    }

    public function test_confirmed_satellite_binding_exposes_canonical_satellite_url(): void
    {
        $ctx = $this->tenantContext();
        $now = Carbon::parse('2026-08-26T12:00:00Z');
        $this->createEligibleEntity($ctx, 'confirmed-a', $now, 'https://example.test/satellite-a');

        $payload = $this->builder->build($ctx['organization']->id, $ctx['project']->id, $now);

        $this->assertSame('https://example.test/satellite-a', $payload['records'][0]['satellite_url']);
    }

    public function test_no_official_source_url_in_payload(): void
    {
        $ctx = $this->tenantContext();
        $now = Carbon::parse('2026-08-26T12:00:00Z');
        $this->createEligibleEntity($ctx, 'source-safe', $now);

        $payload = $this->builder->build($ctx['organization']->id, $ctx['project']->id, $now);
        $encoded = json_encode($payload);

        $this->assertIsString($encoded);
        $this->assertStringNotContainsString('canonical_url', $encoded);
        $this->assertStringNotContainsString('official', strtolower($encoded));
    }

    public function test_public_payload_excludes_remote_post_id(): void
    {
        $ctx = $this->tenantContext();
        $now = Carbon::parse('2026-08-26T12:00:00Z');
        $entity = $this->createEligibleEntity($ctx, 'post-id-hidden', $now);
        RemotePostBindingModel::query()
            ->where('content_entity_id', $entity->id)
            ->update(['remote_post_id' => '12345']);

        $payload = $this->builder->build($ctx['organization']->id, $ctx['project']->id, $now);
        $encoded = json_encode($payload);

        $this->assertIsString($encoded);
        $this->assertStringNotContainsString('remote_post_id', $encoded);
        $this->assertStringNotContainsString('12345', $encoded);
    }

    public function test_public_payload_excludes_announcement_ids_hashes_and_raw_evidence(): void
    {
        $ctx = $this->tenantContext();
        $now = Carbon::parse('2026-08-26T12:00:00Z');
        $entity = $this->createEligibleEntity($ctx, 'internal-hidden', $now);
        $entity->update([
            'verified_announcement_id' => '00000000-0000-4000-8000-000000000099',
            'verified_content_hash' => hash('sha256', 'secret-evidence'),
        ]);

        $payload = $this->builder->build($ctx['organization']->id, $ctx['project']->id, $now);
        $encoded = json_encode($payload);

        $this->assertIsString($encoded);
        $this->assertStringNotContainsString('verified_announcement_id', $encoded);
        $this->assertStringNotContainsString('verified_content_hash', $encoded);
        $this->assertStringNotContainsString('raw_payload', $encoded);
        $this->assertStringNotContainsString('identity_hash', $encoded);
        $this->assertStringNotContainsString('content_hash', $encoded);
        $this->assertStringNotContainsString('00000000-0000-4000-8000-000000000099', $encoded);
    }

    public function test_filters_are_separated_into_lifecycle_source_family_and_thematic(): void
    {
        $ctx = $this->tenantContext();
        $now = Carbon::parse('2026-08-26T12:00:00Z');
        $this->createEligibleEntity($ctx, 'filter-a', $now, 'https://example.test/a', [
            'source_family' => 'asep',
            'thematic_categories' => ['health'],
            'lifecycle_status' => 'open',
        ]);
        $entityB = $this->createEntity($ctx, [
            'entity_id' => 'filter-b',
            'label' => 'Filter B',
            'hub_member' => true,
            'publish_eligible' => true,
            'lifecycle_status' => 'in_progress',
            'verification_status' => 'verified',
            'last_verified_at' => $now->copy()->subDay(),
            'source_family' => 'ministry',
            'thematic_categories' => ['education'],
        ]);
        $this->createConfirmedBinding($ctx, $entityB, 'https://example.test/b');

        $payload = $this->builder->build($ctx['organization']->id, $ctx['project']->id, $now);

        $this->assertSame(['in_progress', 'open_now'], $payload['filters']['lifecycle']);
        $this->assertSame(['asep', 'ministry'], $payload['filters']['source_family']);
        $this->assertSame(['education', 'health'], $payload['filters']['thematic']);
    }

    public function test_archived_record_excluded(): void
    {
        $ctx = $this->tenantContext();
        $now = Carbon::parse('2026-08-26T12:00:00Z');
        $entity = $this->createEntity($ctx, [
            'entity_id' => 'archived-record',
            'label' => 'Archived',
            'hub_member' => true,
            'publish_eligible' => true,
            'archive_state' => 'archived',
            'lifecycle_status' => 'open',
            'verification_status' => 'verified',
            'last_verified_at' => $now->copy()->subDay(),
            'application_deadline_at' => $now->copy()->addDays(5),
        ]);
        $this->createConfirmedBinding($ctx, $entity, 'https://example.test/archived');

        $payload = $this->builder->build($ctx['organization']->id, $ctx['project']->id, $now);

        $this->assertSame([], $payload['records']);
    }

    public function test_records_have_deterministic_ordering(): void
    {
        $ctx = $this->tenantContext();
        $now = Carbon::parse('2026-08-26T12:00:00Z');
        $this->createEligibleEntity($ctx, 'z-entity', $now, 'https://example.test/z');
        $this->createEligibleEntity($ctx, 'a-entity', $now, 'https://example.test/a');

        $payload = $this->builder->build($ctx['organization']->id, $ctx['project']->id, $now);

        $this->assertSame(['a-entity', 'z-entity'], array_column($payload['records'], 'entity_id'));
    }

    public function test_hub_profile_config_metadata_applied(): void
    {
        $ctx = $this->tenantContext();
        app(ConfigurationService::class)->set(
            $ctx['organization']->id,
            HubPayloadBuilder::HUB_PROFILE_KEY,
            [
                'value' => [
                    'version' => 1,
                    'hub_entity_id' => 'custom-hub',
                    'hub_url' => 'https://example.test/prokiryxeis/',
                    'hub_title' => 'Custom Hub Title',
                    'stale_threshold_hours' => 24,
                ],
            ],
            $ctx['project']->id,
        );

        $payload = $this->builder->build(
            $ctx['organization']->id,
            $ctx['project']->id,
            Carbon::parse('2026-08-26T12:00:00Z'),
        );

        $this->assertSame('custom-hub', $payload['hub']['entity_id']);
        $this->assertSame('https://example.test/prokiryxeis/', $payload['hub']['url']);
        $this->assertSame('Custom Hub Title', $payload['hub']['title']);
        $this->assertSame(24, $payload['freshness']['stale_threshold_hours']);
    }

    public function test_missing_hub_profile_uses_safe_generic_values(): void
    {
        $ctx = $this->tenantContext();

        $payload = $this->builder->build(
            $ctx['organization']->id,
            $ctx['project']->id,
            Carbon::parse('2026-08-26T12:00:00Z'),
        );

        $this->assertSame(1, $payload['schema_version']);
        $this->assertSame('hub', $payload['hub']['entity_id']);
        $this->assertNull($payload['hub']['url']);
        $this->assertNull($payload['hub']['title']);
        $this->assertSame(168, $payload['freshness']['stale_threshold_hours']);
    }

    public function test_organization_and_project_isolation(): void
    {
        $ctxA = $this->tenantContext('Org A', 'Project A');
        $ctxB = $this->tenantContext('Org B', 'Project B');
        $now = Carbon::parse('2026-08-26T12:00:00Z');

        $this->createEligibleEntity($ctxA, 'project-a-only', $now, 'https://example.test/a-only');
        $this->createEligibleEntity($ctxB, 'project-b-only', $now, 'https://example.test/b-only');

        $payloadA = $this->builder->build($ctxA['organization']->id, $ctxA['project']->id, $now);
        $payloadB = $this->builder->build($ctxB['organization']->id, $ctxB['project']->id, $now);

        $this->assertSame(['project-a-only'], array_column($payloadA['records'], 'entity_id'));
        $this->assertSame(['project-b-only'], array_column($payloadB['records'], 'entity_id'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createEntity(array $ctx, array $overrides): ContentEntityModel
    {
        return ContentEntityModel::create(array_merge([
            'organization_id' => $ctx['organization']->id,
            'project_id' => $ctx['project']->id,
            'entity_type' => 'process',
            'label' => 'Default Label',
            'source_family' => 'other',
            'thematic_categories' => [],
            'content_role' => 'satellite',
            'archive_state' => 'active',
            'hub_member' => false,
            'publish_eligible' => false,
            'lifecycle_status' => 'verification_required',
            'verification_status' => 'verification_required',
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createEligibleEntity(
        array $ctx,
        string $entityId,
        Carbon $now,
        string $satelliteUrl = 'https://example.test/satellite',
        array $overrides = [],
    ): ContentEntityModel {
        $entity = $this->createEntity($ctx, array_merge([
            'entity_id' => $entityId,
            'label' => strtoupper($entityId),
            'hub_member' => true,
            'publish_eligible' => true,
            'lifecycle_status' => 'open',
            'verification_status' => 'verified',
            'last_verified_at' => $now->copy()->subDay(),
            'application_deadline_at' => $now->copy()->addDays(5),
        ], $overrides));
        $this->createConfirmedBinding($ctx, $entity, $satelliteUrl);

        return $entity;
    }

    private function createConfirmedBinding(array $ctx, ContentEntityModel $entity, string $url): void
    {
        RemotePostBindingModel::create([
            'organization_id' => $ctx['organization']->id,
            'project_id' => $ctx['project']->id,
            'content_entity_id' => $entity->id,
            'remote_system' => 'wordpress',
            'remote_post_id' => null,
            'canonical_url' => $url,
            'confirmed_at' => now(),
            'bound_at' => now(),
        ]);
    }

    /**
     * @return array{user: User, organization: mixed, project: mixed}
     */
    private function tenantContext(string $orgName = 'Hub Org', string $projectName = 'Hub Project'): array
    {
        $user = User::factory()->create(['password' => Hash::make('password-secret')]);
        $organization = app(OrganizationService::class)->create($user, $orgName);
        $project = app(ProjectService::class)->create($organization, $user, $projectName);

        return compact('user', 'organization', 'project');
    }
}
