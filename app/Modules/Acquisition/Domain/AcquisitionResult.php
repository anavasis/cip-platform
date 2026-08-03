<?php

namespace App\Modules\Acquisition\Domain;

use App\Modules\Acquisition\Domain\Evidence\Evidence;

final readonly class AcquisitionResult
{
    private bool $success;

    /** @var array<int, string> */
    private array $warnings;

    /** @var array<int, string> */
    private array $errors;

    private ?Evidence $evidence;

    private string $parserUsed;

    private float $durationMs;

    private CollectorMetrics $metrics;

    /** @var array<string, mixed> */
    private array $parseResult;

    /** @var array<string, mixed> */
    private array $fetchResult;

    private string $errorCode;

    /** @param array<string, mixed> $data */
    public function __construct(array $data)
    {
        $this->success = isset($data['success']) && $data['success'] === true;
        $this->warnings = isset($data['warnings']) && is_array($data['warnings'])
            ? array_values($data['warnings'])
            : [];
        $this->errors = isset($data['errors']) && is_array($data['errors'])
            ? array_values($data['errors'])
            : [];
        $this->evidence = isset($data['evidence']) && $data['evidence'] instanceof Evidence
            ? $data['evidence']
            : null;
        $this->parserUsed = isset($data['parser_used']) ? (string) $data['parser_used'] : '';
        $this->durationMs = isset($data['duration']) ? (float) $data['duration'] : 0.0;
        $this->metrics = isset($data['metrics']) && $data['metrics'] instanceof CollectorMetrics
            ? $data['metrics']
            : new CollectorMetrics;
        $this->parseResult = isset($data['parse_result']) && is_array($data['parse_result'])
            ? $data['parse_result']
            : [];
        $this->fetchResult = isset($data['fetch_result']) && is_array($data['fetch_result'])
            ? $data['fetch_result']
            : [];
        $this->errorCode = isset($data['error_code']) ? (string) $data['error_code'] : '';
    }

    public function success(): bool
    {
        return $this->success;
    }

    /** @return array<int, string> */
    public function warnings(): array
    {
        return $this->warnings;
    }

    /** @return array<int, string> */
    public function errors(): array
    {
        return $this->errors;
    }

    public function evidence(): ?Evidence
    {
        return $this->evidence;
    }

    public function parserUsed(): string
    {
        return $this->parserUsed;
    }

    public function duration(): float
    {
        return $this->durationMs;
    }

    public function metrics(): CollectorMetrics
    {
        return $this->metrics;
    }

    /** @return array<string, mixed> */
    public function parseResult(): array
    {
        return $this->parseResult;
    }

    /** @return array<string, mixed> */
    public function fetchResult(): array
    {
        return $this->fetchResult;
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'warnings' => $this->warnings,
            'errors' => $this->errors,
            'evidence' => $this->evidence?->toArray(),
            'parser_used' => $this->parserUsed,
            'duration' => $this->durationMs,
            'metrics' => $this->metrics->toArray(),
            'error_code' => $this->errorCode,
        ];
    }
}
