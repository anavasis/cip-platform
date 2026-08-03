<?php

namespace Tests\Feature;

use App\Domain\Shared\Enums\PlatformJobStatus;
use App\Infrastructure\Jobs\PingJob;
use App\Infrastructure\Persistence\Models\PlatformJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class JobEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatch_ping_job_creates_platform_job(): void
    {
        Queue::fake();

        ['user' => $user, 'organization' => $org] = $this->createUserWithOrg('owner');
        $token = $this->authToken($user);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/organizations/{$org->id}/jobs/ping");

        $response->assertAccepted()
            ->assertJsonPath('data.job_type', 'ping')
            ->assertJsonPath('data.status', 'pending');

        Queue::assertPushed(PingJob::class);
        $this->assertDatabaseHas('platform_jobs', ['job_type' => 'ping']);
    }

    public function test_ping_job_completes_successfully(): void
    {
        $job = PlatformJob::create([
            'job_type' => 'ping',
            'status' => PlatformJobStatus::Pending,
            'payload' => [],
        ]);

        $worker = new PingJob($job->id);
        $worker->handle(app(\App\Application\Services\JobEngineService::class), app(\App\Application\Services\EventBusService::class));

        $job->refresh();
        $this->assertEquals(PlatformJobStatus::Completed, $job->status);
        $this->assertEquals('pong', $job->result['message'] ?? null);
    }

    public function test_jobs_can_be_listed(): void
    {
        ['user' => $user, 'organization' => $org] = $this->createUserWithOrg('owner');
        $token = $this->authToken($user);

        PlatformJob::create([
            'organization_id' => $org->id,
            'job_type' => 'ping',
            'status' => PlatformJobStatus::Completed,
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/v1/organizations/{$org->id}/jobs")
            ->assertOk()
            ->assertJsonCount(1, 'data.data');
    }
}
