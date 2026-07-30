<?php

namespace StudyMentor\ContentEngine\Acquisition;

use StudyMentor\ContentEngine\Collectors\CollectorRegistry;
use StudyMentor\ContentEngine\Evidence\Evidence;
use StudyMentor\ContentEngine\Evidence\EvidenceRepositoryInterface;
use StudyMentor\ContentEngine\Fingerprint\FingerprintService;
use StudyMentor\ContentEngine\Registry\CapabilityRegistry;
use StudyMentor\ContentEngine\Registry\ParserRegistry;
use StudyMentor\ContentEngine\Registry\VersionRegistry;

defined('ABSPATH') || exit;

/**
 * Exposes Acquisition Engine status for diagnostics consumers.
 * UI wiring remains unchanged in this phase.
 */
final class AcquisitionDiagnostics
{
    private const EVIDENCE_SUMMARY_LIMIT = 10;

    private $collectorRegistry;
    private $parserRegistry;
    private $evidenceRepository;
    private $capabilityRegistry;
    private $versionRegistry;
    private $fingerprintService;
    private $lastResult;
    private $acquisitionCount;
    private $failureCount;
    /** @var array<string, mixed>|null */
    private $startupValidation;

    public function __construct(
        CollectorRegistry $collectorRegistry,
        ParserRegistry $parserRegistry,
        EvidenceRepositoryInterface $evidenceRepository,
        CapabilityRegistry $capabilityRegistry,
        VersionRegistry $versionRegistry,
        FingerprintService $fingerprintService
    ) {
        $this->collectorRegistry = $collectorRegistry;
        $this->parserRegistry = $parserRegistry;
        $this->evidenceRepository = $evidenceRepository;
        $this->capabilityRegistry = $capabilityRegistry;
        $this->versionRegistry = $versionRegistry;
        $this->fingerprintService = $fingerprintService;
        $this->lastResult = null;
        $this->acquisitionCount = 0;
        $this->failureCount = 0;
        $this->startupValidation = null;
    }

    /**
     * @param array<string, mixed> $validation
     * @return void
     */
    public function recordStartupValidation(array $validation)
    {
        $this->startupValidation = $validation;
    }

    /**
     * @return void
     */
    public function recordResult(AcquisitionResult $result)
    {
        $this->lastResult = $result;
        $this->acquisitionCount++;

        if (!$result->success()) {
            $this->failureCount++;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function status()
    {
        $collectorIds = array_keys($this->collectorRegistry->all());
        $parserCount = count($this->parserRegistry->all());
        $last = null;

        if ($this->lastResult instanceof AcquisitionResult) {
            $last = $this->lastResult->toArray();
            $lastEvidence = $this->lastResult->evidence();

            if ($lastEvidence instanceof Evidence) {
                $last['evidence'] = $lastEvidence->toMetadataArray();
            }
        }

        $sourceTypeMap = $this->collectorRegistry->sourceTypeMap();
        $evidenceSummaries = $this->evidenceRepository->summaries();
        $capabilityEnabled = $this->capabilityRegistry->isEnabled(CapabilityRegistry::ACQUISITION);
        $startupValidation = $this->startupValidation !== null
            ? $this->startupValidation
            : array(
                'status' => $capabilityEnabled ? 'pending' : 'not_applicable',
                'checks' => array(),
            );

        return array(
            'acquisition_engine' => $capabilityEnabled ? 'active' : 'ready',
            'acquisition_runtime' => $capabilityEnabled ? 'active' : 'inactive',
            'collector_routing' => $sourceTypeMap !== array()
                ? ($capabilityEnabled ? 'active' : 'ready')
                : 'not_ready',
            'source_type_map' => $sourceTypeMap,
            'acquisition_engine_version' => AcquisitionEngine::VERSION,
            'platform_version' => $this->versionRegistry->get('platform'),
            'plugin_version' => $this->versionRegistry->get('plugin'),
            'capability_acquisition_enabled' => $capabilityEnabled,
            'collectors' => $collectorIds,
            'default_collector' => $this->collectorRegistry->defaultCollector()
                ? $this->collectorRegistry->defaultCollector()->id()
                : '',
            'parser_handlers' => $parserCount,
            'evidence_store' => 'in_memory',
            'evidence_count' => $this->evidenceRepository->count(),
            'acquisitions_recorded' => $this->acquisitionCount,
            'acquisition_failures' => $this->failureCount,
            'publishing' => 'disabled',
            'scheduler' => 'disabled',
            'ai' => 'disabled',
            'startup_validation' => $startupValidation,
            'fingerprint' => $this->fingerprintService->describe(),
            'evidence' => array(
                'store' => 'in_memory',
                'count' => $this->evidenceRepository->count(),
                'store_operations' => $this->evidenceRepository->storeOperations(),
                'entries' => array_slice($evidenceSummaries, 0, self::EVIDENCE_SUMMARY_LIMIT),
            ),
            'last_result' => $last,
        );
    }
}
