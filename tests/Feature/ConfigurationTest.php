<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_set_and_get_configuration(): void
    {
        ['user' => $user, 'organization' => $org] = $this->createUserWithOrg('owner');
        $token = $this->authToken($user);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/organizations/{$org->id}/config", [
                'key' => 'theme',
                'value' => ['mode' => 'dark'],
            ])
            ->assertCreated()
            ->assertJsonPath('data.key', 'theme');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/v1/organizations/{$org->id}/config/theme")
            ->assertOk()
            ->assertJsonPath('data.value.mode', 'dark');
    }
}
