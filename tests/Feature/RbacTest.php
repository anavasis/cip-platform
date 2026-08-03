<?php

namespace Tests\Feature;

use App\Domain\Shared\Enums\RoleScope;
use App\Infrastructure\Persistence\Models\OrganizationMembership;
use App\Infrastructure\Persistence\Models\Role;
use App\Infrastructure\Persistence\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RbacTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_cannot_manage_organization(): void
    {
        ['user' => $owner, 'organization' => $org] = $this->createUserWithOrg('owner');
        $member = User::factory()->create();
        $memberRole = Role::where('name', 'member')->where('scope', RoleScope::Organization)->first();

        OrganizationMembership::create([
            'organization_id' => $org->id,
            'user_id' => $member->id,
            'role_id' => $memberRole->id,
        ]);

        $token = $this->authToken($member);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson("/api/v1/organizations/{$org->id}", ['name' => 'Hacked'])
            ->assertForbidden();
    }

    public function test_owner_can_manage_projects(): void
    {
        ['user' => $user, 'organization' => $org] = $this->createUserWithOrg('owner');
        $token = $this->authToken($user);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/organizations/{$org->id}/projects", [
                'name' => 'My Project',
                'description' => 'Test project',
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'My Project');
    }

    public function test_member_cannot_manage_secrets(): void
    {
        ['organization' => $org] = $this->createUserWithOrg('owner');
        $member = User::factory()->create();
        $memberRole = Role::where('name', 'member')->where('scope', RoleScope::Organization)->first();

        OrganizationMembership::create([
            'organization_id' => $org->id,
            'user_id' => $member->id,
            'role_id' => $memberRole->id,
        ]);

        $token = $this->authToken($member);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/organizations/{$org->id}/secrets", [
                'key' => 'api_key',
                'value' => 'secret-value',
            ])
            ->assertForbidden();
    }
}
