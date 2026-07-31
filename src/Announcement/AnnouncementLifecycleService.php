<?php

namespace StudyMentor\ContentEngine\Announcement;

use StudyMentor\ContentEngine\Data\SourceItemRepository;

defined('ABSPATH') || exit;

/**
 * Announcement lifecycle decision and persistence spine.
 */
final class AnnouncementLifecycleService
{
    private $repository;
    private $identityService;
    /** @var LifecycleBatchResult|null */
    private $lastBatchResult;

    public function __construct(
        SourceItemRepository $repository,
        AnnouncementIdentityService $identityService
    ) {
        $this->repository = $repository;
        $this->identityService = $identityService;
        $this->lastBatchResult = null;
    }

    /**
     * @param array<int, AnnouncementCandidate> $candidates
     * @return LifecycleBatchResult
     */
    public function apply(array $candidates)
    {
        $sourceId = 0;
        $decisions = array();
        $seenIdentities = array();
        $newCount = 0;
        $updatedCount = 0;
        $unchangedCount = 0;
        $duplicateCount = 0;
        $now = $this->utcNow();

        foreach ($candidates as $candidate) {
            if (!$candidate instanceof AnnouncementCandidate) {
                continue;
            }

            if ($sourceId === 0) {
                $sourceId = $candidate->sourceId();
            }

            $identityHash = $this->identityService->identityHash($candidate->canonicalUrl());
            $contentHash = $this->identityService->contentHash($candidate);

            if ($identityHash === '' || $contentHash === '') {
                continue;
            }

            if (isset($seenIdentities[$identityHash])) {
                $duplicateCount++;
                $decisions[] = new LifecycleDecision(array(
                    'outcome' => LifecycleOutcome::DUPLICATE,
                    'source_id' => $candidate->sourceId(),
                    'identity_hash' => $identityHash,
                    'content_hash' => $contentHash,
                    'revision_no' => 0,
                    'item_id' => 0,
                    'title' => $candidate->title(),
                ));
                continue;
            }

            $seenIdentities[$identityHash] = true;
            $existing = $this->repository->findBySourceAndIdentityHash(
                $candidate->sourceId(),
                $identityHash
            );

            if ($existing === null) {
                $payload = $this->encodePayload($candidate->rawPayload());
                $inserted = $this->repository->insert(array(
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
                    'raw_payload' => $payload,
                    'revision_no' => 1,
                    'first_seen_at_utc' => $now,
                    'last_seen_at_utc' => $now,
                    'created_at_utc' => $now,
                    'updated_at_utc' => $now,
                ));

                if ($inserted !== true) {
                    $result = new LifecycleBatchResult(array(
                        'success' => false,
                        'error_code' => 'persist_failed',
                        'source_id' => $candidate->sourceId(),
                        'candidates' => count($candidates),
                        'new_count' => $newCount,
                        'updated_count' => $updatedCount,
                        'unchanged_count' => $unchangedCount,
                        'duplicate_count' => $duplicateCount,
                        'decisions' => $decisions,
                    ));
                    $this->lastBatchResult = $result;

                    return $result;
                }

                $newCount++;
                $itemId = $this->repository->lastInsertId();
                $decisions[] = new LifecycleDecision(array(
                    'outcome' => LifecycleOutcome::NEW_ITEM,
                    'source_id' => $candidate->sourceId(),
                    'identity_hash' => $identityHash,
                    'content_hash' => $contentHash,
                    'revision_no' => 1,
                    'item_id' => $itemId,
                    'title' => $candidate->title(),
                ));
                continue;
            }

            $existingContentHash = isset($existing['content_hash'])
                ? (string) $existing['content_hash']
                : '';
            $itemId = isset($existing['id']) ? (int) $existing['id'] : 0;
            $revisionNo = isset($existing['revision_no']) ? (int) $existing['revision_no'] : 1;

            if ($existingContentHash !== '' && $existingContentHash === $contentHash) {
                $this->repository->markUnchanged($itemId, $now, $now);
                $unchangedCount++;
                $decisions[] = new LifecycleDecision(array(
                    'outcome' => LifecycleOutcome::UNCHANGED,
                    'source_id' => $candidate->sourceId(),
                    'identity_hash' => $identityHash,
                    'content_hash' => $contentHash,
                    'revision_no' => $revisionNo,
                    'item_id' => $itemId,
                    'title' => $candidate->title(),
                ));
                continue;
            }

            $nextRevision = $revisionNo + 1;
            $payload = $this->encodePayload($candidate->rawPayload());
            $this->repository->applyContentUpdate($itemId, array(
                'source_guid' => $candidate->sourceGuid() !== '' ? $candidate->sourceGuid() : null,
                'canonical_url' => $candidate->canonicalUrl(),
                'source_published_at_utc' => $candidate->publishedAtUtc() !== ''
                    ? $candidate->publishedAtUtc()
                    : null,
                'raw_title' => $candidate->title(),
                'content_hash' => $contentHash,
                'raw_payload' => $payload,
                'revision_no' => $nextRevision,
                'last_seen_at_utc' => $now,
                'updated_at_utc' => $now,
            ));
            $updatedCount++;
            $decisions[] = new LifecycleDecision(array(
                'outcome' => LifecycleOutcome::UPDATED,
                'source_id' => $candidate->sourceId(),
                'identity_hash' => $identityHash,
                'content_hash' => $contentHash,
                'revision_no' => $nextRevision,
                'item_id' => $itemId,
                'title' => $candidate->title(),
            ));
        }

        $result = new LifecycleBatchResult(array(
            'success' => true,
            'error_code' => '',
            'source_id' => $sourceId,
            'candidates' => count($candidates),
            'new_count' => $newCount,
            'updated_count' => $updatedCount,
            'unchanged_count' => $unchangedCount,
            'duplicate_count' => $duplicateCount,
            'decisions' => $decisions,
        ));
        $this->lastBatchResult = $result;

        return $result;
    }

    /**
     * @return LifecycleBatchResult|null
     */
    public function lastBatchResult()
    {
        return $this->lastBatchResult;
    }

    /**
     * @return array<string, mixed>
     */
    public function diagnosticsStatus()
    {
        $last = $this->lastBatchResult instanceof LifecycleBatchResult
            ? $this->lastBatchResult->toArray()
            : null;

        if (is_array($last) && isset($last['decisions']) && is_array($last['decisions'])) {
            $summaryDecisions = array();

            foreach ($last['decisions'] as $decision) {
                if (!is_array($decision)) {
                    continue;
                }

                $summaryDecisions[] = array(
                    'outcome' => isset($decision['outcome']) ? (string) $decision['outcome'] : '',
                    'source_id' => isset($decision['source_id']) ? (int) $decision['source_id'] : 0,
                    'identity_hash' => isset($decision['identity_hash'])
                        ? (string) $decision['identity_hash']
                        : '',
                    'revision_no' => isset($decision['revision_no']) ? (int) $decision['revision_no'] : 0,
                    'item_id' => isset($decision['item_id']) ? (int) $decision['item_id'] : 0,
                );
            }

            $last['decisions'] = $summaryDecisions;
        }

        return array(
            'status' => 'ready',
            'store' => 'smce_source_items',
            'last_batch' => $last,
        );
    }

    /**
     * @return string
     */
    private function utcNow()
    {
        if (function_exists('current_time')) {
            return (string) current_time('mysql', true);
        }

        return gmdate('Y-m-d H:i:s');
    }

    /**
     * @param array<string, mixed> $payload
     * @return string
     */
    private function encodePayload(array $payload)
    {
        if (function_exists('wp_json_encode')) {
            $encoded = wp_json_encode($payload);

            if (is_string($encoded) && $encoded !== '') {
                return $encoded;
            }
        }

        $fallback = json_encode($payload);

        return is_string($fallback) ? $fallback : '{}';
    }
}
