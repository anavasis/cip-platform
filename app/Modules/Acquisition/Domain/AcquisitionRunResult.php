<?php

namespace App\Modules\Acquisition\Domain;

final readonly class AcquisitionRunResult
{
    private bool $success;

    private string $runId;

    private string $errorCode;

    private int $sourcesRequested;

    private int $sourcesSucceeded;

    private int $sourcesFailed;

    /** @var array<int, array<string, mixed>> */
    private array $results;

    private float $durationMs;

    /** @param array<string, mixed> $data */
    public function __construct(array $data)
    {
        $this->success = isset($data['success']) && $data['success'] === true;
        $this->runId = isset($data['run_id']) ? (string) $data['run_id'] : '';
        $this->errorCode = isset($data['error_code']) ? (string) $data['error_code'] : '';
        $this->sourcesRequested = isset($data['sources_requested']) ? (int) $data['sources_requested'] : 0;
        $this->sourcesSucceeded = isset($data['sources_succeeded']) ? (int) $data['sources_succeeded'] : 0;
        $this->sourcesFailed = isset($data['sources_failed']) ? (int) $data['sources_failed'] : 0;
        $this->results = isset($data['results']) && is_array($data['results'])
            ? array_values($data['results'])
            : [];
        $this->durationMs = isset($data['duration_ms']) ? (float) $data['duration_ms'] : 0.0;
    }

    public function success(): bool
    {
        return $this->success;
    }

    public function runId(): string
    {
        return $this->runId;
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function sourcesRequested(): int
    {
        return $this->sourcesRequested;
    }

    public function sourcesSucceeded(): int
    {
        return $this->sourcesSucceeded;
    }

    public function sourcesFailed(): int
    {
        return $this->sourcesFailed;
    }

    /** @return array<int, array<string, mixed>> */
    public function results(): array
    {
        return $this->results;
    }

    public function durationMs(): float
    {
        return $this->durationMs;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'run_id' => $this->runId,
            'error_code' => $this->errorCode,
            'sources_requested' => $this->sourcesRequested,
            'sources_succeeded' => $this->sourcesSucceeded,
            'sources_failed' => $this->sourcesFailed,
            'results' => $this->results,
            'duration_ms' => $this->durationMs,
        ];
    }
}
