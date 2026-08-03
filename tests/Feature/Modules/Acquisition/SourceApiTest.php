<?php

namespace Tests\Feature\Modules\Acquisition;

use App\Domain\Shared\Enums\RoleScope;
use App\Infrastructure\Persistence\Models\OrganizationMembership;
use App\Infrastructure\Persistence\Models\Project;
use App\Infrastructure\Persistence\Models\Role;
use App\Infrastructure\Persistence\Models\User;
use App\Modules\Acquisition\Infrastructure\Persistence\Models\Source;
use Tests\TestCase;

class SourceApiTest extends TestCase
{
    public function test_owner_can_create_list_show_and_update_project_source(): void
    {
        ['user' => $owner, 'organization' => $organization] = $this->createUserWithOrg('owner');
        $project = $this->createProject($organization->id, $owner->id, 'Editorial');
        $base = $this->baseUrl($organization->id, $project->id);

        $created = $this->authorized($owner)
            ->postJson($base.'/sources', $this->sourcePayload())
            ->assertCreated()
            ->assertJsonPath('data.slug', 'press-feed')
            ->assertJsonPath('data.name', 'Press Feed');
        $sourceId = $created->json('data.id');

        $this->authorized($owner)
            ->getJson($base.'/sources')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $sourceId);

        $this->authorized($owner)
            ->getJson($base.'/sources/'.$sourceId)
            ->assertOk()
            ->assertJsonPath('data.feed_url', 'https://example.com/feed.xml');

        $this->authorized($owner)
            ->patchJson($base.'/sources/'.$sourceId, ['name' => 'Updated Press Feed'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Press Feed');

        $this->assertDatabaseHas('sources', [
            'id' => $sourceId,
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'name' => 'Updated Press Feed',
        ]);
    }

    public function test_member_without_manage_permission_cannot_create_source(): void
    {
        ['user' => $owner, 'organization' => $organization] = $this->createUserWithOrg('owner');
        $project = $this->createProject($organization->id, $owner->id, 'Editorial');
        $member = User::factory()->create();
        $memberRole = Role::query()
            ->where('name', 'member')
            ->where('scope', RoleScope::Organization)
            ->firstOrFail();
        OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $member->id,
            'role_id' => $memberRole->id,
        ]);

        $this->authorized($member)
            ->postJson($this->baseUrl($organization->id, $project->id).'/sources', $this->sourcePayload())
            ->assertForbidden();

        $this->assertDatabaseCount('sources', 0);
    }

    public function test_source_route_binding_is_tenant_scoped(): void
    {
        ['user' => $ownerA, 'organization' => $organizationA] = $this->createUserWithOrg('owner');
        ['user' => $ownerB, 'organization' => $organizationB] = $this->createUserWithOrg('owner');
        $projectA = $this->createProject($organizationA->id, $ownerA->id, 'Project A');
        $projectB = $this->createProject($organizationB->id, $ownerB->id, 'Project B');
        $sourceB = $this->createSource($organizationB->id, $projectB->id, 'other-feed');

        $this->authorized($ownerA)
            ->getJson($this->baseUrl($organizationA->id, $projectA->id).'/sources/'.$sourceB->id)
            ->assertNotFound();

        $this->authorized($ownerA)
            ->getJson($this->baseUrl($organizationB->id, $projectB->id).'/sources/'.$sourceB->id)
            ->assertForbidden();
    }

    public function test_same_feed_url_is_allowed_in_different_projects(): void
    {
        ['user' => $owner, 'organization' => $organization] = $this->createUserWithOrg('owner');
        $projectA = $this->createProject($organization->id, $owner->id, 'Project A');
        $projectB = $this->createProject($organization->id, $owner->id, 'Project B');

        $this->authorized($owner)
            ->postJson($this->baseUrl($organization->id, $projectA->id).'/sources', $this->sourcePayload('feed-a'))
            ->assertCreated();
        $this->authorized($owner)
            ->postJson($this->baseUrl($organization->id, $projectB->id).'/sources', $this->sourcePayload('feed-b'))
            ->assertCreated();

        $this->assertDatabaseCount('sources', 2);
    }

    public function test_duplicate_slug_in_same_project_is_rejected(): void
    {
        ['user' => $owner, 'organization' => $organization] = $this->createUserWithOrg('owner');
        $project = $this->createProject($organization->id, $owner->id, 'Editorial');
        $url = $this->baseUrl($organization->id, $project->id).'/sources';

        $this->authorized($owner)->postJson($url, $this->sourcePayload())->assertCreated();
        $duplicate = $this->sourcePayload();
        $duplicate['feed_url'] = 'https://example.com/other.xml';

        $this->authorized($owner)
            ->postJson($url, $duplicate)
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'duplicate_slug');
    }

    private function authorized(User $user): self
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->authToken($user));
    }

    private function baseUrl(string $organizationId, string $projectId): string
    {
        return "/api/v1/organizations/{$organizationId}/projects/{$projectId}";
    }

    /** @return array<string, mixed> */
    private function sourcePayload(string $slug = 'press-feed'): array
    {
        return [
            'slug' => $slug,
            'name' => 'Press Feed',
            'source_type' => 'rss',
            'base_url' => 'https://example.com',
            'feed_url' => 'https://example.com/feed.xml',
            'allowed_domains' => ['example.com'],
            'parser_profile' => null,
            'enabled' => true,
            'manual_only' => false,
        ];
    }

    private function createProject(string $organizationId, string $userId, string $name): Project
    {
        return Project::create([
            'organization_id' => $organizationId,
            'name' => $name,
            'slug' => strtolower(str_replace(' ', '-', $name)).'-'.uniqid(),
            'created_by' => $userId,
        ]);
    }

    private function createSource(string $organizationId, string $projectId, string $slug): Source
    {
        return Source::create([
            'organization_id' => $organizationId,
            'project_id' => $projectId,
            'slug' => $slug,
            'name' => 'Other Feed',
            'source_type' => 'rss',
            'base_url' => 'https://example.net',
            'feed_url' => 'https://example.net/feed.xml',
            'feed_url_hash' => hash('sha256', 'https://example.net/feed.xml'),
            'allowed_domains' => ['example.net'],
            'enabled' => true,
            'manual_only' => false,
        ]);
    }
}
