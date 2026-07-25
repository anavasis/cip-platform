<?php

namespace StudyMentor\ContentEngine\Core;

defined('ABSPATH') || exit;

final class FeatureFlags
{
    private const FLAGS = array(
        'source_registry' => true,
        'source_collection' => false,
        'document_collection' => false,
        'extraction' => false,
        'ai_providers' => false,
        'deduplication' => false,
        'article_generation' => false,
        'image_generation' => false,
        'approval_workflow' => false,
        'wordpress_publishing' => false,
        'social_distribution' => false,
        'newsletter_distribution' => false,
        'scheduling' => false,
    );

    public function isEnabled($name)
    {
        return isset(self::FLAGS[$name]) && self::FLAGS[$name] === true;
    }

    public function all()
    {
        return self::FLAGS;
    }
}
