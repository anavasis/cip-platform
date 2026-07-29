<?php

namespace StudyMentor\ContentEngine\Registry;

defined('ABSPATH') || exit;

final class CapabilityRegistry
{
    public const SOURCE_REGISTRY = 'source_registry';
    public const ACQUISITION = 'acquisition';
    public const EVENT_DETECTION = 'event_detection';
    public const COMMERCIAL_INTELLIGENCE = 'commercial_intelligence';
    public const SERVICE_INTELLIGENCE = 'service_intelligence';
    public const MARKETING_INTELLIGENCE = 'marketing_intelligence';
    public const LEARNING_DECISION_INTELLIGENCE = 'learning_decision_intelligence';
    public const KNOWLEDGE_GRAPH = 'knowledge_graph';
    public const CONTENT_PLANNING = 'content_planning';
    public const CONTENT_GENERATION = 'content_generation';
    public const SEO = 'seo';
    public const REVIEW_WORKFLOW = 'review_workflow';
    public const PUBLISHING = 'publishing';
    public const DISTRIBUTION = 'distribution';
    public const CAMPAIGNS = 'campaigns';
    public const SCHEDULING = 'scheduling';
    public const AI_PROVIDERS = 'ai_providers';
    public const ANALYTICS = 'analytics';
    public const ATTRIBUTION = 'attribution';

    /** @var array<string, bool> */
    private $capabilities = array();

    public function __construct(CapabilityFlagMapper $mapper)
    {
        $this->capabilities = array(
            self::SOURCE_REGISTRY => false,
            self::ACQUISITION => false,
            self::EVENT_DETECTION => false,
            self::COMMERCIAL_INTELLIGENCE => false,
            self::SERVICE_INTELLIGENCE => false,
            self::MARKETING_INTELLIGENCE => false,
            self::LEARNING_DECISION_INTELLIGENCE => false,
            self::KNOWLEDGE_GRAPH => false,
            self::CONTENT_PLANNING => false,
            self::CONTENT_GENERATION => false,
            self::SEO => false,
            self::REVIEW_WORKFLOW => false,
            self::PUBLISHING => false,
            self::DISTRIBUTION => false,
            self::CAMPAIGNS => false,
            self::SCHEDULING => false,
            self::AI_PROVIDERS => false,
            self::ANALYTICS => false,
            self::ATTRIBUTION => false,
        );

        foreach (array_keys($this->capabilities) as $capabilityId) {
            $mapped = $mapper->isCapabilityEnabledByFlags($capabilityId);

            if ($mapped === true) {
                $this->capabilities[$capabilityId] = true;
            }
        }
    }

    /**
     * @param string $capability
     * @return bool
     */
    public function has($capability)
    {
        return array_key_exists((string) $capability, $this->capabilities);
    }

    /**
     * @param string $capability
     * @return bool
     */
    public function isEnabled($capability)
    {
        $key = (string) $capability;

        return isset($this->capabilities[$key]) && $this->capabilities[$key] === true;
    }

    /**
     * @return array<string, bool>
     */
    public function all()
    {
        return $this->capabilities;
    }
}
