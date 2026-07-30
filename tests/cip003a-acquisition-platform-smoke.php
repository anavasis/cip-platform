<?php

/**
 * CIP-003A acquisition platform integration smoke test.
 *
 * Exercises module registration and SourceCheckService ownership without
 * WordPress boot, handler registration, or network I/O.
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

use StudyMentor\ContentEngine\Admin\SourceCheckService;
use StudyMentor\ContentEngine\Core\ModuleLoader;
use StudyMentor\ContentEngine\Core\ModuleRegistry;
use StudyMentor\ContentEngine\Core\Plugin;
use StudyMentor\ContentEngine\Core\ServiceContainer;
use StudyMentor\ContentEngine\Data\SourceRepository;
use StudyMentor\ContentEngine\Http\SafeFeedFetcher;
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

/**
 * @param object $object
 * @param string $propertyName
 * @return mixed
 */
function readPrivateProperty($object, $propertyName)
{
    $reflection = new \ReflectionProperty($object, $propertyName);
    $reflection->setAccessible(true);

    return $reflection->getValue($object);
}

// --- Composition root via Plugin ---

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

assertTrue($moduleRegistry->has('acquisition'), 'acquisition module must be registered');
assertInstance(AcquisitionModule::class, $moduleRegistry->get('acquisition'), 'acquisition module must resolve from registry');

// --- Capability surface remains unchanged ---

$capabilityRegistry = $container->get(CapabilityRegistry::class);

foreach ($capabilityRegistry->all() as $capabilityId => $enabled) {
    if ($capabilityId === CapabilityRegistry::SOURCE_REGISTRY) {
        assertTrue($enabled === true, 'source_registry capability must be enabled');
        continue;
    }

    assertTrue($enabled === false, 'Capability must remain disabled: ' . $capabilityId);
}

assertTrue(
    $capabilityRegistry->isEnabled(CapabilityRegistry::ACQUISITION) === false,
    'acquisition capability must remain disabled in CIP-003A'
);

// --- Version and diagnostics integration ---

$versionRegistry = $container->get(VersionRegistry::class);
assertSameValue(
    'cip-003a-acquisition-platform-integration',
    $versionRegistry->get('platform_phase'),
    'VersionRegistry platform phase must match CIP-003A label'
);

$platformDiagnostics = $container->get(PlatformDiagnostics::class);
$diagnostics = $platformDiagnostics->collect();

assertSameValue(
    'cip-003a-acquisition-platform-integration',
    $diagnostics['versions']['platform_phase'],
    'PlatformDiagnostics phase label must match CIP-003A label'
);
assertTrue(in_array('acquisition', $diagnostics['module_ids'], true), 'PlatformDiagnostics must include acquisition');
assertTrue(
    $diagnostics['confirmations']['acquisition'] === 'Inactive',
    'PlatformDiagnostics acquisition confirmation must remain Inactive'
);

// --- SourceCheckService ownership via AcquisitionModule ---

$sourceRepository = $container->get(SourceRepository::class);
$safeFeedFetcher = $container->get(SafeFeedFetcher::class);
$sourceCheckService = $container->get(SourceCheckService::class);

assertInstance(SourceCheckService::class, $sourceCheckService, 'SourceCheckService must resolve from container');
assertSameValue(
    spl_object_id($sourceRepository),
    spl_object_id(readPrivateProperty($sourceCheckService, 'repository')),
    'SourceCheckService must share container SourceRepository'
);
assertSameValue(
    spl_object_id($safeFeedFetcher),
    spl_object_id(readPrivateProperty($sourceCheckService, 'feedFetcher')),
    'SourceCheckService must share container SafeFeedFetcher'
);

if ($failures !== array()) {
    fwrite(STDERR, "CIP-003A acquisition platform smoke test failed.\n");

    foreach ($failures as $failure) {
        fwrite(STDERR, '- ' . $failure . "\n");
    }

    exit(1);
}

echo "CIP-003A acquisition platform smoke test passed.\n";
echo 'Assertions: ' . $passed . "\n";
