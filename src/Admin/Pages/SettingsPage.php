<?php

namespace StudyMentor\ContentEngine\Admin\Pages;

use StudyMentor\ContentEngine\Core\FeatureFlags;

defined('ABSPATH') || exit;

final class SettingsPage
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

        $flags = $this->featureFlags->all();
        $sourceRegistryEnabled = isset($flags['source_registry']) && $flags['source_registry'] === true;
        $otherOperationalFlagsOff = true;

        foreach ($flags as $name => $enabled) {
            if ($name === 'source_registry') {
                continue;
            }

            if ($enabled) {
                $otherOperationalFlagsOff = false;
                break;
            }
        }

        $flagsValid = $sourceRegistryEnabled && $otherOperationalFlagsOff;

        $data = array(
            'title' => 'StudyMentor Content Engine Settings',
            'statements' => array(
                'Phase 1A2 database foundation is installed after successful activation.',
                'Two internal schema tables exist: smce_sources and smce_source_items.',
                'No source records are seeded.',
                'No source items are seeded.',
                'No collection, processing, publishing, Newsletter, social, AI, or scheduling is active.',
                $flagsValid
                    ? 'The manual admin-only Sources Registry is enabled; all collection, processing, fetching, publishing, and integration feature flags remain OFF.'
                    : 'Feature flag safety validation failed.',
                'No operational settings are available.',
                'Later phases require separate approval.',
            ),
        );

        require SMCE_PLUGIN_DIR . 'views/admin/settings.php';
    }
}
