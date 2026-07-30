<?php

/**
 * CIP-003D collector activation smoke test.
 *
 * Exercises SourceAcquisitionService as the canonical acquisition entry point
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
use StudyMentor\ContentEngine\Acquisition\AcquisitionEngine;
use StudyMentor\ContentEngine\Acquisition\SourceAcquisitionService;
use StudyMentor\ContentEngine\Admin\SourceCheckService;
use StudyMentor\ContentEngine\Collectors\CollectorRegistry;
use StudyMentor\ContentEngine\Core\ModuleLoader;
use StudyMentor\ContentEngine\Core\ModuleRegistry;
use StudyMentor\ContentEngine\Core\Plugin;
use StudyMentor\ContentEngine\Core\ServiceContainer;
use StudyMentor\ContentEngine\Evidence\EvidenceRepositoryInterface;
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

// --- SourceAcquisitionService resolves and shares AcquisitionEngine ---

$sourceAcquisitionService = $container->get(SourceAcquisitionService::class);
$acquisitionEngine = $container->get(AcquisitionEngine::class);

assertInstance(SourceAcquisitionService::class, $sourceAcquisitionService, 'SourceAcquisitionService must resolve from container');
assertSameValue(
    spl_object_id($acquisitionEngine),
    spl_object_id(readPrivateProperty($sourceAcquisitionService, 'acquisitionEngine')),
    'SourceAcquisitionService must share container AcquisitionEngine'
);

// --- Collector routing ready after boot ---

$collectorRegistry = $container->get(CollectorRegistry::class);
$sourceTypeMap = $collectorRegistry->sourceTypeMap();

foreach (array('rss', 'atom', 'html') as $sourceType) {
    assertTrue(
        isset($sourceTypeMap[$sourceType]) && $sourceTypeMap[$sourceType] === 'safe_feed',
        'CollectorRegistry must map ' . $sourceType . ' to safe_feed after boot'
    );
}

// --- SourceAcquisitionService pre-acquire error path ---

$invalidResult = $sourceAcquisitionService->acquireFromSource(0);
assertTrue($invalidResult->success() === false, 'acquireFromSource(0) must fail');
assertSameValue('invalid_id', $invalidResult->errorCode(), 'acquireFromSource(0) must return invalid_id');

// --- Collector routing through engine on pre-fetch failure ---

$evidenceRepository = $container->get(EvidenceRepositoryInterface::class);
$acquisitionDiagnostics = $container->get(AcquisitionDiagnostics::class);
$evidenceCountBefore = $evidenceRepository->count();
$recordedBefore = $acquisitionDiagnostics->status()['acquisitions_recorded'];

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
assertSameValue(
    'safe_feed',
    $acquireResult->metrics()->collectorId(),
    'Collector routing must resolve safe_feed for rss source type'
);
assertSameValue(
    $evidenceCountBefore,
    $evidenceRepository->count(),
    'Evidence must not be stored on pre-fetch acquisition failure'
);
assertSameValue(
    $recordedBefore + 1,
    $acquisitionDiagnostics->status()['acquisitions_recorded'],
    'AcquisitionDiagnostics must record engine acquisition attempts'
);

// --- Diagnostics routing-ready terminology ---

$diagnosticsStatus = $acquisitionDiagnostics->status();
assertSameValue('active', $diagnosticsStatus['collector_routing'], 'Collector routing must report active when capability enabled');
assertTrue(
    isset($diagnosticsStatus['source_type_map']) && is_array($diagnosticsStatus['source_type_map']),
    'AcquisitionDiagnostics must expose source_type_map'
);

$platformDiagnostics = $container->get(PlatformDiagnostics::class);
$diagnostics = $platformDiagnostics->collect();

assertSameValue(
    'cip-005-production-orchestrator',
    $diagnostics['versions']['platform_phase'],
    'PlatformDiagnostics phase label must match CIP-003D label'
);
assertSameValue(
    'Active',
    $diagnostics['confirmations']['collector_routing'],
    'PlatformDiagnostics collector_routing confirmation must be Active'
);
assertTrue(
    $diagnostics['confirmations']['acquisition'] === 'Active',
    'PlatformDiagnostics acquisition confirmation must be Active'
);

// --- SourceCheckService contract preserved via SourceAcquisitionService ---

$sourceCheckService = $container->get(SourceCheckService::class);
assertSameValue(
    spl_object_id($sourceAcquisitionService),
    spl_object_id(readPrivateProperty($sourceCheckService, 'sourceAcquisitionService')),
    'SourceCheckService must share container SourceAcquisitionService'
);

$invalidCheck = $sourceCheckService->check(0);
assertSameValue(false, $invalidCheck['success'], 'SourceCheckService::check(0) must fail');
assertSameValue('invalid_id', $invalidCheck['error_code'], 'SourceCheckService::check(0) must return invalid_id');
assertSameValue('', $invalidCheck['requested_url'], 'SourceCheckService invalid_id must preserve empty requested_url');
assertSameValue(0, $invalidCheck['item_count'], 'SourceCheckService invalid_id must preserve item_count');

$capabilityRegistry = $container->get(CapabilityRegistry::class);
assertTrue(
    $capabilityRegistry->isEnabled(CapabilityRegistry::ACQUISITION) === true,
    'acquisition capability must be enabled in CIP-003D'
);

if ($failures !== array()) {
    fwrite(STDERR, "CIP-003D collector activation smoke test failed.\n");

    foreach ($failures as $failure) {
        fwrite(STDERR, '- ' . $failure . "\n");
    }

    exit(1);
}

echo "CIP-003D collector activation smoke test passed.\n";
echo 'Assertions: ' . $passed . "\n";
