<?php

namespace Tests\Feature\Modules\Acquisition;

use App\Application\Services\JobEngineService;
use App\Domain\Shared\Enums\PlatformJobStatus;
use App\Infrastructure\Persistence\Models\Project;
use App\Modules\Acquisition\Infrastructure\Jobs\AcquireSourceJob;
use App\Modules\Acquisition\Infrastructure\Jobs\IngestSourceJob;
use App\Modules\Acquisition\Infrastructure\Persistence\Models\Source;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AcquisitionJobTest extends TestCase
{
    public function test_acquire_and_ingest_jobs_complete_via_sync_queue(): void
    {
        Http::fakeSequence()
            ->push($this->rssBody(), 200, ['Content-Type' => 'application/rss+xml'])
            ->push($this->rssBody(), 200, ['Content-Type' => 'application/rss+xml']);
        ['user' => $owner, 'organization' => $organization] = $this->createUserWithOrg();
        $project = Project::create([
            'organization_id' => $organization->id,
            'name' => 'Jobs Project',
            'slug' => 'jobs-project',
            'created_by' => $owner->id,
        ]);
        $source = $this->createSource($organization->id, $project->id);
        $engine = app(JobEngineService::class);
        $payload = [
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'source_id' => $source->id,
            'trigger' => 'test',
        ];

        $acquisitionJob = $engine->create(
            'acquisition.acquire_source',
            $organization->id,
            $project->id,
            $payload,
        );
        AcquireSourceJob::dispatch($acquisitionJob->id);
        $acquisitionJob->refresh();

        $this->assertSame(PlatformJobStatus::Completed, $acquisitionJob->status);
        $this->assertSame(1, $acquisitionJob->result['sources_succeeded']);
        $this->assertDatabaseHas('acquisition_runs', [
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'status' => 'completed',
            'sources_succeeded' => 1,
        ]);

        $ingestionJob = $engine->create(
            'announcement.ingest_source',
            $organization->id,
            $project->id,
            $payload,
        );
        IngestSourceJob::dispatch($ingestionJob->id);
        $ingestionJob->refresh();

        $this->assertSame(
            PlatformJobStatus::Completed,
            $ingestionJob->status,
            (string) $ingestionJob->error,
        );
        $this->assertSame(1, $ingestionJob->result['new_count']);
        $this->assertDatabaseHas('announcements', [
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'source_id' => $source->id,
            'raw_title' => 'Job announcement',
            'revision_no' => 1,
        ]);
        Http::assertSentCount(2);
    }

    private function createSource(string $organizationId, string $projectId): Source
    {
        $feedUrl = 'http://93.184.216.34/feed.xml';

        return Source::create([
            'organization_id' => $organizationId,
            'project_id' => $projectId,
            'slug' => 'job-feed',
            'name' => 'Job Feed',
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
            '<title>Job Feed</title><item><title>Job announcement</title>'.
            '<link>https://example.com/job-announcement</link>'.
            '<guid>job-announcement</guid><pubDate>Mon, 03 Aug 2026 08:00:00 +0000</pubDate>'.
            '</item></channel></rss>';
    }
}
