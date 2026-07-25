<?php

namespace StudyMentor\ContentEngine\Admin\Pages;

use StudyMentor\ContentEngine\Core\FeatureFlags;

defined('ABSPATH') || exit;

final class DashboardPage
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

        $flags = array();

        foreach ($this->featureFlags->all() as $name => $enabled) {
            $flags[] = array(
                'name' => ucwords(str_replace('_', ' ', $name)),
                'state' => $enabled ? 'ON' : 'OFF',
            );
        }

        $data = array(
            'plugin_name' => 'StudyMentor Content Engine',
            'version' => SMCE_VERSION,
            'phase' => 'Phase 1A2 database foundation',
            'inactive_statement' => 'After successful activation, two internal schema '
                . 'tables are installed with no seeded sources or source items. No '
                . 'collection, processing, publishing, Newsletter, social, AI, or '
                . 'scheduling functionality is active. All operational feature flags '
                . 'remain OFF and no operational settings are available.',
            'flags' => $flags,
        );

        require SMCE_PLUGIN_DIR . 'views/admin/dashboard.php';
    }
}
