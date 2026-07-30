<?php

namespace StudyMentor\ContentEngine\Acquisition;

use StudyMentor\ContentEngine\Collectors\CollectorRegistry;
use StudyMentor\ContentEngine\Evidence\EvidenceRepositoryInterface;
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
    private $collectorRegistry;
    private $parserRegistry;
    private $evidenceRepository;
    private $capabilityRegistry;
    private $versionRegistry;
    private $lastResult;
    private $acquisitionCount;
    private $failureCount;

    public function __construct(
        CollectorRegistry $collectorRegistry,
        ParserRegistry $parserRegistry,
        EvidenceRepositoryInterface $evidenceRepository,
        CapabilityRegistry $capabilityRegistry,
        VersionRegistry $versionRegistry
    ) {
        $this->collectorRegistry = $collectorRegistry;
        $this->parserRegistry = $parserRegistry;
        $this->evidenceRepository = $evidenceRepository;
        $this->capabilityRegistry = $capabilityRegistry;
        $this->versionRegistry = $versionRegistry;
        $this->lastResult = null;
        $this->acquisitionCount = 0;
        $this->failureCount = 0;
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
        $last = $this->lastResult instanceof AcquisitionResult
            ? $this->lastResult->toArray()
            : null;

        if (is_array($last) && isset($last['evidence']['body'])) {
            $last['evidence']['body'] = '';
            $last['evidence']['body_omitted'] = true;
        }

        return array(
            'acquisition_engine' => 'ready',
            'acquisition_engine_version' => AcquisitionEngine::VERSION,
            'platform_version' => $this->versionRegistry->get('platform'),
            'plugin_version' => $this->versionRegistry->get('plugin'),
            'capability_acquisition_enabled' => $this->capabilityRegistry->isEnabled(
                CapabilityRegistry::ACQUISITION
            ),
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
            'last_result' => $last,
        );
    }
}
