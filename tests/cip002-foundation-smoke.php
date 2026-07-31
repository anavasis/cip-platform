<?php

/**
 * CIP-002 platform foundation smoke test.
 *
 * Exercises the composition root without WordPress boot, Plugin::boot(),
 * handler registration, or network I/O.
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

use StudyMentor\ContentEngine\Acquisition\SourceAcquisitionService;
use StudyMentor\ContentEngine\Admin\BulkConnectivityAuditService;
use StudyMentor\ContentEngine\Admin\Menu;
use StudyMentor\ContentEngine\Admin\Pages\SourcesPage;
use StudyMentor\ContentEngine\Admin\SourceActionHandler;
use StudyMentor\ContentEngine\Admin\SourceCatalogActionHandler;
use StudyMentor\ContentEngine\Admin\SourceCheckService;
use StudyMentor\ContentEngine\Admin\SourceItemActionHandler;
use StudyMentor\ContentEngine\Audit\AuditLoggerInterface;
use StudyMentor\ContentEngine\Audit\NullAuditLogger;
use StudyMentor\ContentEngine\Contracts\ModuleInterface;
use StudyMentor\ContentEngine\Core\FeatureFlags;
use StudyMentor\ContentEngine\Core\ModuleLoader;
use StudyMentor\ContentEngine\Core\ModuleRegistry;
use StudyMentor\ContentEngine\Core\ServiceContainer;
use StudyMentor\ContentEngine\Data\SourceRegistryService;
use StudyMentor\ContentEngine\Data\SourceRepository;
use StudyMentor\ContentEngine\Http\SafeFeedFetcher;
use StudyMentor\ContentEngine\Modules\AcquisitionModule;
use StudyMentor\ContentEngine\Modules\AnnouncementModule;
use StudyMentor\ContentEngine\Modules\CorePlatformModule;
use StudyMentor\ContentEngine\Modules\SourceRegistryModule;
use StudyMentor\ContentEngine\Platform\PlatformDiagnostics;
use StudyMentor\ContentEngine\Registry\CapabilityRegistry;
use StudyMentor\ContentEngine\Support\LoggerInterface;
use StudyMentor\ContentEngine\Support\NullLogger;

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
 * @param callable $callback
 * @param string $expectedMessageFragment
 * @param string $message
 * @return void
 */
function assertThrows($callback, $expectedMessageFragment, $message)
{
    try {
        call_user_func($callback);
        assertTrue(false, $message . ' (no exception thrown)');
    } catch (\Throwable $exception) {
        assertTrue(
            strpos($exception->getMessage(), $expectedMessageFragment) !== false,
            $message . ' (unexpected message: ' . $exception->getMessage() . ')'
        );
    }
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

// --- ServiceContainer semantics ---

$container = new ServiceContainer();
$moduleRegistry = new ModuleRegistry();
$container->set(ModuleRegistry::class, $moduleRegistry);

assertThrows(
    static function () use ($container) {
        $container->get('missing.service');
    },
    'Service not registered: missing.service',
    'Missing service get() must throw'
);

$probe = new \stdClass();
$container->set('probe', $probe);

assertThrows(
    static function () use ($container, $probe) {
        $container->set('probe', $probe);
    },
    'Duplicate service registration: probe',
    'Duplicate service registration must throw'
);

$factoryCalls = 0;
$container->factory(
    'singleton.factory',
    static function () use (&$factoryCalls) {
        ++$factoryCalls;

        return new \stdClass();
    }
);

$first = $container->get('singleton.factory');
$second = $container->get('singleton.factory');
assertSameValue(1, $factoryCalls, 'Factory must execute only once');
assertSameValue(spl_object_id($first), spl_object_id($second), 'Factory must cache singleton instance');

$container->factory(
    'throwing.factory',
    static function () {
        throw new \RuntimeException('factory boom');
    }
);

assertThrows(
    static function () use ($container) {
        $container->get('throwing.factory');
    },
    'factory boom',
    'Factory exception must propagate'
);

assertThrows(
    static function () use ($container) {
        $container->get('throwing.factory');
    },
    'factory boom',
    'Factory exception must not cache a value'
);

$container->factory(
    'nonobject.factory',
    static function () {
        return 'not-an-object';
    }
);

assertThrows(
    static function () use ($container) {
        $container->get('nonobject.factory');
    },
    'Service factory did not return an object: nonobject.factory',
    'Non-object factory result must throw'
);

// --- ModuleRegistry semantics ---

$moduleRegistry->register(new CorePlatformModule());
$moduleRegistry->register(new SourceRegistryModule());
$moduleRegistry->register(new AcquisitionModule());
$moduleRegistry->register(new AnnouncementModule());

assertTrue($moduleRegistry->has('core_platform'), 'core_platform module must be registered');
assertTrue($moduleRegistry->has('source_registry'), 'source_registry module must be registered');
assertTrue($moduleRegistry->has('acquisition'), 'acquisition module must be registered');
assertTrue($moduleRegistry->has('announcement'), 'announcement module must be registered');

assertThrows(
    static function () use ($moduleRegistry) {
        $moduleRegistry->register(new CorePlatformModule());
    },
    'Duplicate module registration: core_platform',
    'Duplicate module registration must throw'
);

// --- ModuleLoader happy path ---

$moduleLoader = new ModuleLoader($moduleRegistry, $container);
$moduleLoader->load();
assertSameValue(ModuleLoader::STATE_LOADED, $moduleLoader->state(), 'ModuleLoader must reach loaded state');

// --- Capability surface ---

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

assertTrue(!$capabilityRegistry->has('unknown_capability'), 'Unknown capability has() must be false');
assertTrue(!$capabilityRegistry->isEnabled('unknown_capability'), 'Unknown capability isEnabled() must be false');

// --- Logger bindings ---

assertInstance(NullLogger::class, $container->get(LoggerInterface::class), 'LoggerInterface must resolve to NullLogger');
assertInstance(NullAuditLogger::class, $container->get(AuditLoggerInterface::class), 'AuditLoggerInterface must resolve to NullAuditLogger');

// --- Handler and menu resolution ---

$menu = $container->get(Menu::class);
$sourceActionHandler = $container->get(SourceActionHandler::class);
$sourceCatalogActionHandler = $container->get(SourceCatalogActionHandler::class);
$sourceItemActionHandler = $container->get(SourceItemActionHandler::class);

assertInstance(Menu::class, $menu, 'Menu must resolve from container');
assertInstance(SourceActionHandler::class, $sourceActionHandler, 'SourceActionHandler must resolve from container');
assertInstance(SourceCatalogActionHandler::class, $sourceCatalogActionHandler, 'SourceCatalogActionHandler must resolve from container');
assertInstance(SourceItemActionHandler::class, $sourceItemActionHandler, 'SourceItemActionHandler must resolve from container');

// --- PlatformDiagnostics ---

$platformDiagnostics = $container->get(PlatformDiagnostics::class);
$diagnostics = $platformDiagnostics->collect();

assertTrue(isset($diagnostics['versions']) && is_array($diagnostics['versions']), 'PlatformDiagnostics must return versions');
assertSameValue('0.9.1', $diagnostics['versions']['plugin'], 'PlatformDiagnostics plugin version must match SMCE_VERSION');
assertSameValue('1.0.0', $diagnostics['versions']['database'], 'PlatformDiagnostics database version must match SMCE_DB_VERSION');
assertSameValue('editorial-workspace-phase2', $diagnostics['versions']['platform_phase'], 'PlatformDiagnostics phase label must match');
assertTrue(isset($diagnostics['module_ids']) && is_array($diagnostics['module_ids']), 'PlatformDiagnostics must return module ids');
assertTrue(in_array('core_platform', $diagnostics['module_ids'], true), 'PlatformDiagnostics must include core_platform');
assertTrue(in_array('source_registry', $diagnostics['module_ids'], true), 'PlatformDiagnostics must include source_registry');
assertTrue(in_array('acquisition', $diagnostics['module_ids'], true), 'PlatformDiagnostics must include acquisition');
assertTrue(in_array('announcement', $diagnostics['module_ids'], true), 'PlatformDiagnostics must include announcement');
assertTrue(isset($diagnostics['capabilities']) && is_array($diagnostics['capabilities']), 'PlatformDiagnostics must return capabilities');

// --- Shared dependency identities via reflection ---

$sourceRepository = $container->get(SourceRepository::class);
$safeFeedFetcher = $container->get(SafeFeedFetcher::class);
$featureFlags = $container->get(FeatureFlags::class);

$sourceCheckService = $container->get(SourceCheckService::class);
$sourceAcquisitionService = $container->get(SourceAcquisitionService::class);
$bulkConnectivityAuditService = $container->get(BulkConnectivityAuditService::class);
$sourcesPage = $container->get(SourcesPage::class);
$sourceRegistryService = $container->get(SourceRegistryService::class);

assertSameValue(
    spl_object_id($sourceAcquisitionService),
    spl_object_id(readPrivateProperty($sourceCheckService, 'sourceAcquisitionService')),
    'SourceCheckService must share container SourceAcquisitionService'
);
assertSameValue(
    spl_object_id($sourceRepository),
    spl_object_id(readPrivateProperty($bulkConnectivityAuditService, 'repository')),
    'BulkConnectivityAuditService must share container SourceRepository'
);
assertSameValue(
    spl_object_id($sourceRepository),
    spl_object_id(readPrivateProperty($sourcesPage, 'repository')),
    'SourcesPage must share container SourceRepository'
);
assertSameValue(
    spl_object_id($sourceRepository),
    spl_object_id(readPrivateProperty($sourceRegistryService, 'repository')),
    'SourceRegistryService must share container SourceRepository'
);
assertSameValue(
    spl_object_id($safeFeedFetcher),
    spl_object_id(readPrivateProperty($bulkConnectivityAuditService, 'fetcher')),
    'BulkConnectivityAuditService must share container SafeFeedFetcher'
);
assertSameValue(
    spl_object_id($featureFlags),
    spl_object_id(readPrivateProperty($sourcesPage, 'featureFlags')),
    'SourcesPage must share container FeatureFlags'
);
assertSameValue(
    spl_object_id($featureFlags),
    spl_object_id(readPrivateProperty($sourceActionHandler, 'featureFlags')),
    'SourceActionHandler must share container FeatureFlags'
);
assertSameValue(
    spl_object_id($featureFlags),
    spl_object_id(readPrivateProperty($sourceCatalogActionHandler, 'featureFlags')),
    'SourceCatalogActionHandler must share container FeatureFlags'
);
assertSameValue(
    spl_object_id($featureFlags),
    spl_object_id(readPrivateProperty($sourceItemActionHandler, 'featureFlags')),
    'SourceItemActionHandler must share container FeatureFlags'
);

// --- ModuleLoader terminal states ---

$loadedRegistry = new ModuleRegistry();
$loadedRegistry->register(new CorePlatformModule());
$loadedContainer = new ServiceContainer();
$loadedContainer->set(ModuleRegistry::class, $loadedRegistry);
$loadedLoader = new ModuleLoader($loadedRegistry, $loadedContainer);
$loadedLoader->load();

assertThrows(
    static function () use ($loadedLoader) {
        $loadedLoader->load();
    },
    'ModuleLoader already loaded',
    'Successful ModuleLoader cannot load twice'
);

$failedRegistry = new ModuleRegistry();
$failedRegistry->register(new class implements ModuleInterface {
    public function id()
    {
        return 'failing_module';
    }

    public function register(ServiceContainer $container)
    {
        throw new \RuntimeException('module register failure');
    }

    public function boot(ServiceContainer $container)
    {
    }
});
$failedContainer = new ServiceContainer();
$failedContainer->set(ModuleRegistry::class, $failedRegistry);
$failedLoader = new ModuleLoader($failedRegistry, $failedContainer);

assertThrows(
    static function () use ($failedLoader) {
        $failedLoader->load();
    },
    'module register failure',
    'ModuleLoader must rethrow module failures'
);
assertSameValue(ModuleLoader::STATE_FAILED, $failedLoader->state(), 'ModuleLoader must enter failed state');

assertThrows(
    static function () use ($failedLoader) {
        $failedLoader->load();
    },
    'ModuleLoader load previously failed',
    'Failed ModuleLoader cannot retry'
);

if ($failures !== array()) {
    fwrite(STDERR, "CIP-002 foundation smoke test failed.\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, '- ' . $failure . "\n");
    }
    exit(1);
}

echo "CIP-002 foundation smoke test passed.\n";
echo 'Assertions: ' . $passed . "\n";
exit(0);
