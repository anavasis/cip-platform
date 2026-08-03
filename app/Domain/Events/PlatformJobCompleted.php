<?php

namespace App\Domain\Events;

final class PlatformJobCompleted implements DomainEvent
{
    public function __construct(
        public readonly string $jobId,
        public readonly string $jobType,
        public readonly string $status,
    ) {}

    public function eventName(): string
    {
        return 'platform_job.completed';
    }

    public function payload(): array
    {
        return [
            'job_id' => $this->jobId,
            'job_type' => $this->jobType,
            'status' => $this->status,
        ];
    }
}
