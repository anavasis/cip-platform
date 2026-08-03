<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_schedule_and_run_due(): void
    {
        ['user' => $user, 'organization' => $org] = $this->createUserWithOrg('owner');
        $token = $this->authToken($user);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/organizations/{$org->id}/schedules", [
                'name' => 'Hourly Ping',
                'cron_expression' => '0 * * * *',
                'job_type' => 'ping',
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Hourly Ping');

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/schedules/run-due');

        $response->assertOk()
            ->assertJsonPath('data.dispatched', 1);

        $this->assertDatabaseHas('platform_jobs', ['job_type' => 'ping']);
    }
}
