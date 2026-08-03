<?php

namespace App\Modules\Announcement\Domain;

/**
 * Batch lifecycle result for diagnostics and editorial consumers.
 */
final class LifecycleBatchResult
{
    private readonly bool $success;

    private readonly string $errorCode;

    private readonly string $sourceId;

    private readonly int $candidates;

    private readonly int $newCount;

    private readonly int $updatedCount;

    private readonly int $unchangedCount;

    private readonly int $duplicateCount;

    /**
     * @var array<int, LifecycleDecision>
     */
    private readonly array $decisions;

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(array $data)
    {
        $this->success = isset($data['success']) && $data['success'] === true;
        $this->errorCode = isset($data['error_code']) ? (string) $data['error_code'] : '';
        $this->sourceId = isset($data['source_id']) ? (string) $data['source_id'] : '';
        $this->candidates = isset($data['candidates']) ? (int) $data['candidates'] : 0;
        $this->newCount = isset($data['new_count']) ? (int) $data['new_count'] : 0;
        $this->updatedCount = isset($data['updated_count']) ? (int) $data['updated_count'] : 0;
        $this->unchangedCount = isset($data['unchanged_count']) ? (int) $data['unchanged_count'] : 0;
        $this->duplicateCount = isset($data['duplicate_count']) ? (int) $data['duplicate_count'] : 0;
        $decisions = [];

        if (isset($data['decisions']) && is_array($data['decisions'])) {
            foreach ($data['decisions'] as $decision) {
                if ($decision instanceof LifecycleDecision) {
                    $decisions[] = $decision;
                }
            }
        }

        $this->decisions = $decisions;
    }

    public function success(): bool
    {
        return $this->success;
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function sourceId(): string
    {
        return $this->sourceId;
    }

    public function candidates(): int
    {
        return $this->candidates;
    }

    public function newCount(): int
    {
        return $this->newCount;
    }

    public function updatedCount(): int
    {
        return $this->updatedCount;
    }

    public function unchangedCount(): int
    {
        return $this->unchangedCount;
    }

    public function duplicateCount(): int
    {
        return $this->duplicateCount;
    }

    /**
     * @return array<int, LifecycleDecision>
     */
    public function decisions(): array
    {
        return $this->decisions;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'error_code' => $this->errorCode,
            'source_id' => $this->sourceId,
            'candidates' => $this->candidates,
            'new_count' => $this->newCount,
            'updated_count' => $this->updatedCount,
            'unchanged_count' => $this->unchangedCount,
            'duplicate_count' => $this->duplicateCount,
            'decisions' => array_map(
                static fn (LifecycleDecision $decision): array => $decision->toArray(),
                $this->decisions,
            ),
        ];
    }
}
