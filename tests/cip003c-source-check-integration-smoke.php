<?php

/**
 * CIP-003C source check integration smoke test.
 *
 * Exercises SourceCheckService delegation to AcquisitionEngine without
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
    define('SMCE_VERSION', '0.10.0');
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
use StudyMentor\ContentEngine\Data\SourceRepository;
use StudyMentor\ContentEngine\Evidence\EvidenceRepositoryInterface;
use StudyMentor\ContentEngine\Feed\AsepAnnouncementsHtmlParser;
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

// --- Collector source-type mappings activated in boot() ---

$collectorRegistry = $container->get(CollectorRegistry::class);
$sourceTypeMap = $collectorRegistry->sourceTypeMap();

foreach (array('rss', 'atom', 'html') as $sourceType) {
    assertTrue(
        isset($sourceTypeMap[$sourceType]) && $sourceTypeMap[$sourceType] === 'safe_feed',
        'CollectorRegistry must map ' . $sourceType . ' to safe_feed after boot'
    );
}

// --- ParserRegistry runtime resolution ---

$parserRegistry = $container->get(ParserRegistry::class);

assertTrue(
    $parserRegistry->supports('rss', ''),
    'ParserRegistry must support rss source type'
);
assertTrue(
    $parserRegistry->supports('html', AsepAnnouncementsHtmlParser::SUPPORTED_PROFILE),
    'ParserRegistry must support asep html parser profile'
);

// --- SourceCheckService delegates to AcquisitionEngine ---

$sourceAcquisitionService = $container->get(SourceAcquisitionService::class);
$acquisitionEngine = $container->get(AcquisitionEngine::class);
$sourceCheckService = $container->get(SourceCheckService::class);

assertInstance(SourceCheckService::class, $sourceCheckService, 'SourceCheckService must resolve from container');
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

// --- AcquisitionEngine error path without network ---

$evidenceRepository = $container->get(EvidenceRepositoryInterface::class);
$acquisitionDiagnostics = $container->get(AcquisitionDiagnostics::class);
$evidenceCountBefore = $evidenceRepository->count();

$acquireResult = $acquisitionEngine->acquire(array(
    'source_id' => 1,
    'source_key' => 'test-source',
    'url' => '',
    'allowed_domains' => array('example.com'),
    'source_type' => 'rss',
    'parser_profile' => '',
));

assertTrue($acquireResult->success() === false, 'AcquisitionEngine must fail for missing feed URL');
assertSameValue('missing_feed_url', $acquireResult->errorCode(), 'AcquisitionEngine must return missing_feed_url');
assertSameValue(
    $evidenceCountBefore,
    $evidenceRepository->count(),
    'Evidence must not be stored on pre-fetch acquisition failure'
);

$diagnosticsStatus = $acquisitionDiagnostics->status();
assertSameValue('active', $diagnosticsStatus['acquisition_engine'], 'AcquisitionDiagnostics must report engine active');
assertTrue(
    $diagnosticsStatus['capability_acquisition_enabled'] === true,
    'AcquisitionDiagnostics must report acquisition capability enabled'
);
assertTrue(
    $diagnosticsStatus['acquisitions_recorded'] >= 1,
    'AcquisitionDiagnostics must record acquisition attempts'
);

// --- Platform diagnostics payload extension ---

$platformDiagnostics = $container->get(PlatformDiagnostics::class);
$diagnostics = $platformDiagnostics->collect();

assertSameValue(
    'editorial-workspace-phase2',
    $diagnostics['versions']['platform_phase'],
    'PlatformDiagnostics phase label must match CIP-003D label'
);
assertTrue(
    isset($diagnostics['acquisition_engine']) && is_array($diagnostics['acquisition_engine']),
    'PlatformDiagnostics must include acquisition_engine payload'
);
assertSameValue(
    'active',
    $diagnostics['acquisition_engine']['acquisition_engine'],
    'PlatformDiagnostics acquisition_engine status must be active'
);
assertTrue(
    $diagnostics['confirmations']['acquisition'] === 'Active',
    'PlatformDiagnostics acquisition confirmation must be Active'
);

$capabilityRegistry = $container->get(CapabilityRegistry::class);
assertTrue(
    $capabilityRegistry->isEnabled(CapabilityRegistry::ACQUISITION) === true,
    'acquisition capability must be enabled in CIP-003C'
);

if ($failures !== array()) {
    fwrite(STDERR, "CIP-003C source check integration smoke test failed.\n");

    foreach ($failures as $failure) {
        fwrite(STDERR, '- ' . $failure . "\n");
    }

    exit(1);
}

echo "CIP-003C source check integration smoke test passed.\n";
echo 'Assertions: ' . $passed . "\n";
