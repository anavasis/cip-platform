<?php

namespace Tests\Feature\Modules\Announcement;

use App\Infrastructure\Persistence\Models\Project;
use App\Modules\Acquisition\Infrastructure\Persistence\Models\Source;
use App\Modules\Announcement\Application\AnnouncementLifecycleService;
use App\Modules\Announcement\Domain\AnnouncementCandidate;
use App\Modules\Announcement\Domain\AnnouncementIdentityService;
use App\Modules\Announcement\Domain\LifecycleOutcome;
use App\Modules\Announcement\Infrastructure\Persistence\Models\Announcement;
use App\Modules\Announcement\Infrastructure\Persistence\Repositories\EloquentAnnouncementRepository;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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

    public function test_long_source_guid_persists_with_null_column_and_full_payload_guid(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Long source_guid compatibility requires PostgreSQL.');
        }

        ['user' => $owner, 'organization' => $organization] = $this->createUserWithOrg();
        $project = Project::create([
            'organization_id' => $organization->id,
            'name' => 'PostgreSQL Long GUID',
            'slug' => 'postgres-long-guid-'.uniqid(),
            'created_by' => $owner->id,
        ]);
        $source = $this->createSource($organization->id, $project->id, 'postgres-long-guid');
        $longGuid = $this->mineduStyleLongGuid();
        $this->assertGreaterThan(255, mb_strlen($longGuid, 'UTF-8'));

        $lifecycle = app(AnnouncementLifecycleService::class)
            ->forTenant($organization->id, $project->id);
        $result = $lifecycle->apply([
            $this->candidate($source->id, 'MinEdu Long GUID', $longGuid, $longGuid),
        ]);

        $this->assertTrue($result->success());
        $this->assertSame(1, $result->newCount());
        $this->assertSame(LifecycleOutcome::NEW_ITEM, $result->decisions()[0]->outcome());

        $announcement = Announcement::query()
            ->where('project_id', $project->id)
            ->where('source_id', $source->id)
            ->sole();

        $this->assertNull($announcement->source_guid);
        $this->assertSame($longGuid, $announcement->canonical_url);
        $this->assertSame($longGuid, $announcement->raw_payload['guid'] ?? null);

        $organization->delete();
        $owner->delete();
    }

    public function test_normal_source_guid_persists_when_within_limit(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Normal source_guid persistence requires PostgreSQL.');
        }

        ['user' => $owner, 'organization' => $organization] = $this->createUserWithOrg();
        $project = Project::create([
            'organization_id' => $organization->id,
            'name' => 'PostgreSQL Normal GUID',
            'slug' => 'postgres-normal-guid-'.uniqid(),
            'created_by' => $owner->id,
        ]);
        $source = $this->createSource($organization->id, $project->id, 'postgres-normal-guid');
        $shortGuid = 'https://www.minedu.gov.gr/?view=article&id=70628:13-08-26&catid=1183';

        $lifecycle = app(AnnouncementLifecycleService::class)
            ->forTenant($organization->id, $project->id);
        $result = $lifecycle->apply([
            $this->candidate($source->id, 'MinEdu Short GUID', $shortGuid, $shortGuid),
        ]);

        $this->assertTrue($result->success());
        $announcement = Announcement::query()
            ->where('project_id', $project->id)
            ->where('source_id', $source->id)
            ->sole();

        $this->assertSame($shortGuid, $announcement->source_guid);
        $this->assertSame($shortGuid, $announcement->canonical_url);
        $this->assertSame($shortGuid, $announcement->raw_payload['guid'] ?? null);

        $organization->delete();
        $owner->delete();
    }

    public function test_real_database_error_propagates_without_aborted_transaction_mask(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL error propagation requires PostgreSQL.');
        }

        ['user' => $owner, 'organization' => $organization] = $this->createUserWithOrg();
        $project = Project::create([
            'organization_id' => $organization->id,
            'name' => 'PostgreSQL FK Failure',
            'slug' => 'postgres-fk-failure-'.uniqid(),
            'created_by' => $owner->id,
        ]);
        $missingSourceId = (string) Str::uuid();
        $lifecycle = app(AnnouncementLifecycleService::class)
            ->forTenant($organization->id, $project->id);

        $caught = null;

        try {
            $lifecycle->apply([
                $this->candidate($missingSourceId, 'Missing Source', 'https://example.com/missing-source'),
            ]);
            $this->fail('Expected QueryException for missing source foreign key.');
        } catch (QueryException $exception) {
            $caught = $exception;
        }

        $this->assertInstanceOf(QueryException::class, $caught);
        $this->assertStringNotContainsString('25P02', (string) $caught->getMessage());
        $this->assertMatchesRegularExpression('/foreign key|23503/i', (string) $caught->getMessage());
        $this->assertSame(
            0,
            Announcement::query()->where('project_id', $project->id)->count(),
        );

        $organization->delete();
        $owner->delete();
    }

    public function test_insert_or_ignore_conflict_returns_false_without_exception(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('insertOrIgnore conflict handling requires PostgreSQL.');
        }

        ['user' => $owner, 'organization' => $organization] = $this->createUserWithOrg();
        $project = Project::create([
            'organization_id' => $organization->id,
            'name' => 'PostgreSQL Insert Conflict',
            'slug' => 'postgres-insert-conflict-'.uniqid(),
            'created_by' => $owner->id,
        ]);
        $source = $this->createSource($organization->id, $project->id, 'postgres-insert-conflict');
        $identity = new AnnouncementIdentityService;
        $candidate = $this->candidate(
            $source->id,
            'Conflict Item',
            'https://example.com/conflict-item',
            'conflict-item-guid',
        );
        $now = gmdate('Y-m-d H:i:s');
        $insertData = [
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'source_id' => $source->id,
            'identity_hash' => $identity->identityHash($candidate->canonicalUrl()),
            'identity_basis' => $identity->identityBasis(),
            'source_guid' => $candidate->sourceGuid(),
            'canonical_url' => $candidate->canonicalUrl(),
            'source_published_at_utc' => $candidate->publishedAtUtc(),
            'raw_title' => $candidate->title(),
            'content_hash' => $identity->contentHash($candidate),
            'raw_payload' => $candidate->rawPayload(),
            'revision_no' => 1,
            'first_seen_at_utc' => $now,
            'last_seen_at_utc' => $now,
            'created_at_utc' => $now,
            'updated_at_utc' => $now,
        ];

        $repository = app(EloquentAnnouncementRepository::class);

        $this->assertTrue($repository->insert($insertData));
        $this->assertFalse($repository->insert($insertData));

        $organization->delete();
        $owner->delete();
    }

    private function mineduStyleLongGuid(): string
    {
        return 'https://www.minedu.gov.gr/?view=article&id=54731:prosklisi-ypopsifion-ekpaideftikon-genikis-ekpaidefsis-gia-ypovoli-dikaiologitikon-sto-olokliromeno-pliroforiako-systima-diaxeirisis-prosopikoy-protovathmias-kai-defterovathmias-ekpaidefsis-o-p-sy-d-tou-ypourgeiou-paideias-kai-thriskevmaton-sto-plaisio-ton-arithm-1ge-2023-kai-2ge-2023-prokirykseon-tou-asep-i-ypovoli-dikaiologit&catid=2017';
    }

    private function candidate(
        string $sourceId,
        string $title,
        string $canonicalUrl = 'https://example.com/concurrent-item',
        ?string $sourceGuid = 'concurrent-item',
    ): AnnouncementCandidate {
        return new AnnouncementCandidate([
            'source_id' => $sourceId,
            'title' => $title,
            'canonical_url' => $canonicalUrl,
            'source_guid' => $sourceGuid ?? '',
            'published_at_utc' => '2026-08-03 08:00:00',
            'raw_payload' => [
                'title' => $title,
                'guid' => $sourceGuid ?? '',
                'link' => $canonicalUrl,
            ],
        ]);
    }

    private function createSource(string $organizationId, string $projectId, string $slug = 'postgres-concurrent'): Source
    {
        $feedUrl = 'https://example.com/postgres-concurrent.xml';

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
