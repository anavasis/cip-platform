<?php

namespace Tests;

use App\Domain\Shared\Enums\RoleScope;
use App\Infrastructure\Persistence\Models\Organization;
use App\Infrastructure\Persistence\Models\OrganizationMembership;
use App\Infrastructure\Persistence\Models\Role;
use App\Infrastructure\Persistence\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Sanctum\Sanctum;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->withoutVite();
    }

    protected function createUserWithOrg(string $roleName = 'owner'): array
    {
        $user = User::factory()->create();
        $organization = Organization::create([
            'name' => 'Test Org',
            'slug' => 'test-org-'.uniqid(),
            'created_by' => $user->id,
        ]);

        $role = Role::query()
            ->where('name', $roleName)
            ->where('scope', RoleScope::Organization)
            ->firstOrFail();

        OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
        ]);

        return compact('user', 'organization');
    }

    protected function actingAsUser(User $user): self
    {
        Sanctum::actingAs($user);

        return $this;
    }

    protected function authToken(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }
}
