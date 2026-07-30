<?php

namespace StudyMentor\ContentEngine\Platform;

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

    public function __construct(
        ModuleRegistry $moduleRegistry,
        CapabilityRegistry $capabilityRegistry,
        FeatureFlags $featureFlags,
        VersionRegistry $versionRegistry
    ) {
        $this->moduleRegistry = $moduleRegistry;
        $this->capabilityRegistry = $capabilityRegistry;
        $this->featureFlags = $featureFlags;
        $this->versionRegistry = $versionRegistry;
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

        return array(
            'versions' => $this->versionRegistry->all(),
            'module_ids' => $moduleIds,
            'capabilities' => $capabilities,
            'feature_flags' => $flags,
            'confirmations' => array(
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
}
