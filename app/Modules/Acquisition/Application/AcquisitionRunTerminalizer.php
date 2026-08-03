<?php

namespace App\Modules\Acquisition\Application;

use App\Modules\Acquisition\Domain\AcquisitionRunTerminalizationException;
use App\Modules\Acquisition\Infrastructure\Persistence\Repositories\EloquentAcquisitionRunRepository;

/**
 * Guarantees acquisition runs leave `running` when a job ends.
 * Terminal persistence is required, not best-effort.
 */
final class AcquisitionRunTerminalizer
{
    private const MAX_ATTEMPTS = 3;

    public function __construct(
        private readonly EloquentAcquisitionRunRepository $runs,
    ) {}

    /**
     * @param  array<string, mixed>  $fields
     */
    public function ensureTerminal(
        string $runId,
        string $organizationId,
        string $projectId,
        string $status,
        array $fields = [],
    ): void {
        if (! in_array($status, ['completed', 'failed'], true)) {
            throw AcquisitionRunTerminalizationException::persistenceFailed($runId);
        }

        $payload = array_merge($fields, [
            'organization_id' => $organizationId,
            'project_id' => $projectId,
            'status' => $status,
        ]);

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            $updated = $this->runs->updateRun($runId, $payload);
            $current = $this->runs->findByRunId($organizationId, $projectId, $runId);

            if (is_array($current) && $this->isTerminalStatus((string) ($current['status'] ?? ''))) {
                return;
            }

            if ($updated === true) {
                $current = $this->runs->findByRunId($organizationId, $projectId, $runId);

                if (is_array($current) && $this->isTerminalStatus((string) ($current['status'] ?? ''))) {
                    return;
                }
            }

            if ($attempt < self::MAX_ATTEMPTS) {
                usleep(5_000 * $attempt);
            }
        }

        $final = $this->runs->findByRunId($organizationId, $projectId, $runId);

        if (is_array($final) && $this->isTerminalStatus((string) ($final['status'] ?? ''))) {
            return;
        }

        throw AcquisitionRunTerminalizationException::persistenceFailed($runId);
    }

    private function isTerminalStatus(string $status): bool
    {
        return in_array($status, ['completed', 'failed'], true);
    }
}
