<?php

/**
 * CIP-005 production orchestrator smoke test.
 *
 * Exercises the production acquisition entry point, capability gate placement,
 * and diagnostics without WordPress boot, handler registration, or network I/O.
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
use StudyMentor\ContentEngine\Acquisition\AcquisitionRunResult;
use StudyMentor\ContentEngine\Acquisition\ProductionAcquisitionOrchestrator;
use StudyMentor\ContentEngine\Acquisition\SourceAcquisitionService;
use StudyMentor\ContentEngine\Admin\SourceCheckService;
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

// --- Composition root ---

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

// --- Orchestrator resolves and shares SourceAcquisitionService ---

$orchestrator = $container->get(ProductionAcquisitionOrchestrator::class);
$sourceAcquisitionService = $container->get(SourceAcquisitionService::class);
$sourceCheckService = $container->get(SourceCheckService::class);
$capabilityRegistry = $container->get(CapabilityRegistry::class);

assertInstance(
    ProductionAcquisitionOrchestrator::class,
    $orchestrator,
    'ProductionAcquisitionOrchestrator must resolve from container'
);
assertSameValue(
    spl_object_id($sourceAcquisitionService),
    spl_object_id(readPrivateProperty($orchestrator, 'sourceAcquisitionService')),
    'ProductionAcquisitionOrchestrator must share container SourceAcquisitionService'
);
assertSameValue(
    spl_object_id($sourceAcquisitionService),
    spl_object_id(readPrivateProperty($sourceCheckService, 'sourceAcquisitionService')),
    'SourceCheckService must share container SourceAcquisitionService'
);
assertTrue(
    $capabilityRegistry->isEnabled(CapabilityRegistry::ACQUISITION) === true,
    'acquisition capability must be enabled for production orchestrator'
);

// --- Empty run rejected without engine call ---

$emptyRun = $orchestrator->run(array());
assertInstance(AcquisitionRunResult::class, $emptyRun, 'run() must return AcquisitionRunResult');
assertTrue($emptyRun->success() === false, 'Empty run must fail');
assertSameValue('invalid_request', $emptyRun->errorCode(), 'Empty run must return invalid_request');
assertTrue($emptyRun->runId() !== '', 'Rejected run must still assign opaque run_id');

// --- Pre-fetch source failure path without network ---

$invalidRun = $orchestrator->run(array(0));
assertTrue($invalidRun->success() === false, 'run([0]) must fail');
assertSameValue('', $invalidRun->errorCode(), 'Capability-enabled run must not return capability_disabled');
assertSameValue(1, $invalidRun->sourcesRequested(), 'run([0]) must request one source');
assertSameValue(0, $invalidRun->sourcesSucceeded(), 'run([0]) must succeed zero sources');
assertSameValue(1, $invalidRun->sourcesFailed(), 'run([0]) must fail one source');
assertTrue(isset($invalidRun->results()[0]), 'run([0]) must include per-source result');
assertSameValue(
    'invalid_id',
    $invalidRun->results()[0]['error_code'],
    'run([0]) per-source result must be invalid_id'
);
assertTrue($invalidRun->runId() !== '', 'Completed run must assign opaque run_id');
assertTrue(
    !isset($invalidRun->toArray()['evidence'])
    && !isset($invalidRun->results()[0]['body']),
    'Run result must not expose evidence body'
);

// --- Diagnostics production orchestrator block ---

$acquisitionDiagnostics = $container->get(AcquisitionDiagnostics::class);
$status = $acquisitionDiagnostics->status();

assertTrue(
    isset($status['production_orchestrator']) && is_array($status['production_orchestrator']),
    'AcquisitionDiagnostics must expose production_orchestrator block'
);
assertTrue(
    isset($status['production_orchestrator']['last_run'])
    && is_array($status['production_orchestrator']['last_run']),
    'AcquisitionDiagnostics must expose last production run'
);
assertSameValue(
    'completed',
    $status['production_orchestrator']['last_run']['status'],
    'Last production run status must be completed'
);
assertTrue(
    !isset($status['production_orchestrator']['last_run']['body'])
    && !isset($status['production_orchestrator']['last_run']['evidence']),
    'Last production run must not expose evidence body'
);
assertTrue(
    $status['production_orchestrator']['runs_recorded'] >= 1,
    'Production runs_recorded must increment'
);
assertSameValue(
    'idle',
    $status['production_runtime'],
    'Production runtime must return to idle after run'
);

// --- Platform diagnostics confirmation ---

$platformDiagnostics = $container->get(PlatformDiagnostics::class);
$diagnostics = $platformDiagnostics->collect();

assertSameValue(
    'editorial-workspace-phase2',
    $diagnostics['versions']['platform_phase'],
    'PlatformDiagnostics phase label must match CIP-005 label'
);
assertSameValue(
    'Ready',
    $diagnostics['confirmations']['production_orchestrator'],
    'PlatformDiagnostics production_orchestrator confirmation must be Ready'
);
assertSameValue(
    'Active',
    $diagnostics['confirmations']['acquisition'],
    'PlatformDiagnostics acquisition confirmation must remain Active'
);

$versionRegistry = $container->get(VersionRegistry::class);
assertSameValue(
    'editorial-workspace-phase2',
    $versionRegistry->get('platform_phase'),
    'VersionRegistry platform phase must match CIP-005 label'
);

// --- Source Check remains ungated ---

$invalidCheck = $sourceCheckService->check(0);
assertSameValue(false, $invalidCheck['success'], 'SourceCheckService::check(0) must fail');
assertSameValue('invalid_id', $invalidCheck['error_code'], 'SourceCheckService::check(0) must return invalid_id');

$directAcquire = $sourceAcquisitionService->acquire(array(
    'source_id' => 1,
    'source_key' => 'test-source',
    'url' => '',
    'allowed_domains' => array('example.com'),
    'source_type' => 'rss',
    'parser_profile' => '',
));
assertTrue($directAcquire->success() === false, 'Direct acquire must fail for missing feed URL');
assertSameValue(
    'missing_feed_url',
    $directAcquire->errorCode(),
    'Direct SourceAcquisitionService path must remain ungated'
);

if ($failures !== array()) {
    fwrite(STDERR, "CIP-005 production orchestrator smoke test failed.\n");

    foreach ($failures as $failure) {
        fwrite(STDERR, '- ' . $failure . "\n");
    }

    exit(1);
}

echo "CIP-005 production orchestrator smoke test passed.\n";
echo 'Assertions: ' . $passed . "\n";
