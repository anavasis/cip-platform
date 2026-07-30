<?php

namespace StudyMentor\ContentEngine\Platform;

use StudyMentor\ContentEngine\Acquisition\AcquisitionDiagnostics;
use StudyMentor\ContentEngine\Core\FeatureFlags;
use StudyMentor\ContentEngine\Core\ModuleRegistry;
use StudyMentor\ContentEngine\Registry\CapabilityRegistry;
use StudyMentor\ContentEngine\Registry\VersionRegistry;

defined('ABSPATH') || exit;

final class PlatformDiagnostics
{
    private $moduleRegistry;
    private $capabilityRegistry;
    private $featureFlags;
    private $versionRegistry;
    private $acquisitionDiagnostics;

    public function __construct(
        ModuleRegistry $moduleRegistry,
        CapabilityRegistry $capabilityRegistry,
        FeatureFlags $featureFlags,
        VersionRegistry $versionRegistry,
        AcquisitionDiagnostics $acquisitionDiagnostics
    ) {
        $this->moduleRegistry = $moduleRegistry;
        $this->capabilityRegistry = $capabilityRegistry;
        $this->featureFlags = $featureFlags;
        $this->versionRegistry = $versionRegistry;
        $this->acquisitionDiagnostics = $acquisitionDiagnostics;
    }

    /**
     * @return array<string, mixed>
     */
    public function collect()
    {
        $moduleIds = array();

        foreach ($this->moduleRegistry->all() as $module) {
            $moduleIds[] = $module->id();
        }

        sort($moduleIds);

        $capabilities = array();

        foreach ($this->capabilityRegistry->all() as $capabilityId => $enabled) {
            $capabilities[] = array(
                'id' => $capabilityId,
                'enabled' => $enabled === true,
            );
        }

        $flags = array();

        foreach ($this->featureFlags->all() as $name => $enabled) {
            $flags[] = array(
                'name' => (string) $name,
                'enabled' => $enabled === true,
            );
        }

        $acquisitionEngineStatus = $this->acquisitionDiagnostics->status();
        $collectorRoutingState = isset($acquisitionEngineStatus['collector_routing'])
            ? (string) $acquisitionEngineStatus['collector_routing']
            : '';
        $collectorRouting = 'Not ready';

        if ($collectorRoutingState === 'active') {
            $collectorRouting = 'Active';
        } elseif ($collectorRoutingState === 'ready') {
            $collectorRouting = 'Ready';
        }

        $startupValidation = 'Not applicable';

        if (
            isset($acquisitionEngineStatus['startup_validation'])
            && is_array($acquisitionEngineStatus['startup_validation'])
            && isset($acquisitionEngineStatus['startup_validation']['status'])
        ) {
            $validationStatus = (string) $acquisitionEngineStatus['startup_validation']['status'];

            if ($validationStatus === 'passed') {
                $startupValidation = 'Passed';
            } elseif ($validationStatus === 'failed') {
                $startupValidation = 'Failed';
            }
        }

        return array(
            'versions' => $this->versionRegistry->all(),
            'module_ids' => $moduleIds,
            'capabilities' => $capabilities,
            'feature_flags' => $flags,
            'acquisition_engine' => $acquisitionEngineStatus,
            'confirmations' => array(
                'collector_routing' => $collectorRouting,
                'evidence_store' => 'In-Memory',
                'startup_validation' => $startupValidation,
                'production_orchestrator' => $this->productionOrchestratorConfirmation(
                    $acquisitionEngineStatus
                ),
                'acquisition' => $this->capabilityRegistry->isEnabled(CapabilityRegistry::ACQUISITION)
                    ? 'Active'
                    : 'Inactive',
                'ai_providers' => $this->capabilityRegistry->isEnabled(CapabilityRegistry::AI_PROVIDERS)
                    ? 'Active'
                    : 'Inactive',
                'scheduling' => $this->capabilityRegistry->isEnabled(CapabilityRegistry::SCHEDULING)
                    ? 'Active'
                    : 'Inactive',
                'publishing' => $this->capabilityRegistry->isEnabled(CapabilityRegistry::PUBLISHING)
                    ? 'Active'
                    : 'Inactive',
            ),
        );
    }

    /**
     * @param array<string, mixed> $acquisitionEngineStatus
     * @return string
     */
    private function productionOrchestratorConfirmation(array $acquisitionEngineStatus)
    {
        if (
            !isset($acquisitionEngineStatus['production_orchestrator'])
            || !is_array($acquisitionEngineStatus['production_orchestrator'])
            || !isset($acquisitionEngineStatus['production_orchestrator']['status'])
        ) {
            return 'Not ready';
        }

        $status = (string) $acquisitionEngineStatus['production_orchestrator']['status'];

        if ($status === 'running') {
            return 'Active';
        }

        if ($status === 'ready' || $status === 'idle') {
            return 'Ready';
        }

        return 'Not ready';
    }
}
