<?php

namespace Tests\Feature;

use App\Infrastructure\Persistence\Models\Secret;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecretTest extends TestCase
{
    use RefreshDatabase;

    public function test_secret_is_encrypted_at_rest(): void
    {
        ['user' => $user, 'organization' => $org] = $this->createUserWithOrg('owner');
        $token = $this->authToken($user);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/organizations/{$org->id}/secrets", [
                'key' => 'db_password',
                'value' => 'plaintext-secret',
            ])
            ->assertCreated();

        $secret = Secret::first();
        $this->assertNotEquals('plaintext-secret', $secret->encrypted_value);
        $this->assertNotEmpty($secret->encrypted_value);
    }

    public function test_secret_list_masks_value(): void
    {
        ['user' => $user, 'organization' => $org] = $this->createUserWithOrg('owner');
        $token = $this->authToken($user);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/organizations/{$org->id}/secrets", [
                'key' => 'token',
                'value' => 'my-token',
            ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/v1/organizations/{$org->id}/secrets");

        $response->assertOk()
            ->assertJsonPath('data.0.value', '********');
    }

    public function test_secret_reveal_returns_plaintext_for_authorized_user(): void
    {
        ['user' => $user, 'organization' => $org] = $this->createUserWithOrg('owner');
        $token = $this->authToken($user);

        $create = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/organizations/{$org->id}/secrets", [
                'key' => 'api_key',
                'value' => 'reveal-me',
            ]);

        $secretId = $create->json('data.id');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/v1/organizations/{$org->id}/secrets/{$secretId}/reveal")
            ->assertOk()
            ->assertJsonPath('data.value', 'reveal-me');
    }
}
