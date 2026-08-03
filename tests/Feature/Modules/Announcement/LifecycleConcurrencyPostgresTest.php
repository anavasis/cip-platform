<?php

namespace Tests\Feature\Modules\Announcement;

use App\Infrastructure\Persistence\Models\Project;
use App\Modules\Acquisition\Infrastructure\Persistence\Models\Source;
use App\Modules\Announcement\Application\AnnouncementLifecycleService;
use App\Modules\Announcement\Domain\AnnouncementCandidate;
use App\Modules\Announcement\Domain\LifecycleOutcome;
use App\Modules\Announcement\Infrastructure\Persistence\Models\Announcement;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LifecycleConcurrencyPostgresTest extends TestCase
{
    /** @return array<int, string> */
    protected function connectionsToTransact(): array
    {
        $connection = (string) config('database.default');

        return config("database.connections.{$connection}.driver") === 'pgsql'
            ? []
            : [$connection];
    }

    public function test_concurrent_content_updates_increment_revision_under_row_lock(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Genuine lifecycle concurrency requires PostgreSQL.');
        }

        if (! function_exists('pcntl_fork') || ! function_exists('stream_socket_pair')) {
            $this->markTestSkipped('The PostgreSQL concurrency test requires pcntl and sockets.');
        }

        ['user' => $owner, 'organization' => $organization] = $this->createUserWithOrg();
        $project = Project::create([
            'organization_id' => $organization->id,
            'name' => 'PostgreSQL Concurrency',
            'slug' => 'postgres-concurrency-'.uniqid(),
            'created_by' => $owner->id,
        ]);
        $source = $this->createSource($organization->id, $project->id);
        $lifecycle = app(AnnouncementLifecycleService::class)
            ->forTenant($organization->id, $project->id);
        $created = $lifecycle->apply([$this->candidate($source->id, 'Version 1')]);
        $this->assertSame(LifecycleOutcome::NEW_ITEM, $created->decisions()[0]->outcome());

        $sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        $this->assertIsArray($sockets);
        $pid = pcntl_fork();
        $this->assertNotSame(-1, $pid);

        if ($pid === 0) {
            fclose($sockets[0]);
            fgets($sockets[1]);

            try {
                DB::disconnect();
                DB::reconnect();
                $result = app(AnnouncementLifecycleService::class)
                    ->forTenant($organization->id, $project->id)
                    ->apply([$this->candidate($source->id, 'Child update')]);
                fwrite($sockets[1], json_encode([
                    'success' => $result->success(),
                    'outcome' => $result->decisions()[0]->outcome(),
                    'revision' => $result->decisions()[0]->revisionNo(),
                ], JSON_THROW_ON_ERROR));
                fclose($sockets[1]);
                exit(0);
            } catch (\Throwable $throwable) {
                fwrite($sockets[1], json_encode([
                    'success' => false,
                    'error' => $throwable->getMessage(),
                ]));
                fclose($sockets[1]);
                exit(1);
            }
        }

        fclose($sockets[1]);
        fwrite($sockets[0], "go\n");
        fflush($sockets[0]);
        DB::disconnect();
        DB::reconnect();
        $parentResult = app(AnnouncementLifecycleService::class)
            ->forTenant($organization->id, $project->id)
            ->apply([$this->candidate($source->id, 'Parent update')]);
        $childPayload = stream_get_contents($sockets[0]);
        fclose($sockets[0]);
        pcntl_waitpid($pid, $status);

        $childResult = json_decode($childPayload, true, 512, JSON_THROW_ON_ERROR);
        $announcement = Announcement::query()
            ->where('project_id', $project->id)
            ->where('source_id', $source->id)
            ->sole();
        $finalRevision = $announcement->revision_no;
        $announcementCount = Announcement::query()
            ->where('project_id', $project->id)
            ->where('source_id', $source->id)
            ->count();
        $revisions = [
            $parentResult->decisions()[0]->revisionNo(),
            (int) $childResult['revision'],
        ];
        sort($revisions);

        $organization->delete();
        $owner->delete();

        $this->assertTrue(pcntl_wifexited($status));
        $this->assertSame(0, pcntl_wexitstatus($status), (string) ($childResult['error'] ?? ''));
        $this->assertTrue($parentResult->success());
        $this->assertTrue((bool) $childResult['success']);
        $this->assertSame(LifecycleOutcome::UPDATED, $parentResult->decisions()[0]->outcome());
        $this->assertSame(LifecycleOutcome::UPDATED, $childResult['outcome']);
        $this->assertSame([2, 3], $revisions);
        $this->assertSame(3, $finalRevision);
        $this->assertSame(1, $announcementCount);
    }

    private function candidate(string $sourceId, string $title): AnnouncementCandidate
    {
        return new AnnouncementCandidate([
            'source_id' => $sourceId,
            'title' => $title,
            'canonical_url' => 'https://example.com/concurrent-item',
            'source_guid' => 'concurrent-item',
            'published_at_utc' => '2026-08-03 08:00:00',
            'raw_payload' => ['title' => $title],
        ]);
    }

    private function createSource(string $organizationId, string $projectId): Source
    {
        $feedUrl = 'https://example.com/postgres-concurrent.xml';

        return Source::create([
            'organization_id' => $organizationId,
            'project_id' => $projectId,
            'slug' => 'postgres-concurrent',
            'name' => 'PostgreSQL Concurrent Feed',
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
