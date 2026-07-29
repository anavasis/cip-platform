<?php

namespace StudyMentor\ContentEngine\Registry;

use StudyMentor\ContentEngine\Core\FeatureFlags;

defined('ABSPATH') || exit;

final class CapabilityFlagMapper
{
    /** @var array<string, string> */
    private const FLAG_TO_CAPABILITY = array(
        'source_registry' => CapabilityRegistry::SOURCE_REGISTRY,
        'source_collection' => CapabilityRegistry::ACQUISITION,
        'document_collection' => CapabilityRegistry::ACQUISITION,
        'scheduling' => CapabilityRegistry::SCHEDULING,
        'ai_providers' => CapabilityRegistry::AI_PROVIDERS,
        'approval_workflow' => CapabilityRegistry::REVIEW_WORKFLOW,
        'wordpress_publishing' => CapabilityRegistry::PUBLISHING,
        'social_distribution' => CapabilityRegistry::DISTRIBUTION,
        'newsletter_distribution' => CapabilityRegistry::DISTRIBUTION,
        'article_generation' => CapabilityRegistry::CONTENT_GENERATION,
    );

    private $featureFlags;

    public function __construct(FeatureFlags $featureFlags)
    {
        $this->featureFlags = $featureFlags;
    }

    /**
     * @param string $capabilityId
     * @return bool|null null when no safe feature-flag mapping exists
     */
    public function isCapabilityEnabledByFlags($capabilityId)
    {
        $capabilityKey = (string) $capabilityId;

        foreach (self::FLAG_TO_CAPABILITY as $flagName => $mappedCapability) {
            if ($mappedCapability === $capabilityKey) {
                return $this->featureFlags->isEnabled($flagName);
            }
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    public function mappings()
    {
        return self::FLAG_TO_CAPABILITY;
    }
}
