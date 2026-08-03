<?php

namespace Tests\Feature\Modules\Acquisition;

use App\Application\Services\FeatureFlagService;
use App\Application\Services\JobEngineService;
use App\Domain\Shared\Enums\FeatureFlagScope;
use App\Domain\Shared\Enums\PlatformJobStatus;
use App\Infrastructure\Persistence\Models\Project;
use App\Infrastructure\Persistence\Models\StoredEvent;
use App\Modules\Acquisition\Application\CapabilityGate;
use App\Modules\Acquisition\Domain\Events\AcquisitionRunStarted;
use App\Modules\Acquisition\Infrastructure\Jobs\AcquireSourceJob;
use App\Modules\Acquisition\Infrastructure\Persistence\Models\AcquisitionRun;
use App\Modules\Acquisition\Infrastructure\Persistence\Models\Source;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Tests\Feature\Modules\Acquisition\Support\AcquisitionHttpTestBindings;
use Tests\Feature\Modules\Acquisition\Support\SequencedFeedFetcher;
use Tests\TestCase;

class RetryBehaviorTest extends TestCase
{
    public function test_retryable_transport_failure_is_rethrown_and_queue_retry_succeeds(): void
    {
        config(['queue.default' => 'database']);
        $fetcher = new SequencedFeedFetcher([
            [
                'success' => false,
                'error_code' => 'http_error',
                'http_status' => 503,
                'content_type' => 'text/plain',
                'body' => '',
                'response_size' => 0,
            ],
            [
                'success' => true,
                'body' => $this->rssBody(),
                'content_type' => 'application/rss+xml',
            ],
        ]);
        AcquisitionHttpTestBindings::bindFeedFetcher($this->app, $fetcher);
        ['user' => $owner, 'organization' => $organization] = $this->createUserWithOrg();
        $project = Project::create([
            'organization_id' => $organization->id,
            'name' => 'Retry Behavior',
            'slug' => 'retry-behavior',
            'created_by' => $owner->id,
        ]);
        $this->enableAcquisition($organization->id, $project->id);
        $source = $this->createSource($organization->id, $project->id);
        $platformJob = app(JobEngineService::class)->create(
            'acquisition.acquire_source',
            $organization->id,
            $project->id,
            [
                'organization_id' => $organization->id,
                'project_id' => $project->id,
                'source_id' => $source->id,
                'trigger' => 'retry-behavior-test',
            ],
        );
        $runStatusesWhenStarted = [];
        Event::listen(
            AcquisitionRunStarted::class,
            static function (AcquisitionRunStarted $event) use (&$runStatusesWhenStarted): void {
                $runStatusesWhenStarted[] = AcquisitionRun::query()
                    ->where('run_id', $event->runId)
                    ->value('status');
            },
        );

        AcquireSourceJob::dispatch($platformJob->id);
        $this->assertDatabaseCount('jobs', 1);
        $this->assertSame(0, Artisan::call('queue:work', [
            'connection' => 'database',
            '--once' => true,
            '--tries' => 3,
            '--sleep' => 0,
        ]));

        $platformJob->refresh();
        $this->assertSame(PlatformJobStatus::Failed, $platformJob->status);
        $this->assertSame('http_error', $platformJob->error);
        $this->assertDatabaseHas('acquisition_runs', [
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'status' => 'failed',
            'error_code' => 'http_error',
        ]);
        $this->assertDatabaseCount('jobs', 1);

        $this->assertSame(0, Artisan::call('queue:work', [
            'connection' => 'database',
            '--once' => true,
            '--tries' => 3,
            '--sleep' => 0,
        ]));

        $platformJob->refresh();
        $this->assertSame(PlatformJobStatus::Completed, $platformJob->status);
        $this->assertNull($platformJob->error);
        $this->assertDatabaseCount('jobs', 0);
        $this->assertSame(2, AcquisitionRun::query()
            ->where('organization_id', $organization->id)
            ->where('project_id', $project->id)
            ->count());
        $this->assertSame(1, AcquisitionRun::query()->where('status', 'failed')->count());
        $this->assertSame(1, AcquisitionRun::query()->where('status', 'completed')->count());
        $this->assertSame(['running', 'running'], $runStatusesWhenStarted);
        $this->assertSame(1, StoredEvent::query()
            ->where('event_type', 'acquisition.run_failed')
            ->count());
        $this->assertSame(1, StoredEvent::query()
            ->where('event_type', 'acquisition.run_completed')
            ->count());
    }

    private function enableAcquisition(string $organizationId, string $projectId): void
    {
        $flags = app(FeatureFlagService::class);

        foreach ([CapabilityGate::ACQUISITION, CapabilityGate::SOURCE_REGISTRY] as $key) {
            $flags->upsert(
                $key,
                true,
                FeatureFlagScope::Project,
                null,
                $organizationId,
                $projectId,
            );
        }
    }

    private function createSource(string $organizationId, string $projectId): Source
    {
        $feedUrl = 'http://93.184.216.34/retry.xml';

        return Source::create([
            'organization_id' => $organizationId,
            'project_id' => $projectId,
            'slug' => 'retry-feed',
            'name' => 'Retry Feed',
            'source_type' => 'rss',
            'base_url' => 'http://93.184.216.34',
            'feed_url' => $feedUrl,
            'feed_url_hash' => hash('sha256', $feedUrl),
            'allowed_domains' => ['93.184.216.34'],
            'enabled' => true,
            'manual_only' => false,
        ]);
    }

    private function rssBody(): string
    {
        return '<?xml version="1.0"?><rss version="2.0"><channel>'.
            '<title>Retry Feed</title><item><title>Retry succeeded</title>'.
            '<link>https://example.com/retry-succeeded</link>'.
            '<guid>retry-succeeded</guid></item></channel></rss>';
    }
}
