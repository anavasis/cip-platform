<?php

namespace StudyMentor\ContentEngine\Registry;

defined('ABSPATH') || exit;

final class VersionRegistry
{
    public const PLATFORM_PHASE = 'cip-002-platform-foundation';

    /** @var array<string, string> */
    private $versions = array();

    public function __construct()
    {
        $pluginVersion = defined('SMCE_VERSION') ? (string) SMCE_VERSION : '0.0.0';
        $dbVersion = defined('SMCE_DB_VERSION') ? (string) SMCE_DB_VERSION : '0.0.0';

        $this->versions = array(
            'plugin' => $pluginVersion,
            'database' => $dbVersion,
            'platform_phase' => self::PLATFORM_PHASE,
        );
    }

    /**
     * @param string $component
     * @return string
     */
    public function get($component)
    {
        $key = (string) $component;

        return isset($this->versions[$key]) ? (string) $this->versions[$key] : '';
    }

    /**
     * @return array<string, string>
     */
    public function all()
    {
        return $this->versions;
    }
}
