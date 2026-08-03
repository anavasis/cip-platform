<?php

namespace App\Modules\Acquisition\Application;

use App\Modules\Acquisition\Domain\AcquisitionResult;
use App\Modules\Acquisition\Domain\Collectors\CollectorRegistry;
use App\Modules\Acquisition\Domain\Contracts\CapabilityGateInterface;
use App\Modules\Acquisition\Domain\Evidence\Evidence;
use App\Modules\Acquisition\Domain\Evidence\EvidenceRepositoryInterface;
use App\Modules\Acquisition\Domain\Fingerprint\FingerprintService;
use App\Modules\Acquisition\Domain\Registry\ParserRegistry;

final class AcquisitionDiagnostics
{
    private const EVIDENCE_SUMMARY_LIMIT = 10;

    private ?AcquisitionResult $lastResult = null;

    private int $acquisitionCount = 0;

    private int $failureCount = 0;

    /** @var array<string, mixed>|null */
    private ?array $startupValidation = null;

    /** @var array<string, mixed>|null */
    private ?array $lastProductionRun = null;

    private int $productionRunsRecorded = 0;

    public function __construct(
        private readonly CollectorRegistry $collectorRegistry,
        private readonly ParserRegistry $parserRegistry,
        private readonly EvidenceRepositoryInterface $evidenceRepository,
        private readonly CapabilityGateInterface $capabilityGate,
        private readonly FingerprintService $fingerprintService,
        private readonly string $platformVersion = '',
        private readonly string $pluginVersion = '',
    ) {}

    /** @param array<string, mixed> $validation */
    public function recordStartupValidation(array $validation): void
    {
        $this->startupValidation = $validation;
    }

    /** @param array<string, mixed> $run */
    public function recordProductionRun(array $run): void
    {
        $status = isset($run['status']) ? (string) $run['status'] : '';
        $this->lastProductionRun = [
            'run_id' => isset($run['run_id']) ? (string) $run['run_id'] : '',
            'status' => $status,
            'error_code' => isset($run['error_code']) ? (string) $run['error_code'] : '',
            'sources_requested' => isset($run['sources_requested']) ? (int) $run['sources_requested'] : 0,
            'sources_succeeded' => isset($run['sources_succeeded']) ? (int) $run['sources_succeeded'] : 0,
            'sources_failed' => isset($run['sources_failed']) ? (int) $run['sources_failed'] : 0,
        ];

        if ($status === 'completed' || $status === 'gate_rejected') {
            $this->productionRunsRecorded++;
        }
    }

    public function recordResult(AcquisitionResult $result): void
    {
        $this->lastResult = $result;
        $this->acquisitionCount++;

        if (! $result->success()) {
            $this->failureCount++;
        }
    }

    /** @return array<string, mixed> */
    public function status(): array
    {
        $collectorIds = array_keys($this->collectorRegistry->all());
        $last = null;

        if ($this->lastResult !== null) {
            $last = $this->lastResult->toArray();
            $lastEvidence = $this->lastResult->evidence();

            if ($lastEvidence instanceof Evidence) {
                $last['evidence'] = $lastEvidence->toMetadataArray();
            }
        }

        $sourceTypeMap = $this->collectorRegistry->sourceTypeMap();
        $evidenceSummaries = $this->evidenceRepository->summaries();
        $capabilityEnabled = $this->capabilityGate->isEnabled(CapabilityGate::ACQUISITION);
        $startupValidation = $this->startupValidation ?? [
            'status' => $capabilityEnabled ? 'pending' : 'not_applicable',
            'checks' => [],
        ];
        $orchestratorStatus = 'idle';
        $productionRuntime = $capabilityEnabled ? 'idle' : 'inactive';

        if (($this->lastProductionRun['status'] ?? null) === 'running') {
            $orchestratorStatus = 'running';
            $productionRuntime = 'running';
        } elseif ($capabilityEnabled) {
            $orchestratorStatus = 'ready';
        }

        return [
            'acquisition_engine' => $capabilityEnabled ? 'active' : 'ready',
            'acquisition_runtime' => $capabilityEnabled ? 'active' : 'inactive',
            'collector_routing' => $sourceTypeMap !== []
                ? ($capabilityEnabled ? 'active' : 'ready')
                : 'not_ready',
            'source_type_map' => $sourceTypeMap,
            'acquisition_engine_version' => AcquisitionEngine::VERSION,
            'platform_version' => $this->platformVersion,
            'plugin_version' => $this->pluginVersion,
            'capability_acquisition_enabled' => $capabilityEnabled,
            'collectors' => $collectorIds,
            'default_collector' => $this->collectorRegistry->defaultCollector()?->id() ?? '',
            'parser_handlers' => count($this->parserRegistry->all()),
            'evidence_store' => 'in_memory',
            'evidence_count' => $this->evidenceRepository->count(),
            'acquisitions_recorded' => $this->acquisitionCount,
            'acquisition_failures' => $this->failureCount,
            'publishing' => 'disabled',
            'scheduler' => 'disabled',
            'ai' => 'disabled',
            'startup_validation' => $startupValidation,
            'production_runtime' => $productionRuntime,
            'production_orchestrator' => [
                'status' => $orchestratorStatus,
                'runs_recorded' => $this->productionRunsRecorded,
                'last_run' => $this->lastProductionRun,
            ],
            'fingerprint' => $this->fingerprintService->describe(),
            'evidence' => [
                'store' => 'in_memory',
                'count' => $this->evidenceRepository->count(),
                'store_operations' => $this->evidenceRepository->storeOperations(),
                'entries' => array_slice($evidenceSummaries, 0, self::EVIDENCE_SUMMARY_LIMIT),
            ],
            'last_result' => $last,
        ];
    }
}
