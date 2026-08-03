<?php

namespace App\Modules\Acquisition\Domain\Events;

use App\Domain\Events\DomainEvent;

/**
 * Metadata-only event. Evidence body content is intentionally excluded.
 */
final readonly class EvidenceCaptured implements DomainEvent
{
    public function __construct(
        public string $organizationId,
        public string $projectId,
        public string $sourceId,
        public string $evidenceKey,
        public string $contentHash,
        public string $identityHash,
        public int $httpStatus,
        public int $responseBytes,
    ) {}

    public function eventName(): string
    {
        return 'evidence.captured';
    }

    public function payload(): array
    {
        return [
            'organization_id' => $this->organizationId,
            'project_id' => $this->projectId,
            'source_id' => $this->sourceId,
            'evidence_key' => $this->evidenceKey,
            'content_hash' => $this->contentHash,
            'identity_hash' => $this->identityHash,
            'http_status' => $this->httpStatus,
            'response_bytes' => $this->responseBytes,
        ];
    }
}
