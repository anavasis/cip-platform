<?php

namespace Tests\Feature\Modules\Announcement;

use App\Application\Services\ConfigurationService;
use App\Infrastructure\Persistence\Models\Project;
use App\Modules\Acquisition\Application\AcquisitionDiagnostics;
use App\Modules\Acquisition\Infrastructure\Persistence\Models\Source;
use App\Modules\Announcement\Application\EditorialIngestionService;
use App\Modules\Announcement\Domain\AnnouncementCandidate;
use App\Modules\Intelligence\Application\ContentIntelligencePlanner;
use App\Modules\Intelligence\Application\EntityBindingService;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class EntityBindingIngestionIsolationTest extends TestCase
{
    public function test_ingestion_succeeds_when_entity_binding_throws(): void
    {
        ['user' => $owner, 'organization' => $organization] = $this->createUserWithOrg();
        $project = $this->createProject($organization->id, $owner->id, 'Binding Isolation Project');
        $source = $this->createSource($organization->id, $project->id, 'binding-isolation-source');
        $this->seedProfile($organization->id, $project->id);

        $this->mockConfigurationServiceForBindingFailures(static function (): void {
            throw new RuntimeException('Synthetic binding failure');
        });

        $ingestion = app(EditorialIngestionService::class)->forTenant($organization->id, $project->id);
        $result = $ingestion->ingestCandidates([
            $this->candidate($source->id, 'ΑΣΕΠ 6Κ/2026', 'https://example.com/binding-failure'),
        ]);

        $this->assertTrue($result->success());
        $this->assertSame(1, $result->newCount());
        $this->assertSame(0, $result->updatedCount());
        $this->assertSame(0, $result->unchangedCount());
        $this->assertSame(0, $result->duplicateCount());
        $this->assertDatabaseHas('announcements', [
            'project_id' => $project->id,
            'source_id' => $source->id,
            'raw_title' => 'ΑΣΕΠ 6Κ/2026',
            'revision_no' => 1,
        ]);

        $lastIngestion = $this->lastIngestionDiagnostics($organization->id, $project->id);
        $this->assertSame(1, $lastIngestion['entity_binding_failure_count']);
        $this->assertCount(1, $lastIngestion['entity_binding_failures']);
        $this->assertSame(RuntimeException::class, $lastIngestion['entity_binding_failures'][0]['exception']);
        $this->assertSame('Synthetic binding failure', $lastIngestion['entity_binding_failures'][0]['message']);
    }

    public function test_one_binding_failure_does_not_abort_remaining_announcements(): void
    {
        ['user' => $owner, 'organization' => $organization] = $this->createUserWithOrg();
        $project = $this->createProject($organization->id, $owner->id, 'Batch Isolation Project');
        $source = $this->createSource($organization->id, $project->id, 'batch-isolation-source');
        $this->seedProfile($organization->id, $project->id);

        $bindingAttempts = 0;
        $this->mockConfigurationServiceForBindingFailures(static function () use (&$bindingAttempts): void {
            $bindingAttempts++;

            if ($bindingAttempts === 1) {
                throw new RuntimeException('Synthetic binding failure');
            }
        });

        $ingestion = app(EditorialIngestionService::class)->forTenant($organization->id, $project->id);
        $result = $ingestion->ingestCandidates([
            $this->candidate($source->id, 'ΑΣΕΠ 6Κ/2026', 'https://example.com/batch-first'),
            $this->candidate($source->id, 'ΑΣΕΠ 6Κ/2026', 'https://example.com/batch-second'),
        ]);

        $this->assertTrue($result->success());
        $this->assertSame(2, $result->newCount());
        $this->assertSame(2, $bindingAttempts);
        $this->assertDatabaseCount('announcements', 2);

        $lastIngestion = $this->lastIngestionDiagnostics($organization->id, $project->id);
        $this->assertSame(1, $lastIngestion['entity_binding_failure_count']);
        $this->assertCount(1, $lastIngestion['entity_binding_failures']);
    }

    public function test_binding_failure_metadata_does_not_leak_between_ingestions(): void
    {
        ['user' => $owner, 'organization' => $organization] = $this->createUserWithOrg();
        $project = $this->createProject($organization->id, $owner->id, 'Reset Isolation Project');
        $source = $this->createSource($organization->id, $project->id, 'reset-isolation-source');
        $this->seedProfile($organization->id, $project->id);

        $bindingAttempts = 0;
        $this->mockConfigurationServiceForBindingFailures(static function () use (&$bindingAttempts): void {
            $bindingAttempts++;

            if ($bindingAttempts === 1) {
                throw new RuntimeException('Synthetic binding failure');
            }
        });

        $ingestion = app(EditorialIngestionService::class)->forTenant($organization->id, $project->id);

        $first = $ingestion->ingestCandidates([
            $this->candidate($source->id, 'ΑΣΕΠ 6Κ/2026', 'https://example.com/reset-first'),
        ]);
        $this->assertTrue($first->success());

        $failedIngestion = $this->lastIngestionDiagnostics($organization->id, $project->id);
        $this->assertSame(1, $failedIngestion['entity_binding_failure_count']);

        $second = $ingestion->ingestCandidates([
            $this->candidate($source->id, 'ΑΣΕΠ 6Κ/2026', 'https://example.com/reset-second'),
        ]);
        $this->assertTrue($second->success());
        $this->assertSame(2, $bindingAttempts);

        $cleanIngestion = $this->lastIngestionDiagnostics($organization->id, $project->id);
        $this->assertSame(0, $cleanIngestion['entity_binding_failure_count']);
        $this->assertArrayNotHasKey('entity_binding_failures', $cleanIngestion);
    }

    /**
     * @param  callable(): void  $onBindingProfileRead
     */
    private function mockConfigurationServiceForBindingFailures(callable $onBindingProfileRead): void
    {
        $real = app(ConfigurationService::class);
        $mock = Mockery::mock($real)->makePartial();

        $mock->shouldReceive('get')->andReturnUsing(
            function (string $organizationId, string $key, ?string $projectId = null) use ($real, $onBindingProfileRead) {
                if ($key === ContentIntelligencePlanner::PROFILE_KEY && $this->isEntityBindingServiceBindContext()) {
                    $onBindingProfileRead();
                }

                return $real->get($organizationId, $key, $projectId);
            },
        );

        $this->app->instance(ConfigurationService::class, $mock);
    }

    private function isEntityBindingServiceBindContext(): bool
    {
        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 25) as $frame) {
            if (($frame['class'] ?? '') === EntityBindingService::class
                && ($frame['function'] ?? '') === 'bindAnnouncement') {
                return true;
            }
        }

        return false;
    }

    private function seedProfile(string $organizationId, string $projectId): void
    {
        app(ConfigurationService::class)->set(
            $organizationId,
            ContentIntelligencePlanner::PROFILE_KEY,
            ['value' => $this->satelliteProfile()],
            $projectId,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function satelliteProfile(): array
    {
        return [
            'version' => 1,
            'publishing_mode' => 'plan_only',
            'primary_domain' => 'example.test',
            'entity_rules' => [
                [
                    'entity_id' => 'entity-a-2026',
                    'label' => 'Entity A 2026',
                    'patterns' => ['6\\s*[ΚK]\\s*\\/\\s*2026'],
                    'match_scope' => 'title',
                    'content_role' => 'satellite',
                    'canonical_target_url' => 'https://example.test/entity-a-2026',
                    'seo' => ['slug' => 'entity-a-2026'],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function lastIngestionDiagnostics(string $organizationId, string $projectId): array
    {
        $status = app(AcquisitionDiagnostics::class)->status($organizationId, $projectId);
        $lastIngestion = $status['last_ingestion'] ?? null;

        $this->assertIsArray($lastIngestion);

        return $lastIngestion;
    }

    private function candidate(string $sourceId, string $title, string $canonicalUrl): AnnouncementCandidate
    {
        return new AnnouncementCandidate([
            'source_id' => $sourceId,
            'title' => $title,
            'canonical_url' => $canonicalUrl,
            'source_guid' => $title.'-guid',
            'published_at_utc' => '2026-08-03 08:00:00',
            'raw_payload' => ['title' => $title],
        ]);
    }

    private function createProject(string $organizationId, string $userId, string $name): Project
    {
        return Project::create([
            'organization_id' => $organizationId,
            'name' => $name,
            'slug' => strtolower(str_replace(' ', '-', $name)).'-'.uniqid(),
            'created_by' => $userId,
        ]);
    }

    private function createSource(string $organizationId, string $projectId, string $slug): Source
    {
        $feedUrl = "https://example.com/{$slug}.xml";

        return Source::create([
            'organization_id' => $organizationId,
            'project_id' => $projectId,
            'slug' => $slug,
            'name' => $slug,
            'source_type' => 'rss',
            'base_url' => 'https://example.com',
            'feed_url' => $feedUrl,
            'feed_url_hash' => hash('sha256', $feedUrl),
            'allowed_domains' => ['example.com'],
            'enabled' => true,
            'manual_only' => false,
        ]);
    }
}
