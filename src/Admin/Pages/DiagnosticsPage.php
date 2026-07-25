<?php

namespace StudyMentor\ContentEngine\Admin\Pages;

use StudyMentor\ContentEngine\Core\FeatureFlags;
use StudyMentor\ContentEngine\Core\Requirements;

defined('ABSPATH') || exit;

final class DiagnosticsPage
{
    private $featureFlags;

    public function __construct(FeatureFlags $featureFlags)
    {
        $this->featureFlags = $featureFlags;
    }

    public function render()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html('You do not have permission to view this page.'));
        }

        $requirementResult = Requirements::check();
        $flags = array();

        foreach ($this->featureFlags->all() as $name => $enabled) {
            $flags[] = array(
                'name' => ucwords(str_replace('_', ' ', $name)),
                'state' => $enabled ? 'ON' : 'OFF',
            );
        }

        $data = array(
            'title' => 'StudyMentor Content Engine Diagnostics',
            'environment' => array(
                'Plugin version' => SMCE_VERSION,
                'Database schema version target' => SMCE_DB_VERSION,
                'PHP version' => PHP_VERSION,
                'WordPress version' => get_bloginfo('version'),
                'Minimum PHP version' => SMCE_MIN_PHP,
                'Minimum WordPress version' => SMCE_MIN_WP,
                'Environment requirements' => $requirementResult['met']
                    ? 'PASS'
                    : 'FAIL: ' . $requirementResult['message'],
            ),
            'flags' => $flags,
            'confirmations' => array(
                'Phase' => 'Phase 1A2 database foundation',
                'Database schema' => 'Activation-only foundation (smce_sources, smce_source_items)',
                'Seeded sources' => 'None',
                'Seeded source items' => 'None',
                'Collection' => 'Inactive',
                'Processing' => 'Inactive',
                'Publishing' => 'Inactive',
                'News' . 'letter integration' => 'Inactive',
                'Social integration' => 'None',
                'AI integration' => 'None',
                'Scheduling' => 'None',
                'Public routes' => 'None',
                'Operational settings' => 'None',
                'Client panel dependency' => 'None',
            ),
        );

        require SMCE_PLUGIN_DIR . 'views/admin/diagnostics.php';
    }
}
