<?php

/**
 * CIP-003B acquisition engine import smoke test.
 *
 * Exercises passive engine DI registration without acquisition runtime
 * activation, WordPress boot, handler registration, or network I/O.
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
use StudyMentor\ContentEngine\Acquisition\AcquisitionEngine;
use StudyMentor\ContentEngine\Acquisition\SourceAcquisitionService;
use StudyMentor\ContentEngine\Acquisition\AcquisitionManager;
use StudyMentor\ContentEngine\Acquisition\DownloadManager;
use StudyMentor\ContentEngine\Admin\SourceCheckService;
use StudyMentor\ContentEngine\Collectors\CollectorRegistry;
use StudyMentor\ContentEngine\Core\ModuleLoader;
use StudyMentor\ContentEngine\Core\ModuleRegistry;
use StudyMentor\ContentEngine\Core\Plugin;
use StudyMentor\ContentEngine\Core\ServiceContainer;
use StudyMentor\ContentEngine\Data\SourceRepository;
use StudyMentor\ContentEngine\Evidence\EvidenceRepositoryInterface;
use StudyMentor\ContentEngine\Evidence\InMemoryEvidenceRepository;
use StudyMentor\ContentEngine\Feed\AsepAnnouncementsHtmlParser;
use StudyMentor\ContentEngine\Feed\FeedPreviewParser;
use StudyMentor\ContentEngine\Fingerprint\FingerprintService;
use StudyMentor\ContentEngine\Http\SafeFeedFetcher;
use StudyMentor\ContentEngine\Modules\AcquisitionModule;
use StudyMentor\ContentEngine\Modules\CorePlatformModule;
use StudyMentor\ContentEngine\Modules\SourceRegistryModule;
use StudyMentor\ContentEngine\Platform\PlatformDiagnostics;
use StudyMentor\ContentEngine\Registry\CapabilityRegistry;
use StudyMentor\ContentEngine\Registry\ParserRegistry;
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

// --- Passive engine services resolve from container ---

assertInstance(CollectorRegistry::class, $container->get(CollectorRegistry::class), 'CollectorRegistry must resolve from container');
assertInstance(ParserRegistry::class, $container->get(ParserRegistry::class), 'ParserRegistry must resolve from container');
assertInstance(FingerprintService::class, $container->get(FingerprintService::class), 'FingerprintService must resolve from container');
assertInstance(DownloadManager::class, $container->get(DownloadManager::class), 'DownloadManager must resolve from container');
assertInstance(InMemoryEvidenceRepository::class, $container->get(EvidenceRepositoryInterface::class), 'EvidenceRepositoryInterface must resolve to InMemoryEvidenceRepository');
assertInstance(AcquisitionDiagnostics::class, $container->get(AcquisitionDiagnostics::class), 'AcquisitionDiagnostics must resolve from container');
assertInstance(AcquisitionManager::class, $container->get(AcquisitionManager::class), 'AcquisitionManager must resolve from container');
assertInstance(AcquisitionEngine::class, $container->get(AcquisitionEngine::class), 'AcquisitionEngine must resolve from container');

// --- Capability surface remains unchanged ---

$capabilityRegistry = $container->get(CapabilityRegistry::class);

foreach ($capabilityRegistry->all() as $capabilityId => $enabled) {
    if ($capabilityId === CapabilityRegistry::SOURCE_REGISTRY) {
        assertTrue($enabled === true, 'source_registry capability must be enabled');
        continue;
    }

    if ($capabilityId === CapabilityRegistry::ACQUISITION) {
        assertTrue($enabled === true, 'acquisition capability must be enabled');
        continue;
    }

    assertTrue($enabled === false, 'Capability must remain disabled: ' . $capabilityId);
}

assertTrue(
    $capabilityRegistry->isEnabled(CapabilityRegistry::ACQUISITION) === true,
    'acquisition capability must be enabled in CIP-003B'
);

// --- Version and diagnostics integration ---

$versionRegistry = $container->get(VersionRegistry::class);
assertSameValue(
    'editorial-workspace-phase2',
    $versionRegistry->get('platform_phase'),
    'VersionRegistry platform phase must match CIP-003D label'
);

$platformDiagnostics = $container->get(PlatformDiagnostics::class);
$diagnostics = $platformDiagnostics->collect();

assertSameValue(
    'editorial-workspace-phase2',
    $diagnostics['versions']['platform_phase'],
    'PlatformDiagnostics phase label must match CIP-003D label'
);
assertTrue(in_array('acquisition', $diagnostics['module_ids'], true), 'PlatformDiagnostics must include acquisition');
assertTrue(
    $diagnostics['confirmations']['acquisition'] === 'Active',
    'PlatformDiagnostics acquisition confirmation must be Active'
);

// --- SourceCheckService ownership via AcquisitionModule ---

$sourceAcquisitionService = $container->get(SourceAcquisitionService::class);
$sourceCheckService = $container->get(SourceCheckService::class);

assertInstance(SourceCheckService::class, $sourceCheckService, 'SourceCheckService must resolve from container');
assertSameValue(
    spl_object_id($sourceAcquisitionService),
    spl_object_id(readPrivateProperty($sourceCheckService, 'sourceAcquisitionService')),
    'SourceCheckService must share container SourceAcquisitionService'
);

if ($failures !== array()) {
    fwrite(STDERR, "CIP-003B acquisition engine smoke test failed.\n");

    foreach ($failures as $failure) {
        fwrite(STDERR, '- ' . $failure . "\n");
    }

    exit(1);
}

echo "CIP-003B acquisition engine smoke test passed.\n";
echo 'Assertions: ' . $passed . "\n";
