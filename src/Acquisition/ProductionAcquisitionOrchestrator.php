<?php

namespace StudyMentor\ContentEngine\Acquisition;

use StudyMentor\ContentEngine\Collectors\CollectorRegistry;
use StudyMentor\ContentEngine\Registry\CapabilityRegistry;
use StudyMentor\ContentEngine\Registry\ParserRegistry;

defined('ABSPATH') || exit;

/**
 * Production acquisition entry point.
 * Capability and startup gates live only here — Source Check remains ungated.
 */
final class ProductionAcquisitionOrchestrator
{
    private $sourceAcquisitionService;
    private $capabilityRegistry;
    private $collectorRegistry;
    private $parserRegistry;
    private $diagnostics;

    public function __construct(
        SourceAcquisitionService $sourceAcquisitionService,
        CapabilityRegistry $capabilityRegistry,
        CollectorRegistry $collectorRegistry,
        ParserRegistry $parserRegistry,
        AcquisitionDiagnostics $diagnostics
    ) {
        $this->sourceAcquisitionService = $sourceAcquisitionService;
        $this->capabilityRegistry = $capabilityRegistry;
        $this->collectorRegistry = $collectorRegistry;
        $this->parserRegistry = $parserRegistry;
        $this->diagnostics = $diagnostics;
    }

    /**
     * @param array<int, int|string> $sourceIds
     * @return AcquisitionRunResult
     */
    public function run(array $sourceIds)
    {
        $startedAt = microtime(true);
        $normalizedIds = $this->normalizeSourceIds($sourceIds);

        if ($normalizedIds === array()) {
            return $this->rejectRun(
                'invalid_request',
                0,
                $startedAt
            );
        }

        if (!$this->capabilityRegistry->isEnabled(CapabilityRegistry::ACQUISITION)) {
            return $this->rejectRun(
                'capability_disabled',
                count($normalizedIds),
                $startedAt
            );
        }

        if (!$this->startupReady()) {
            return $this->rejectRun(
                'startup_validation_failed',
                count($normalizedIds),
                $startedAt
            );
        }

        $runId = $this->generateRunId();
        $this->diagnostics->recordProductionRun(array(
            'run_id' => $runId,
            'status' => 'running',
            'error_code' => '',
            'sources_requested' => count($normalizedIds),
            'sources_succeeded' => 0,
            'sources_failed' => 0,
        ));

        $results = array();
        $succeeded = 0;
        $failed = 0;

        foreach ($normalizedIds as $sourceId) {
            $acquisition = $this->sourceAcquisitionService->acquireFromSource($sourceId);
            $sourceSuccess = $acquisition->success() === true;

            if ($sourceSuccess) {
                $succeeded++;
            } else {
                $failed++;
            }

            $results[] = array(
                'source_id' => $sourceId,
                'success' => $sourceSuccess,
                'error_code' => $sourceSuccess ? '' : $acquisition->errorCode(),
            );
        }

        $runResult = new AcquisitionRunResult(array(
            'success' => $failed === 0 && $succeeded > 0,
            'run_id' => $runId,
            'error_code' => '',
            'sources_requested' => count($normalizedIds),
            'sources_succeeded' => $succeeded,
            'sources_failed' => $failed,
            'results' => $results,
            'duration_ms' => (microtime(true) - $startedAt) * 1000,
        ));

        $this->diagnostics->recordProductionRun(array(
            'run_id' => $runResult->runId(),
            'status' => 'completed',
            'error_code' => '',
            'sources_requested' => $runResult->sourcesRequested(),
            'sources_succeeded' => $runResult->sourcesSucceeded(),
            'sources_failed' => $runResult->sourcesFailed(),
        ));

        return $runResult;
    }

    /**
     * Direct readiness validation from registries — not from diagnostics state.
     *
     * @return bool
     */
    private function startupReady()
    {
        if (!$this->collectorRegistry->has('safe_feed')) {
            return false;
        }

        if (count($this->parserRegistry->all()) < 1) {
            return false;
        }

        if (!$this->capabilityRegistry->isEnabled(CapabilityRegistry::SOURCE_REGISTRY)) {
            return false;
        }

        $sourceTypeMap = $this->collectorRegistry->sourceTypeMap();

        foreach (array('rss', 'atom', 'html') as $sourceType) {
            if (
                !isset($sourceTypeMap[$sourceType])
                || $sourceTypeMap[$sourceType] !== 'safe_feed'
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<int, int|string> $sourceIds
     * @return array<int, int>
     */
    private function normalizeSourceIds(array $sourceIds)
    {
        $normalized = array();
        $seen = array();

        foreach ($sourceIds as $sourceId) {
            $id = (int) $sourceId;
            $key = (string) $id;

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $normalized[] = $id;
        }

        return $normalized;
    }

    /**
     * @return string Opaque unique run identifier
     */
    private function generateRunId()
    {
        try {
            $bytes = random_bytes(16);

            return bin2hex($bytes);
        } catch (\Exception $exception) {
            return hash('sha256', uniqid('smce-run-', true) . microtime(true));
        }
    }

    /**
     * @param string $errorCode
     * @param int $sourcesRequested
     * @param float $startedAt
     * @return AcquisitionRunResult
     */
    private function rejectRun($errorCode, $sourcesRequested, $startedAt)
    {
        $runId = $this->generateRunId();
        $result = new AcquisitionRunResult(array(
            'success' => false,
            'run_id' => $runId,
            'error_code' => (string) $errorCode,
            'sources_requested' => (int) $sourcesRequested,
            'sources_succeeded' => 0,
            'sources_failed' => 0,
            'results' => array(),
            'duration_ms' => (microtime(true) - $startedAt) * 1000,
        ));

        $this->diagnostics->recordProductionRun(array(
            'run_id' => $result->runId(),
            'status' => 'gate_rejected',
            'error_code' => $result->errorCode(),
            'sources_requested' => $result->sourcesRequested(),
            'sources_succeeded' => 0,
            'sources_failed' => 0,
        ));

        return $result;
    }
}
