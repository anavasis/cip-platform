<?php

namespace Tests\Feature\Modules\Acquisition;

use App\Infrastructure\Persistence\Models\Project;
use App\Infrastructure\Persistence\Models\StoredEvent;
use App\Modules\Acquisition\Application\SourceAcquisitionService;
use App\Modules\Acquisition\Domain\Evidence\EvidenceRepositoryInterface;
use App\Modules\Acquisition\Infrastructure\Persistence\Models\Source;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EvidenceCapturedTest extends TestCase
{
    public function test_evidence_is_tenant_partitioned_and_emits_metadata_only_event(): void
    {
        Http::fake([
            'http://93.184.216.34/feed.xml' => Http::response(
                $this->rssBody(),
                200,
                ['Content-Type' => 'application/rss+xml'],
            ),
        ]);
        ['user' => $owner, 'organization' => $organization] = $this->createUserWithOrg();
        $project = $this->createProject($organization->id, $owner->id, 'Evidence');
        $otherProject = $this->createProject($organization->id, $owner->id, 'Other');
        $source = $this->createSource($organization->id, $project->id);

        $result = app(SourceAcquisitionService::class)->acquireFromSource(
            $organization->id,
            $project->id,
            $source->id,
            [
                'correlation_id' => 'correlation-123',
                'run_id' => 'run-456',
            ],
        );

        $this->assertTrue($result->success());
        $repository = app(EvidenceRepositoryInterface::class);
        $this->assertSame(1, $repository->count($organization->id, $project->id));
        $this->assertSame(0, $repository->count($organization->id, $otherProject->id));
        $summary = $repository->summaries($organization->id, $project->id)[0];
        $this->assertSame($organization->id, $summary['organization_id']);
        $this->assertSame($project->id, $summary['project_id']);
        $this->assertSame('correlation-123', $summary['correlation_id']);
        $this->assertSame('run-456', $summary['run_id']);

        $event = StoredEvent::query()
            ->where('event_type', 'evidence.captured')
            ->firstOrFail();
        $this->assertSame($organization->id, $event->payload['organization_id']);
        $this->assertSame($project->id, $event->payload['project_id']);
        $this->assertSame($source->id, $event->payload['source_id']);
        $this->assertSame('correlation-123', $event->payload['correlation_id']);
        $this->assertSame('run-456', $event->payload['run_id']);
        $this->assertArrayNotHasKey('body', $event->payload);
        $this->assertArrayNotHasKey('headers', $event->payload);
    }

    private function createProject(string $organizationId, string $userId, string $name): Project
    {
        return Project::create([
            'organization_id' => $organizationId,
            'name' => $name,
            'slug' => strtolower($name).'-'.uniqid(),
            'created_by' => $userId,
        ]);
    }

    private function createSource(string $organizationId, string $projectId): Source
    {
        $feedUrl = 'http://93.184.216.34/feed.xml';

        return Source::create([
            'organization_id' => $organizationId,
            'project_id' => $projectId,
            'slug' => 'evidence-feed',
            'name' => 'Evidence Feed',
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
            '<title>Evidence</title><item><title>Captured</title>'.
            '<link>https://example.com/captured</link><guid>captured</guid>'.
            '</item></channel></rss>';
    }
}
