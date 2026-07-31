<?php

namespace StudyMentor\ContentEngine\Blueprint;

defined('ABSPATH') || exit;

/**
 * Supported article types for Content Blueprints.
 */
final class ArticleType
{
    public const NEWS_BRIEF = 'news_brief';
    public const EXPLAINER = 'explainer';
    public const GUIDE = 'guide';
    public const FAQ_ARTICLE = 'faq_article';

    private function __construct()
    {
    }

    /**
     * @param string $type
     * @return bool
     */
    public static function isValid($type)
    {
        return in_array((string) $type, self::all(), true);
    }

    /**
     * @return array<int, string>
     */
    public static function all()
    {
        return array(
            self::NEWS_BRIEF,
            self::EXPLAINER,
            self::GUIDE,
            self::FAQ_ARTICLE,
        );
    }
}
