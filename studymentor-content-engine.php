<?php
/**
 * Plugin Name: StudyMentor Content Engine
 * Description: StudyMentor Content Engine — Editorial Foundation baseline.
 * Version: 0.10.0
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: StudyMentor
 * Text Domain: studymentor-content-engine
 */

defined('ABSPATH') || exit;

define('SMCE_VERSION', '0.10.0');
define('SMCE_DB_VERSION', '1.0.0');
define('SMCE_PLUGIN_FILE', __FILE__);
define('SMCE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SMCE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SMCE_MIN_PHP', '7.4');
define('SMCE_MIN_WP', '5.8');

require_once SMCE_PLUGIN_DIR . 'src/Core/Autoloader.php';

\StudyMentor\ContentEngine\Core\Autoloader::register(SMCE_PLUGIN_DIR . 'src');

register_activation_hook(
    SMCE_PLUGIN_FILE,
    array(\StudyMentor\ContentEngine\Core\Activator::class, 'activate')
);
register_deactivation_hook(
    SMCE_PLUGIN_FILE,
    array(\StudyMentor\ContentEngine\Core\Deactivator::class, 'deactivate')
);

$smce_requirements = \StudyMentor\ContentEngine\Core\Requirements::check();

if (!$smce_requirements['met']) {
    if (is_admin()) {
        \StudyMentor\ContentEngine\Core\Requirements::registerAdminNotice(
            $smce_requirements['message']
        );
    }

    return;
}

if (is_admin()) {
    $smce_plugin = new \StudyMentor\ContentEngine\Core\Plugin();
    $smce_plugin->boot();
}
