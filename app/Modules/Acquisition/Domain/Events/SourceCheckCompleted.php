<?php

namespace App\Modules\Acquisition\Domain\Events;

use App\Domain\Events\DomainEvent;

final readonly class SourceCheckCompleted implements DomainEvent
{
    public function __construct(
        public string $organizationId,
        public string $projectId,
        public string $sourceId,
        public bool $success,
        public string $errorCode = '',
        public int $httpStatus = 0,
        public float $durationMs = 0.0,
    ) {}

    public function eventName(): string
    {
        return 'source.check_completed';
    }

    public function payload(): array
    {
        return [
            'organization_id' => $this->organizationId,
            'project_id' => $this->projectId,
            'source_id' => $this->sourceId,
            'success' => $this->success,
            'error_code' => $this->errorCode,
            'http_status' => $this->httpStatus,
            'duration_ms' => $this->durationMs,
        ];
    }
}
