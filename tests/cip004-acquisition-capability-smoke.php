<?php

/**
 * CIP-004 acquisition capability enablement smoke test.
 *
 * Exercises capability enablement, startup validation, and diagnostics
 * without WordPress boot, handler registration, or network I/O.
 */

$pluginDirectory = dirname(__DIR__);

if (!defined('ABSPATH')) {
    define('ABSPATH', $pluginDirectory . '/');
}

if (!defined('SMCE_PLUGIN_DIR')) {
    define('SMCE_PLUGIN_DIR', $pluginDirectory . '/');
}

if (!defined('SMCE_VERSION')) {
    define('SMCE_VERSION', '0.9.1');
}

if (!defined('SMCE_DB_VERSION')) {
    define('SMCE_DB_VERSION', '1.0.0');
}

$GLOBALS['wpdb'] = (object) array(
    'prefix' => 'wp_',
);

require_once $pluginDirectory . '/src/Core/Autoloader.php';

use StudyMentor\ContentEngine\Acquisition\AcquisitionDiagnostics;
use StudyMentor\ContentEngine\Acquisition\SourceAcquisitionService;
use StudyMentor\ContentEngine\Admin\SourceCheckService;
use StudyMentor\ContentEngine\Core\FeatureFlags;
use StudyMentor\ContentEngine\Core\ModuleLoader;
use StudyMentor\ContentEngine\Core\ModuleRegistry;
use StudyMentor\ContentEngine\Core\Plugin;
use StudyMentor\ContentEngine\Core\ServiceContainer;
use StudyMentor\ContentEngine\Modules\AcquisitionModule;
use StudyMentor\ContentEngine\Modules\CorePlatformModule;
use StudyMentor\ContentEngine\Modules\SourceRegistryModule;
use StudyMentor\ContentEngine\Platform\PlatformDiagnostics;
use StudyMentor\ContentEngine\Registry\CapabilityRegistry;
use StudyMentor\ContentEngine\Registry\VersionRegistry;

StudyMentor\ContentEngine\Core\Autoloader::register($pluginDirectory . '/src');

$failures = array();
$passed = 0;

/**
 * @param bool $condition
 * @param string $message
 * @return void
 */
function assertTrue($condition, $message)
{
    global $failures, $passed;

    if ($condition) {
        ++$passed;
        return;
    }

    $failures[] = $message;
}

/**
 * @param mixed $expected
 * @param mixed $actual
 * @param string $message
 * @return void
 */
function assertSameValue($expected, $actual, $message)
{
    assertTrue($expected === $actual, $message . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')');
}

/**
 * @param string $expectedClass
 * @param object $object
 * @param string $message
 * @return void
 */
function assertInstance($expectedClass, $object, $message)
{
    assertTrue($object instanceof $expectedClass, $message);
}

// --- Feature flag and capability enablement ---

$plugin = new Plugin();
assertInstance(Plugin::class, $plugin, 'Plugin must construct with acquisition module registered');

$container = new ServiceContainer();
$moduleRegistry = new ModuleRegistry();
$container->set(ModuleRegistry::class, $moduleRegistry);
$moduleRegistry->register(new CorePlatformModule());
$moduleRegistry->register(new SourceRegistryModule());
$moduleRegistry->register(new AcquisitionModule());

$moduleLoader = new ModuleLoader($moduleRegistry, $container);
$moduleLoader->load();
assertSameValue(ModuleLoader::STATE_LOADED, $moduleLoader->state(), 'ModuleLoader must reach loaded state');

$featureFlags = $container->get(FeatureFlags::class);
$capabilityRegistry = $container->get(CapabilityRegistry::class);

assertTrue(
    $featureFlags->isEnabled('source_collection') === true,
    'source_collection feature flag must be enabled'
);
assertTrue(
    $capabilityRegistry->isEnabled(CapabilityRegistry::ACQUISITION) === true,
    'acquisition capability must be enabled'
);
assertTrue(
    $capabilityRegistry->isEnabled(CapabilityRegistry::SOURCE_REGISTRY) === true,
    'source_registry capability must remain enabled'
);

// --- Startup validation and acquisition diagnostics ---

$acquisitionDiagnostics = $container->get(AcquisitionDiagnostics::class);
$diagnosticsStatus = $acquisitionDiagnostics->status();

assertSameValue('active', $diagnosticsStatus['acquisition_runtime'], 'Acquisition runtime must be active');
assertSameValue('active', $diagnosticsStatus['acquisition_engine'], 'Acquisition engine must be active');
assertSameValue('active', $diagnosticsStatus['collector_routing'], 'Collector routing must be active');
assertTrue(
    isset($diagnosticsStatus['startup_validation'])
    && is_array($diagnosticsStatus['startup_validation']),
    'AcquisitionDiagnostics must expose startup_validation'
);
assertSameValue(
    'passed',
    $diagnosticsStatus['startup_validation']['status'],
    'Startup validation must pass when acquisition capability is enabled'
);
assertTrue(
    isset($diagnosticsStatus['startup_validation']['checks']['safe_feed_collector'])
    && $diagnosticsStatus['startup_validation']['checks']['safe_feed_collector'] === true,
    'Startup validation must confirm safe_feed collector'
);

// --- Platform diagnostics confirmations ---

$platformDiagnostics = $container->get(PlatformDiagnostics::class);
$diagnostics = $platformDiagnostics->collect();

assertSameValue(
    'editorial-spine-phase1-announcement-lifecycle',
    $diagnostics['versions']['platform_phase'],
    'PlatformDiagnostics phase label must match CIP-004 label'
);
assertSameValue(
    'Active',
    $diagnostics['confirmations']['acquisition'],
    'PlatformDiagnostics acquisition confirmation must be Active'
);
assertSameValue(
    'Passed',
    $diagnostics['confirmations']['startup_validation'],
    'PlatformDiagnostics startup_validation confirmation must be Passed'
);
assertSameValue(
    'Active',
    $diagnostics['confirmations']['collector_routing'],
    'PlatformDiagnostics collector_routing confirmation must be Active'
);

$versionRegistry = $container->get(VersionRegistry::class);
assertSameValue(
    'editorial-spine-phase1-announcement-lifecycle',
    $versionRegistry->get('platform_phase'),
    'VersionRegistry platform phase must match CIP-004 label'
);

// --- Source Check remains operational without engine gate ---

$sourceCheckService = $container->get(SourceCheckService::class);
$sourceAcquisitionService = $container->get(SourceAcquisitionService::class);

assertInstance(SourceCheckService::class, $sourceCheckService, 'SourceCheckService must resolve from container');
assertInstance(SourceAcquisitionService::class, $sourceAcquisitionService, 'SourceAcquisitionService must resolve from container');

$invalidCheck = $sourceCheckService->check(0);
assertSameValue(false, $invalidCheck['success'], 'SourceCheckService::check(0) must fail');
assertSameValue('invalid_id', $invalidCheck['error_code'], 'SourceCheckService::check(0) must return invalid_id');

$acquireResult = $sourceAcquisitionService->acquire(array(
    'source_id' => 1,
    'source_key' => 'test-source',
    'url' => '',
    'allowed_domains' => array('example.com'),
    'source_type' => 'rss',
    'parser_profile' => '',
));

assertTrue($acquireResult->success() === false, 'acquire() must fail for missing feed URL');
assertSameValue('missing_feed_url', $acquireResult->errorCode(), 'acquire() must return missing_feed_url');

if ($failures !== array()) {
    fwrite(STDERR, "CIP-004 acquisition capability smoke test failed.\n");

    foreach ($failures as $failure) {
        fwrite(STDERR, '- ' . $failure . "\n");
    }

    exit(1);
}

echo "CIP-004 acquisition capability smoke test passed.\n";
echo 'Assertions: ' . $passed . "\n";
