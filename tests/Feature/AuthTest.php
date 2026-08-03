<?php

namespace Tests\Feature;

use App\Infrastructure\Persistence\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_with_personal_org(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'password' => 'password123',
            'create_personal_org' => true,
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'data' => ['user' => ['id', 'email'], 'token', 'organization' => ['id', 'name']],
            ]);

        $this->assertDatabaseHas('users', ['email' => 'alice@example.com']);
        $this->assertDatabaseHas('organizations', ['name' => "Alice's Organization"]);
    }

    public function test_user_can_login_and_get_profile(): void
    {
        $user = User::factory()->create(['email' => 'bob@example.com']);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'bob@example.com',
            'password' => 'password',
        ]);

        $login->assertOk()->assertJsonStructure(['data' => ['token', 'user']]);
        $token = $login->json('data.token');

        $me = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me');

        $me->assertOk()->assertJsonPath('data.email', 'bob@example.com');
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();
        $token = $this->authToken($user);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        User::factory()->create(['email' => 'carol@example.com']);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'carol@example.com',
            'password' => 'wrong-password',
        ])->assertUnauthorized();
    }
}
