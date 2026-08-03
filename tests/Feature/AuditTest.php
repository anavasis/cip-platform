<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_events_are_recorded_on_actions(): void
    {
        ['user' => $user, 'organization' => $org] = $this->createUserWithOrg('owner');
        $token = $this->authToken($user);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/organizations/{$org->id}/projects", ['name' => 'Audited Project'])
            ->assertCreated();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/v1/organizations/{$org->id}/audit")
            ->assertOk();

        $this->assertDatabaseHas('audit_events', [
            'organization_id' => $org->id,
            'action' => 'project.created',
        ]);
    }
}
