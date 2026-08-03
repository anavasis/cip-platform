<?php

namespace Tests\Feature;

use App\Infrastructure\Persistence\Models\StoredEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventBusTest extends TestCase
{
    use RefreshDatabase;

    public function test_domain_events_are_stored_on_registration(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Event User',
            'email' => 'events@example.com',
            'password' => 'password123',
        ])->assertCreated();

        $this->assertDatabaseHas('stored_events', [
            'event_type' => 'user.registered',
        ]);
    }

    public function test_events_can_be_listed(): void
    {
        ['user' => $user] = $this->createUserWithOrg('owner');
        $token = $this->authToken($user);

        StoredEvent::create([
            'event_type' => 'test.event',
            'payload' => ['foo' => 'bar'],
            'occurred_at' => now(),
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/events')
            ->assertOk()
            ->assertJsonFragment(['event_type' => 'test.event']);
    }
}
