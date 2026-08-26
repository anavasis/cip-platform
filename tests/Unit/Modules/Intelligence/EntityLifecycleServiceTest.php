<?php

namespace Tests\Unit\Modules\Intelligence;

use App\Modules\Intelligence\Application\EntityLifecycleService;
use App\Modules\Intelligence\Infrastructure\Persistence\Models\ContentEntityModel;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class EntityLifecycleServiceTest extends TestCase
{
    private EntityLifecycleService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new EntityLifecycleService();
    }

    public function test_verified_open_with_future_deadline_is_effective_open(): void
    {
        $now = Carbon::parse('2026-08-26T12:00:00Z');
        $entity = $this->makeEntity([
            'lifecycle_status' => 'open',
            'verification_status' => 'verified',
            'application_deadline_at' => Carbon::parse('2026-09-01T00:00:00Z'),
            'last_verified_at' => Carbon::parse('2026-08-20T00:00:00Z'),
            'hub_member' => true,
            'publish_eligible' => true,
        ]);

        $result = $this->service->evaluate($entity, $now, 168);

        $this->assertSame('open', $result['effective_lifecycle_status']);
        $this->assertTrue($result['is_public_eligible']);
        $this->assertSame('open_now', $result['display_section']);
    }

    public function test_verified_open_with_deadline_equal_to_now_is_verification_required(): void
    {
        $now = Carbon::parse('2026-08-26T12:00:00Z');
        $entity = $this->makeEntity([
            'lifecycle_status' => 'open',
            'verification_status' => 'verified',
            'application_deadline_at' => Carbon::parse('2026-08-26T11:00:00Z'),
            'last_verified_at' => Carbon::parse('2026-08-20T00:00:00Z'),
            'hub_member' => true,
            'publish_eligible' => true,
        ]);

        $result = $this->service->evaluate($entity, $now, 168);

        $this->assertSame('verification_required', $result['effective_lifecycle_status']);
        $this->assertFalse($result['is_public_eligible']);
    }

    public function test_verified_open_with_passed_deadline_is_verification_required(): void
    {
        $now = Carbon::parse('2026-08-26T12:00:00Z');
        $entity = $this->makeEntity([
            'lifecycle_status' => 'open',
            'verification_status' => 'verified',
            'application_deadline_at' => Carbon::parse('2026-08-20T00:00:00Z'),
            'last_verified_at' => Carbon::parse('2026-08-19T00:00:00Z'),
            'hub_member' => true,
            'publish_eligible' => true,
        ]);

        $result = $this->service->evaluate($entity, $now, 168);

        $this->assertSame('verification_required', $result['effective_lifecycle_status']);
        $this->assertFalse($result['is_public_eligible']);
        $this->assertNotSame('in_progress', $result['effective_lifecycle_status']);
    }

    public function test_passed_deadline_never_becomes_in_progress_automatically(): void
    {
        $now = Carbon::parse('2026-08-26T12:00:00Z');
        $entity = $this->makeEntity([
            'lifecycle_status' => 'open',
            'verification_status' => 'verified',
            'application_deadline_at' => Carbon::parse('2026-08-01T00:00:00Z'),
            'last_verified_at' => Carbon::parse('2026-07-30T00:00:00Z'),
            'hub_member' => true,
            'publish_eligible' => true,
        ]);

        $result = $this->service->evaluate($entity, $now, 168);

        $this->assertNotSame('in_progress', $result['effective_lifecycle_status']);
    }

    public function test_verification_required_is_not_public_eligible(): void
    {
        $entity = $this->makeEntity([
            'lifecycle_status' => 'verification_required',
            'verification_status' => 'verified',
            'last_verified_at' => Carbon::parse('2026-08-20T00:00:00Z'),
            'hub_member' => true,
            'publish_eligible' => true,
        ]);

        $result = $this->service->evaluate($entity, Carbon::parse('2026-08-26T12:00:00Z'), 168);

        $this->assertFalse($result['is_public_eligible']);
    }

    public function test_stale_is_not_public_eligible(): void
    {
        $entity = $this->makeEntity([
            'lifecycle_status' => 'open',
            'verification_status' => 'verified',
            'last_verified_at' => Carbon::parse('2026-08-01T00:00:00Z'),
            'hub_member' => true,
            'publish_eligible' => true,
        ]);

        $result = $this->service->evaluate($entity, Carbon::parse('2026-08-26T12:00:00Z'), 168);

        $this->assertSame('stale', $result['effective_verification_status']);
        $this->assertFalse($result['is_public_eligible']);
    }

    public function test_unverifiable_is_not_public_eligible(): void
    {
        $entity = $this->makeEntity([
            'lifecycle_status' => 'open',
            'verification_status' => 'unverifiable',
            'last_verified_at' => Carbon::parse('2026-08-20T00:00:00Z'),
            'hub_member' => true,
            'publish_eligible' => true,
        ]);

        $result = $this->service->evaluate($entity, Carbon::parse('2026-08-26T12:00:00Z'), 168);

        $this->assertFalse($result['is_public_eligible']);
    }

    public function test_completed_is_not_public_eligible(): void
    {
        $entity = $this->makeEntity([
            'lifecycle_status' => 'completed',
            'verification_status' => 'verified',
            'last_verified_at' => Carbon::parse('2026-08-20T00:00:00Z'),
            'hub_member' => true,
            'publish_eligible' => true,
        ]);

        $result = $this->service->evaluate($entity, Carbon::parse('2026-08-26T12:00:00Z'), 168);

        $this->assertFalse($result['is_public_eligible']);
    }

    public function test_archived_lifecycle_is_not_public_eligible(): void
    {
        $entity = $this->makeEntity([
            'lifecycle_status' => 'archived',
            'verification_status' => 'verified',
            'last_verified_at' => Carbon::parse('2026-08-20T00:00:00Z'),
            'hub_member' => true,
            'publish_eligible' => true,
        ]);

        $result = $this->service->evaluate($entity, Carbon::parse('2026-08-26T12:00:00Z'), 168);

        $this->assertFalse($result['is_public_eligible']);
    }

    public function test_hub_member_false_is_not_public_eligible(): void
    {
        $entity = $this->makeEntity([
            'lifecycle_status' => 'open',
            'verification_status' => 'verified',
            'last_verified_at' => Carbon::parse('2026-08-20T00:00:00Z'),
            'hub_member' => false,
            'publish_eligible' => true,
        ]);

        $result = $this->service->evaluate($entity, Carbon::parse('2026-08-26T12:00:00Z'), 168);

        $this->assertFalse($result['is_public_eligible']);
    }

    public function test_publish_eligible_false_is_not_public_eligible(): void
    {
        $entity = $this->makeEntity([
            'lifecycle_status' => 'open',
            'verification_status' => 'verified',
            'last_verified_at' => Carbon::parse('2026-08-20T00:00:00Z'),
            'hub_member' => true,
            'publish_eligible' => false,
        ]);

        $result = $this->service->evaluate($entity, Carbon::parse('2026-08-26T12:00:00Z'), 168);

        $this->assertFalse($result['is_public_eligible']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeEntity(array $overrides): ContentEntityModel
    {
        $defaults = [
            'organization_id' => '00000000-0000-4000-8000-000000000001',
            'project_id' => '00000000-0000-4000-8000-000000000002',
            'entity_id' => 'entity-test',
            'entity_type' => 'process',
            'label' => 'Test Entity',
            'source_family' => 'other',
            'thematic_categories' => [],
            'content_role' => 'satellite',
            'archive_state' => 'active',
            'hub_member' => false,
            'publish_eligible' => false,
            'lifecycle_status' => 'verification_required',
            'verification_status' => 'verification_required',
        ];

        return new ContentEntityModel(array_merge($defaults, $overrides));
    }
}
