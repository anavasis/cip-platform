<?php

namespace App\Modules\Editorial\Domain\GenerationResult;


/**
 * Opaque provider execution identity only.
 * Not an SDK client, HTTP call, or vendor binding.
 */
final class ProviderExecutionReference
{
    private $executionId;
    private $providerCode;
    private $startedAtUtc;
    private $completedAtUtc;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data)
    {
        $this->executionId = isset($data['execution_id'])
            ? trim((string) $data['execution_id'])
            : '';
        $this->providerCode = isset($data['provider_code'])
            ? trim((string) $data['provider_code'])
            : '';
        $this->startedAtUtc = isset($data['started_at_utc'])
            ? (string) $data['started_at_utc']
            : '';
        $this->completedAtUtc = isset($data['completed_at_utc'])
            ? (string) $data['completed_at_utc']
            : '';
    }

    /** @return string */
    public function executionId()
    {
        return $this->executionId;
    }

    /** @return string */
    public function providerCode()
    {
        return $this->providerCode;
    }

    /** @return string */
    public function startedAtUtc()
    {
        return $this->startedAtUtc;
    }

    /** @return string */
    public function completedAtUtc()
    {
        return $this->completedAtUtc;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray()
    {
        return array(
            'execution_id' => $this->executionId,
            'provider_code' => $this->providerCode,
            'started_at_utc' => $this->startedAtUtc,
            'completed_at_utc' => $this->completedAtUtc,
        );
    }
}
