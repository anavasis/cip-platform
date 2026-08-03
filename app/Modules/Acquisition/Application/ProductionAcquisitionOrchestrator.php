<?php

namespace App\Modules\Acquisition\Application;

use App\Modules\Acquisition\Domain\AcquisitionRunResult;
use App\Modules\Acquisition\Domain\Collectors\CollectorRegistry;
use App\Modules\Acquisition\Domain\Contracts\CapabilityGateInterface;
use App\Modules\Acquisition\Domain\Registry\ParserRegistry;

/**
 * Capability and startup gates apply only to production acquisition runs.
 */
final readonly class ProductionAcquisitionOrchestrator
{
    public function __construct(
        private SourceAcquisitionService $sourceAcquisitionService,
        private CapabilityGateInterface $capabilityGate,
        private CollectorRegistry $collectorRegistry,
        private ParserRegistry $parserRegistry,
        private AcquisitionDiagnostics $diagnostics,
    ) {}

    /** @param array<int, string|int> $sourceIds */
    public function run(
        string $organizationId,
        string $projectId,
        array $sourceIds,
    ): AcquisitionRunResult {
        $startedAt = microtime(true);
        $organizationId = trim($organizationId);
        $projectId = trim($projectId);
        $normalizedIds = $this->normalizeSourceIds($sourceIds);

        if ($organizationId === '' || $projectId === '' || $normalizedIds === []) {
            return $this->rejectRun('invalid_request', 0, $startedAt);
        }

        if (! $this->capabilityGate->isEnabled(CapabilityGate::ACQUISITION)) {
            return $this->rejectRun('capability_disabled', count($normalizedIds), $startedAt);
        }

        if (! $this->startupReady()) {
            return $this->rejectRun('startup_validation_failed', count($normalizedIds), $startedAt);
        }

        $runId = $this->generateRunId();
        $this->diagnostics->recordProductionRun([
            'run_id' => $runId,
            'status' => 'running',
            'error_code' => '',
            'sources_requested' => count($normalizedIds),
            'sources_succeeded' => 0,
            'sources_failed' => 0,
        ]);

        $results = [];
        $succeeded = 0;
        $failed = 0;

        foreach ($normalizedIds as $sourceId) {
            $acquisition = $this->sourceAcquisitionService->acquireFromSource(
                $organizationId,
                $projectId,
                $sourceId,
            );
            $sourceSuccess = $acquisition->success();

            if ($sourceSuccess) {
                $succeeded++;
            } else {
                $failed++;
            }

            $results[] = [
                'source_id' => $sourceId,
                'success' => $sourceSuccess,
                'error_code' => $sourceSuccess ? '' : $acquisition->errorCode(),
            ];
        }

        $runResult = new AcquisitionRunResult([
            'success' => $failed === 0 && $succeeded > 0,
            'run_id' => $runId,
            'error_code' => '',
            'sources_requested' => count($normalizedIds),
            'sources_succeeded' => $succeeded,
            'sources_failed' => $failed,
            'results' => $results,
            'duration_ms' => (microtime(true) - $startedAt) * 1000,
        ]);

        $this->diagnostics->recordProductionRun([
            'run_id' => $runResult->runId(),
            'status' => 'completed',
            'error_code' => '',
            'sources_requested' => $runResult->sourcesRequested(),
            'sources_succeeded' => $runResult->sourcesSucceeded(),
            'sources_failed' => $runResult->sourcesFailed(),
        ]);

        return $runResult;
    }

    private function startupReady(): bool
    {
        if (! $this->collectorRegistry->has('safe_feed')
            || count($this->parserRegistry->all()) < 1
            || ! $this->capabilityGate->isEnabled(CapabilityGate::SOURCE_REGISTRY)) {
            return false;
        }

        $sourceTypeMap = $this->collectorRegistry->sourceTypeMap();

        foreach (['rss', 'atom', 'html'] as $sourceType) {
            if (($sourceTypeMap[$sourceType] ?? null) !== 'safe_feed') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, string|int>  $sourceIds
     * @return array<int, string>
     */
    private function normalizeSourceIds(array $sourceIds): array
    {
        $normalized = [];
        $seen = [];

        foreach ($sourceIds as $sourceId) {
            if (! is_string($sourceId) && ! is_int($sourceId)) {
                continue;
            }

            $id = trim((string) $sourceId);

            if ($id === '' || isset($seen[$id])) {
                continue;
            }

            $seen[$id] = true;
            $normalized[] = $id;
        }

        return $normalized;
    }

    private function generateRunId(): string
    {
        try {
            return bin2hex(random_bytes(16));
        } catch (\Throwable) {
            return hash('sha256', uniqid('cip-run-', true).microtime(true));
        }
    }

    private function rejectRun(
        string $errorCode,
        int $sourcesRequested,
        float $startedAt,
    ): AcquisitionRunResult {
        $result = new AcquisitionRunResult([
            'success' => false,
            'run_id' => $this->generateRunId(),
            'error_code' => $errorCode,
            'sources_requested' => $sourcesRequested,
            'sources_succeeded' => 0,
            'sources_failed' => 0,
            'results' => [],
            'duration_ms' => (microtime(true) - $startedAt) * 1000,
        ]);

        $this->diagnostics->recordProductionRun([
            'run_id' => $result->runId(),
            'status' => 'gate_rejected',
            'error_code' => $result->errorCode(),
            'sources_requested' => $result->sourcesRequested(),
            'sources_succeeded' => 0,
            'sources_failed' => 0,
        ]);

        return $result;
    }
}
