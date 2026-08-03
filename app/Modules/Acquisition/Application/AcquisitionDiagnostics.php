<?php

namespace App\Modules\Acquisition\Application;

use App\Modules\Acquisition\Domain\AcquisitionResult;
use App\Modules\Acquisition\Domain\Collectors\CollectorRegistry;
use App\Modules\Acquisition\Domain\Contracts\CapabilityGateInterface;
use App\Modules\Acquisition\Domain\Evidence\Evidence;
use App\Modules\Acquisition\Domain\Evidence\EvidenceRepositoryInterface;
use App\Modules\Acquisition\Domain\Fingerprint\FingerprintService;
use App\Modules\Acquisition\Domain\Registry\ParserRegistry;
use App\Modules\Announcement\Domain\Contracts\IngestionDiagnosticsInterface;

final class AcquisitionDiagnostics implements IngestionDiagnosticsInterface
{
    private const EVIDENCE_SUMMARY_LIMIT = 10;

    /** @var array<string, AcquisitionResult> */
    private array $lastResults = [];

    /** @var array<string, int> */
    private array $acquisitionCounts = [];

    /** @var array<string, int> */
    private array $failureCounts = [];

    /** @var array<string, array<string, mixed>> */
    private array $startupValidations = [];

    /** @var array<string, array<string, mixed>> */
    private array $lastProductionRuns = [];

    /** @var array<string, array<string, mixed>> */
    private array $lastIngestions = [];

    /** @var array<string, int> */
    private array $productionRunsRecorded = [];

    public function __construct(
        private readonly CollectorRegistry $collectorRegistry,
        private readonly ParserRegistry $parserRegistry,
        private readonly EvidenceRepositoryInterface $evidenceRepository,
        private readonly CapabilityGateInterface $capabilityGate,
        private readonly FingerprintService $fingerprintService,
        private readonly string $platformVersion = '',
    ) {}

    /** @param array<string, mixed> $validation */
    public function recordStartupValidation(
        string $organizationId,
        string $projectId,
        array $validation,
    ): void
    {
        $key = $this->tenantKey($organizationId, $projectId);

        if ($key !== null) {
            $this->startupValidations[$key] = $this->metadataOnly($validation);
        }
    }

    /** @param array<string, mixed> $run */
    public function recordProductionRun(
        string $organizationId,
        string $projectId,
        array $run,
    ): void
    {
        $key = $this->tenantKey($organizationId, $projectId);

        if ($key === null) {
            return;
        }

        $status = isset($run['status']) ? (string) $run['status'] : '';
        $this->lastProductionRuns[$key] = [
            'run_id' => isset($run['run_id']) ? (string) $run['run_id'] : '',
            'status' => $status,
            'error_code' => isset($run['error_code']) ? (string) $run['error_code'] : '',
            'sources_requested' => isset($run['sources_requested']) ? (int) $run['sources_requested'] : 0,
            'sources_succeeded' => isset($run['sources_succeeded']) ? (int) $run['sources_succeeded'] : 0,
            'sources_failed' => isset($run['sources_failed']) ? (int) $run['sources_failed'] : 0,
        ];

        if ($status === 'completed' || $status === 'gate_rejected') {
            $this->productionRunsRecorded[$key] = ($this->productionRunsRecorded[$key] ?? 0) + 1;
        }
    }

    public function recordResult(
        string $organizationId,
        string $projectId,
        AcquisitionResult $result,
    ): void {
        $key = $this->tenantKey($organizationId, $projectId);

        if ($key === null) {
            return;
        }

        $this->lastResults[$key] = $result;
        $this->acquisitionCounts[$key] = ($this->acquisitionCounts[$key] ?? 0) + 1;

        if (! $result->success()) {
            $this->failureCounts[$key] = ($this->failureCounts[$key] ?? 0) + 1;
        }
    }

    /** @param array<string, mixed> $data */
    public function record(array $data): void
    {
        $key = $this->tenantKey(
            (string) ($data['organization_id'] ?? ''),
            (string) ($data['project_id'] ?? ''),
        );

        if ($key !== null) {
            $this->lastIngestions[$key] = $this->metadataOnly($data);
        }
    }

    /** @return array<string, mixed> */
    public function status(string $organizationId, string $projectId): array
    {
        $key = $this->tenantKey($organizationId, $projectId);
        $collectorIds = array_keys($this->collectorRegistry->all());
        $last = null;
        $lastResult = $key !== null ? ($this->lastResults[$key] ?? null) : null;

        if ($lastResult !== null) {
            $last = $lastResult->toArray();
            $lastEvidence = $lastResult->evidence();

            if ($lastEvidence instanceof Evidence) {
                $last['evidence'] = $lastEvidence->toMetadataArray();
            }
        }

        $sourceTypeMap = $this->collectorRegistry->sourceTypeMap();
        $evidenceSummaries = $key !== null
            ? $this->evidenceRepository->summaries($organizationId, $projectId)
            : [];
        $capabilityEnabled = $this->capabilityEnabled($organizationId, $projectId);
        $startupValidation = ($key !== null ? ($this->startupValidations[$key] ?? null) : null) ?? [
            'status' => $capabilityEnabled ? 'pending' : 'not_applicable',
            'checks' => [],
        ];
        $lastProductionRun = $key !== null ? ($this->lastProductionRuns[$key] ?? null) : null;
        $orchestratorStatus = 'idle';
        $productionRuntime = $capabilityEnabled ? 'idle' : 'inactive';

        if (($lastProductionRun['status'] ?? null) === 'running') {
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
            'capability_acquisition_enabled' => $capabilityEnabled,
            'collectors' => $collectorIds,
            'default_collector' => $this->collectorRegistry->defaultCollector()?->id() ?? '',
            'parser_handlers' => count($this->parserRegistry->all()),
            'evidence_store' => 'in_memory',
            'evidence_count' => $key !== null
                ? $this->evidenceRepository->count($organizationId, $projectId)
                : 0,
            'acquisitions_recorded' => $key !== null ? ($this->acquisitionCounts[$key] ?? 0) : 0,
            'acquisition_failures' => $key !== null ? ($this->failureCounts[$key] ?? 0) : 0,
            'startup_validation' => $startupValidation,
            'production_runtime' => $productionRuntime,
            'production_orchestrator' => [
                'status' => $orchestratorStatus,
                'runs_recorded' => $key !== null ? ($this->productionRunsRecorded[$key] ?? 0) : 0,
                'last_run' => $lastProductionRun,
            ],
            'fingerprint' => $this->fingerprintService->describe(),
            'evidence' => [
                'store' => 'in_memory',
                'count' => $key !== null
                    ? $this->evidenceRepository->count($organizationId, $projectId)
                    : 0,
                'store_operations' => $key !== null
                    ? $this->evidenceRepository->storeOperations($organizationId, $projectId)
                    : 0,
                'entries' => array_slice($evidenceSummaries, 0, self::EVIDENCE_SUMMARY_LIMIT),
            ],
            'last_result' => $last,
            'last_ingestion' => $key !== null ? ($this->lastIngestions[$key] ?? null) : null,
        ];
    }

    private function capabilityEnabled(string $organizationId, string $projectId): bool
    {
        if ($this->capabilityGate instanceof CapabilityGate) {
            return $this->capabilityGate->isEnabledFor(
                CapabilityGate::ACQUISITION,
                $organizationId,
                $projectId,
            );
        }

        return $this->capabilityGate->isEnabled(CapabilityGate::ACQUISITION);
    }

    private function tenantKey(string $organizationId, string $projectId): ?string
    {
        $organizationId = trim($organizationId);
        $projectId = trim($projectId);

        return $organizationId !== '' && $projectId !== ''
            ? $organizationId."\0".$projectId
            : null;
    }

    /**
     * @param  array<int|string, mixed>  $data
     * @return array<int|string, mixed>
     */
    private function metadataOnly(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && in_array($key, ['body', 'raw_body', 'evidence_body'], true)) {
                unset($data[$key]);

                continue;
            }

            if (is_array($value)) {
                $data[$key] = $this->metadataOnly($value);
            }
        }

        return $data;
    }
}
