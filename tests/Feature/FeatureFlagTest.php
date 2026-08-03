<?php

namespace Tests\Feature;

use App\Domain\Shared\Enums\FeatureFlagScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeatureFlagTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_upsert_and_list_feature_flags(): void
    {
        ['user' => $user, 'organization' => $org] = $this->createUserWithOrg('owner');
        $token = $this->authToken($user);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/flags', [
                'key' => 'new_dashboard',
                'enabled' => true,
                'scope' => 'global',
            ])
            ->assertCreated();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/organizations/{$org->id}/flags", [
                'key' => 'org_feature',
                'enabled' => false,
                'scope' => 'organization',
            ])
            ->assertCreated();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/v1/organizations/{$org->id}/flags");

        $response->assertOk();
        $keys = collect($response->json('data'))->pluck('key');
        $this->assertTrue($keys->contains('new_dashboard'));
        $this->assertTrue($keys->contains('org_feature'));
    }
}
