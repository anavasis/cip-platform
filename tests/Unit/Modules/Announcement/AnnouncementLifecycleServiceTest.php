<?php

namespace Tests\Unit\Modules\Announcement;

use App\Modules\Announcement\Application\AnnouncementLifecycleService;
use App\Modules\Announcement\Domain\AnnouncementCandidate;
use App\Modules\Announcement\Domain\AnnouncementIdentityService;
use App\Modules\Announcement\Domain\AnnouncementRepositoryInterface;
use App\Modules\Announcement\Domain\LifecycleOutcome;
use Tests\TestCase;

class AnnouncementLifecycleServiceTest extends TestCase
{
    public function test_new_unchanged_updated_and_intra_batch_duplicate_lifecycle(): void
    {
        $repository = new InMemoryAnnouncementRepository;
        $service = (new AnnouncementLifecycleService(
            $repository,
            new AnnouncementIdentityService,
        ))->forTenant(
            '0198-1111-7222-8333-000000000001',
            '0198-1111-7222-8333-000000000002',
        );
        $sourceId = '0198-1111-7222-8333-000000000003';
        $original = $this->candidate($sourceId, 'Alpha');

        $new = $service->apply([$original]);
        $this->assertTrue($new->success());
        $this->assertSame(1, $new->newCount());
        $this->assertSame(LifecycleOutcome::NEW_ITEM, $new->decisions()[0]->outcome());
        $this->assertSame(1, $new->decisions()[0]->revisionNo());
        $this->assertSame(1, $repository->insertCalls);

        $unchanged = $service->apply([$original]);
        $this->assertSame(1, $unchanged->unchangedCount());
        $this->assertSame(LifecycleOutcome::UNCHANGED, $unchanged->decisions()[0]->outcome());
        $this->assertSame(1, $repository->markUnchangedCalls);

        $revised = $this->candidate($sourceId, 'Alpha revised');
        $updated = $service->apply([$revised]);
        $this->assertSame(1, $updated->updatedCount());
        $this->assertSame(LifecycleOutcome::UPDATED, $updated->decisions()[0]->outcome());
        $this->assertSame(2, $updated->decisions()[0]->revisionNo());
        $this->assertSame(1, $repository->contentUpdateCalls);

        $duplicate = $service->apply([$revised, $revised]);
        $this->assertSame(1, $duplicate->unchangedCount());
        $this->assertSame(1, $duplicate->duplicateCount());
        $this->assertSame(LifecycleOutcome::DUPLICATE, $duplicate->decisions()[1]->outcome());
        $this->assertSame(0, $duplicate->decisions()[1]->revisionNo());
        $this->assertSame(1, $repository->insertCalls, 'An intra-batch duplicate must not be inserted.');
    }

    public function test_tenant_context_is_required(): void
    {
        $result = (new AnnouncementLifecycleService(
            new InMemoryAnnouncementRepository,
            new AnnouncementIdentityService,
        ))->apply([$this->candidate('0198-1111-7222-8333-000000000003', 'Alpha')]);

        $this->assertFalse($result->success());
        $this->assertSame('tenant_context_missing', $result->errorCode());
    }

    public function test_unique_insert_race_is_re_read_and_classified_unchanged(): void
    {
        $repository = new InMemoryAnnouncementRepository;
        $repository->simulateInsertConflict = true;
        $service = (new AnnouncementLifecycleService(
            $repository,
            new AnnouncementIdentityService,
        ))->forTenant('org-1', 'project-1');

        $result = $service->apply([$this->candidate('source-1', 'Alpha')]);

        $this->assertTrue($result->success());
        $this->assertSame(0, $result->newCount());
        $this->assertSame(1, $result->unchangedCount());
        $this->assertSame(LifecycleOutcome::UNCHANGED, $result->decisions()[0]->outcome());
        $this->assertSame(1, $repository->markUnchangedCalls);
    }

    public function test_unique_insert_race_is_re_read_and_classified_updated(): void
    {
        $repository = new InMemoryAnnouncementRepository;
        $repository->simulateInsertConflict = true;
        $repository->conflictHasDifferentContent = true;
        $service = (new AnnouncementLifecycleService(
            $repository,
            new AnnouncementIdentityService,
        ))->forTenant('org-1', 'project-1');

        $result = $service->apply([$this->candidate('source-1', 'Alpha')]);

        $this->assertTrue($result->success());
        $this->assertSame(0, $result->newCount());
        $this->assertSame(1, $result->updatedCount());
        $this->assertSame(LifecycleOutcome::UPDATED, $result->decisions()[0]->outcome());
        $this->assertSame(2, $result->decisions()[0]->revisionNo());
    }

    private function candidate(string $sourceId, string $title): AnnouncementCandidate
    {
        return new AnnouncementCandidate([
            'source_id' => $sourceId,
            'title' => $title,
            'canonical_url' => 'https://example.com/alpha',
            'source_guid' => 'alpha-guid',
            'published_at_utc' => '2026-08-03 08:00:00',
            'raw_payload' => ['title' => $title],
        ]);
    }
}

final class InMemoryAnnouncementRepository implements AnnouncementRepositoryInterface
{
    /** @var array<string, array<string, mixed>> */
    private array $rows = [];

    private string $lastId = '';

    public int $insertCalls = 0;

    public int $markUnchangedCalls = 0;

    public int $contentUpdateCalls = 0;

    public bool $simulateInsertConflict = false;

    public bool $conflictHasDifferentContent = false;

    public function insert(array $data): bool
    {
        $this->insertCalls++;
        $this->lastId = sprintf('0198-1111-7222-8333-%012d', count($this->rows) + 10);
        $data['id'] = $this->lastId;
        $this->rows[$this->lastId] = $data;

        if ($this->simulateInsertConflict) {
            if ($this->conflictHasDifferentContent) {
                $this->rows[$this->lastId]['content_hash'] = hash('sha256', 'concurrent-content');
            }

            return false;
        }

        return true;
    }

    public function findBySourceAndIdentityHash(
        string $organizationId,
        string $projectId,
        string $sourceId,
        string $identityHash,
    ): ?array {
        foreach ($this->rows as $row) {
            if (
                $row['organization_id'] === $organizationId
                && $row['project_id'] === $projectId
                && $row['source_id'] === $sourceId
                && $row['identity_hash'] === $identityHash
            ) {
                return $row;
            }
        }

        return null;
    }

    public function lastInsertId(): string
    {
        return $this->lastId;
    }

    public function markUnchanged(
        string $organizationId,
        string $projectId,
        string $sourceId,
        string $itemId,
        string $lastSeenAtUtc,
        string $updatedAtUtc,
    ): bool {
        $this->markUnchangedCalls++;
        if (($this->rows[$itemId]['organization_id'] ?? null) !== $organizationId
            || ($this->rows[$itemId]['project_id'] ?? null) !== $projectId
            || ($this->rows[$itemId]['source_id'] ?? null) !== $sourceId) {
            return false;
        }

        $this->rows[$itemId]['last_seen_at_utc'] = $lastSeenAtUtc;
        $this->rows[$itemId]['updated_at_utc'] = $updatedAtUtc;

        return true;
    }

    public function applyContentUpdate(
        string $organizationId,
        string $projectId,
        string $sourceId,
        string $itemId,
        array $data,
    ): int|false {
        $this->contentUpdateCalls++;
        if (($this->rows[$itemId]['organization_id'] ?? null) !== $organizationId
            || ($this->rows[$itemId]['project_id'] ?? null) !== $projectId
            || ($this->rows[$itemId]['source_id'] ?? null) !== $sourceId) {
            return false;
        }

        $data['revision_no'] = (int) ($this->rows[$itemId]['revision_no'] ?? 1) + 1;
        $this->rows[$itemId] = array_merge($this->rows[$itemId], $data);

        return $data['revision_no'];
    }

    public function findPage(string $organizationId, string $projectId, array $criteria): array
    {
        return [];
    }

    public function findById(string $organizationId, string $projectId, string $itemId): array
    {
        return $this->rows[$itemId] ?? [];
    }

    public function findEditorialSummary(string $organizationId, string $projectId): array
    {
        return [];
    }
}
