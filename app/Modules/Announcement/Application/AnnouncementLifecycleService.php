<?php

namespace App\Modules\Announcement\Application;

use App\Modules\Announcement\Domain\AnnouncementCandidate;
use App\Modules\Announcement\Domain\AnnouncementIdentityService;
use App\Modules\Announcement\Domain\AnnouncementRepositoryInterface;
use App\Modules\Announcement\Domain\LifecycleBatchResult;
use App\Modules\Announcement\Domain\LifecycleDecision;
use App\Modules\Announcement\Domain\LifecycleOutcome;

/**
 * Announcement lifecycle decision and persistence spine.
 */
final class AnnouncementLifecycleService
{
    private ?LifecycleBatchResult $lastBatchResult = null;

    private string $organizationId = '';

    private string $projectId = '';

    public function __construct(
        private readonly AnnouncementRepositoryInterface $repository,
        private readonly AnnouncementIdentityService $identityService,
    ) {}

    public function forTenant(string $organizationId, string $projectId): self
    {
        $this->organizationId = trim($organizationId);
        $this->projectId = trim($projectId);

        return $this;
    }

    /**
     * @param  array<int, AnnouncementCandidate>  $candidates
     */
    public function apply(array $candidates): LifecycleBatchResult
    {
        if ($this->organizationId === '' || $this->projectId === '') {
            return $this->remember(new LifecycleBatchResult([
                'success' => false,
                'error_code' => 'tenant_context_missing',
                'source_id' => '',
                'candidates' => count($candidates),
                'decisions' => [],
            ]));
        }

        $sourceId = '';
        $decisions = [];
        $seenIdentities = [];
        $newCount = 0;
        $updatedCount = 0;
        $unchangedCount = 0;
        $duplicateCount = 0;
        $now = $this->utcNow();

        foreach ($candidates as $candidate) {
            if (! $candidate instanceof AnnouncementCandidate) {
                continue;
            }

            if ($sourceId === '') {
                $sourceId = $candidate->sourceId();
            }

            $identityHash = $this->identityService->identityHash($candidate->canonicalUrl());
            $contentHash = $this->identityService->contentHash($candidate);

            if ($identityHash === '' || $contentHash === '') {
                continue;
            }

            if (isset($seenIdentities[$identityHash])) {
                $duplicateCount++;
                $decisions[] = new LifecycleDecision([
                    'outcome' => LifecycleOutcome::DUPLICATE,
                    'source_id' => $candidate->sourceId(),
                    'identity_hash' => $identityHash,
                    'content_hash' => $contentHash,
                    'revision_no' => 0,
                    'item_id' => '',
                    'title' => $candidate->title(),
                ]);

                continue;
            }

            $seenIdentities[$identityHash] = true;
            $existing = $this->repository->findBySourceAndIdentityHash(
                $this->organizationId,
                $this->projectId,
                $candidate->sourceId(),
                $identityHash,
            );

            if ($existing === null) {
                $inserted = $this->repository->insert([
                    'organization_id' => $this->organizationId,
                    'project_id' => $this->projectId,
                    'source_id' => $candidate->sourceId(),
                    'identity_hash' => $identityHash,
                    'identity_basis' => $this->identityService->identityBasis(),
                    'source_guid' => $candidate->sourceGuid() !== '' ? $candidate->sourceGuid() : null,
                    'canonical_url' => $candidate->canonicalUrl(),
                    'source_published_at_utc' => $candidate->publishedAtUtc() !== ''
                        ? $candidate->publishedAtUtc()
                        : null,
                    'raw_title' => $candidate->title(),
                    'content_hash' => $contentHash,
                    'raw_payload' => $this->encodePayload($candidate->rawPayload()),
                    'revision_no' => 1,
                    'first_seen_at_utc' => $now,
                    'last_seen_at_utc' => $now,
                    'created_at_utc' => $now,
                    'updated_at_utc' => $now,
                ]);

                if ($inserted !== true) {
                    return $this->remember(new LifecycleBatchResult([
                        'success' => false,
                        'error_code' => 'persist_failed',
                        'source_id' => $candidate->sourceId(),
                        'candidates' => count($candidates),
                        'new_count' => $newCount,
                        'updated_count' => $updatedCount,
                        'unchanged_count' => $unchangedCount,
                        'duplicate_count' => $duplicateCount,
                        'decisions' => $decisions,
                    ]));
                }

                $newCount++;
                $decisions[] = new LifecycleDecision([
                    'outcome' => LifecycleOutcome::NEW_ITEM,
                    'source_id' => $candidate->sourceId(),
                    'identity_hash' => $identityHash,
                    'content_hash' => $contentHash,
                    'revision_no' => 1,
                    'item_id' => $this->repository->lastInsertId(),
                    'title' => $candidate->title(),
                ]);

                continue;
            }

            $existingContentHash = isset($existing['content_hash'])
                ? (string) $existing['content_hash']
                : '';
            $itemId = isset($existing['id']) ? (string) $existing['id'] : '';
            $revisionNo = isset($existing['revision_no']) ? (int) $existing['revision_no'] : 1;

            if ($existingContentHash !== '' && $existingContentHash === $contentHash) {
                $this->repository->markUnchanged($itemId, $now, $now);
                $unchangedCount++;
                $decisions[] = new LifecycleDecision([
                    'outcome' => LifecycleOutcome::UNCHANGED,
                    'source_id' => $candidate->sourceId(),
                    'identity_hash' => $identityHash,
                    'content_hash' => $contentHash,
                    'revision_no' => $revisionNo,
                    'item_id' => $itemId,
                    'title' => $candidate->title(),
                ]);

                continue;
            }

            $nextRevision = $revisionNo + 1;
            $this->repository->applyContentUpdate($itemId, [
                'source_guid' => $candidate->sourceGuid() !== '' ? $candidate->sourceGuid() : null,
                'canonical_url' => $candidate->canonicalUrl(),
                'source_published_at_utc' => $candidate->publishedAtUtc() !== ''
                    ? $candidate->publishedAtUtc()
                    : null,
                'raw_title' => $candidate->title(),
                'content_hash' => $contentHash,
                'raw_payload' => $this->encodePayload($candidate->rawPayload()),
                'revision_no' => $nextRevision,
                'last_seen_at_utc' => $now,
                'updated_at_utc' => $now,
            ]);
            $updatedCount++;
            $decisions[] = new LifecycleDecision([
                'outcome' => LifecycleOutcome::UPDATED,
                'source_id' => $candidate->sourceId(),
                'identity_hash' => $identityHash,
                'content_hash' => $contentHash,
                'revision_no' => $nextRevision,
                'item_id' => $itemId,
                'title' => $candidate->title(),
            ]);
        }

        return $this->remember(new LifecycleBatchResult([
            'success' => true,
            'error_code' => '',
            'source_id' => $sourceId,
            'candidates' => count($candidates),
            'new_count' => $newCount,
            'updated_count' => $updatedCount,
            'unchanged_count' => $unchangedCount,
            'duplicate_count' => $duplicateCount,
            'decisions' => $decisions,
        ]));
    }

    public function lastBatchResult(): ?LifecycleBatchResult
    {
        return $this->lastBatchResult;
    }

    /**
     * @return array<string, mixed>
     */
    public function diagnosticsStatus(): array
    {
        $last = $this->lastBatchResult?->toArray();

        if (is_array($last) && isset($last['decisions']) && is_array($last['decisions'])) {
            $summaryDecisions = [];

            foreach ($last['decisions'] as $decision) {
                if (! is_array($decision)) {
                    continue;
                }

                $summaryDecisions[] = [
                    'outcome' => isset($decision['outcome']) ? (string) $decision['outcome'] : '',
                    'source_id' => isset($decision['source_id']) ? (string) $decision['source_id'] : '',
                    'identity_hash' => isset($decision['identity_hash'])
                        ? (string) $decision['identity_hash']
                        : '',
                    'revision_no' => isset($decision['revision_no']) ? (int) $decision['revision_no'] : 0,
                    'item_id' => isset($decision['item_id']) ? (string) $decision['item_id'] : '',
                ];
            }

            $last['decisions'] = $summaryDecisions;
        }

        return [
            'status' => 'ready',
            'store' => 'announcements',
            'last_batch' => $last,
        ];
    }

    private function remember(LifecycleBatchResult $result): LifecycleBatchResult
    {
        $this->lastBatchResult = $result;

        return $result;
    }

    private function utcNow(): string
    {
        return gmdate('Y-m-d H:i:s');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function encodePayload(array $payload): string
    {
        $encoded = json_encode($payload);

        return is_string($encoded) ? $encoded : '{}';
    }
}
