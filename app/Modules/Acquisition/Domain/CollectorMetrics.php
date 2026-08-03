<?php

namespace App\Modules\Acquisition\Domain;

final readonly class CollectorMetrics
{
    private float $executionTimeMs;

    private int $bytes;

    private int $redirects;

    private int $failures;

    private int $httpStatus;

    private string $collectorId;

    /** @param array<string, mixed> $data */
    public function __construct(array $data = [])
    {
        $this->executionTimeMs = isset($data['execution_time_ms']) ? (float) $data['execution_time_ms'] : 0.0;
        $this->bytes = isset($data['bytes']) ? (int) $data['bytes'] : 0;
        $this->redirects = isset($data['redirects']) ? (int) $data['redirects'] : 0;
        $this->failures = isset($data['failures']) ? (int) $data['failures'] : 0;
        $this->httpStatus = isset($data['http_status']) ? (int) $data['http_status'] : 0;
        $this->collectorId = isset($data['collector']) ? (string) $data['collector'] : '';
    }

    public function executionTimeMs(): float
    {
        return $this->executionTimeMs;
    }

    public function bytes(): int
    {
        return $this->bytes;
    }

    public function redirects(): int
    {
        return $this->redirects;
    }

    public function failures(): int
    {
        return $this->failures;
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }

    public function collectorId(): string
    {
        return $this->collectorId;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'execution_time_ms' => $this->executionTimeMs,
            'bytes' => $this->bytes,
            'redirects' => $this->redirects,
            'failures' => $this->failures,
            'http_status' => $this->httpStatus,
            'collector' => $this->collectorId,
        ];
    }
}
