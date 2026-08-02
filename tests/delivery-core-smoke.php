<?php

/**
 * Delivery Core smoke / unit / architecture tests.
 *
 * No HTTP, REST, WordPress post APIs, Hub, social, newsletter, or scheduler.
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

if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $acceptedArgs = 1)
    {
        return true;
    }
}

if (!function_exists('current_time')) {
    function current_time($type, $gmt = 0)
    {
        return gmdate('Y-m-d H:i:s');
    }
}

require_once $pluginDirectory . '/src/Core/Autoloader.php';
StudyMentor\ContentEngine\Core\Autoloader::register($pluginDirectory . '/src');

use StudyMentor\ContentEngine\Core\ModuleLoader;
use StudyMentor\ContentEngine\Core\ModuleRegistry;
use StudyMentor\ContentEngine\Core\Plugin;
use StudyMentor\ContentEngine\Core\ServiceContainer;
use StudyMentor\ContentEngine\Delivery\DeliveryBinding;
use StudyMentor\ContentEngine\Delivery\DeliveryConnectorInterface;
use StudyMentor\ContentEngine\Delivery\DeliveryConnectorRegistry;
use StudyMentor\ContentEngine\Delivery\DeliveryDiagnostics;
use StudyMentor\ContentEngine\Delivery\DeliveryEngine;
use StudyMentor\ContentEngine\Delivery\DeliveryPayloadBuilder;
use StudyMentor\ContentEngine\Delivery\DeliveryRegistry;
use StudyMentor\ContentEngine\Delivery\DeliveryState;
use StudyMentor\ContentEngine\Modules\AcquisitionModule;
use StudyMentor\ContentEngine\Modules\AnnouncementModule;
use StudyMentor\ContentEngine\Modules\BlueprintModule;
use StudyMentor\ContentEngine\Modules\CorePlatformModule;
use StudyMentor\ContentEngine\Modules\DeliveryModule;
use StudyMentor\ContentEngine\Modules\EditorialSliceModule;
use StudyMentor\ContentEngine\Modules\GenerationRequestModule;
use StudyMentor\ContentEngine\Modules\GenerationResultModule;
use StudyMentor\ContentEngine\Modules\PromptContextModule;
use StudyMentor\ContentEngine\Modules\PromptPackageModule;
use StudyMentor\ContentEngine\Modules\SourceRegistryModule;

$failures = array();
$passed = 0;

function assertTrue($condition, $message)
{
    global $failures, $passed;

    if ($condition) {
        ++$passed;
        return;
    }

    $failures[] = $message;
}

function assertSameValue($expected, $actual, $message)
{
    assertTrue(
        $expected === $actual,
        $message . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')'
    );
}

/**
 * Test-only connector — not part of Delivery Core product surface.
 */
final class FakeDeliveryConnector implements DeliveryConnectorInterface
{
    private $id;
    private $calls = 0;
    private $externalPrefix;

    public function __construct($id, $externalPrefix)
    {
        $this->id = (string) $id;
        $this->externalPrefix = (string) $externalPrefix;
    }

    public function id()
    {
        return $this->id;
    }

    public function deliver(array $payload, $existingBinding)
    {
        $this->calls++;

        if ($existingBinding instanceof DeliveryBinding && $existingBinding->externalId() !== '') {
            return array(
                'ok' => true,
                'external_id' => $existingBinding->externalId(),
                'delivery_state' => DeliveryState::DELIVERED,
            );
        }

        return array(
            'ok' => true,
            'external_id' => $this->externalPrefix . (string) $payload['announcement_id'],
            'delivery_state' => DeliveryState::DELIVERED,
        );
    }

    public function calls()
    {
        return $this->calls;
    }
}

// --- Unit: state + binding ---

assertTrue(DeliveryState::isValid(DeliveryState::PENDING), 'pending valid');
assertTrue(DeliveryState::isValid(DeliveryState::DELIVERED), 'delivered valid');
assertTrue(DeliveryState::isValid(DeliveryState::FAILED), 'failed valid');
assertTrue(DeliveryState::isValid(DeliveryState::RETRY), 'retry valid');
assertTrue(DeliveryState::isValid(DeliveryState::SKIPPED), 'skipped valid');
assertTrue(DeliveryState::isValid(DeliveryState::ORPHANED), 'orphaned valid');
assertTrue(!DeliveryState::isValid('published'), 'published is not a delivery state');

$binding = new DeliveryBinding(array(
    'project_id' => 'proj-1',
    'announcement_id' => 10,
    'target' => 'fake_target',
    'external_id' => '',
    'delivery_state' => DeliveryState::PENDING,
    'revision' => 1,
    'idempotency_key' => str_repeat('a', 64),
    'last_sync' => '',
    'attempt_count' => 0,
    'last_error' => '',
));
assertSameValue('proj-1', $binding->projectId(), 'binding project_id');
assertSameValue(10, $binding->announcementId(), 'binding announcement_id');
$updated = $binding->with(array('external_id' => 'ext-1', 'delivery_state' => DeliveryState::DELIVERED));
assertSameValue('ext-1', $updated->externalId(), 'binding with() external_id');
assertSameValue(DeliveryState::PENDING, $binding->deliveryState(), 'original binding immutable');

// --- Unit: payload builder + deterministic idempotency ---

$builder = new DeliveryPayloadBuilder();
$keyA = $builder->buildIdempotencyKey('proj-1', 10, 'fake_target', 'identity-1');
$keyB = $builder->buildIdempotencyKey('proj-1', 10, 'fake_target', 'identity-1');
$keyC = $builder->buildIdempotencyKey('proj-1', 10, 'fake_target', 'identity-2');
assertSameValue(64, strlen($keyA), 'idempotency key is sha256 hex');
assertSameValue($keyA, $keyB, 'idempotency key is deterministic');
assertTrue($keyA !== $keyC, 'idempotency key changes with identity');

$payload = $builder->build(array(
    'project_id' => 'proj-1',
    'announcement_id' => 10,
    'target' => 'fake_target',
    'revision' => 2,
    'identity_hash' => 'identity-1',
    'content_hash' => 'content-rev-2',
    'title' => 'Title',
    'body' => 'Body',
    'preview_id' => 'prev-1',
));
assertSameValue(DeliveryPayloadBuilder::SCHEMA_VERSION, $payload['schema_version'], 'payload schema');
assertSameValue($keyA, $payload['idempotency_key'], 'payload uses stable identity key');
assertSameValue(2, $payload['revision'], 'payload revision');
assertTrue(isset($payload['artifact']['preview_id']), 'payload artifact block');

// --- Registry ---

$registry = new DeliveryRegistry();
assertTrue(
    $registry->save(new DeliveryBinding(array(
        'project_id' => 'proj-1',
        'announcement_id' => 10,
        'target' => 'fake_target',
        'external_id' => 'ext-9',
        'delivery_state' => DeliveryState::DELIVERED,
        'revision' => 1,
        'idempotency_key' => $keyA,
        'last_sync' => '2026-08-02 00:00:00',
        'attempt_count' => 1,
        'last_error' => '',
    ))),
    'registry save'
);
$found = $registry->find('proj-1', 10, 'fake_target');
assertTrue($found instanceof DeliveryBinding, 'registry find by composite');
assertSameValue('ext-9', $found->externalId(), 'registry external id');
$byKey = $registry->findByIdempotencyKey($keyA);
assertTrue($byKey instanceof DeliveryBinding, 'registry find by idempotency key');
assertSameValue(1, $registry->count(), 'registry count');

// --- Engine: no connector → failed binding ---

$connectors = new DeliveryConnectorRegistry();
$emptyRegistry = new DeliveryRegistry();
$emptyDiagnostics = new DeliveryDiagnostics($connectors, $emptyRegistry);
$engineNoConnector = new DeliveryEngine(
    $connectors,
    $emptyRegistry,
    $builder,
    $emptyDiagnostics
);
$failed = $engineNoConnector->deliver(array(
    'project_id' => 'proj-1',
    'announcement_id' => 11,
    'target' => 'missing_target',
    'revision' => 1,
    'identity_hash' => 'id-11',
    'title' => 'T',
    'body' => 'B',
));
assertSameValue(false, $failed['ok'], 'missing connector fails');
assertSameValue('connector_not_registered', $failed['error_code'], 'missing connector code');
assertTrue($failed['binding'] instanceof DeliveryBinding, 'failed binding stored');
assertSameValue(DeliveryState::FAILED, $failed['binding']->deliveryState(), 'failed state');

// --- Engine: fake connector create + idempotent redeliver ---

$fake = new FakeDeliveryConnector('fake_target', 'fake-');
$connectors->register($fake);
$liveRegistry = new DeliveryRegistry();
$liveDiagnostics = new DeliveryDiagnostics($connectors, $liveRegistry);
$engine = new DeliveryEngine($connectors, $liveRegistry, $builder, $liveDiagnostics);

$request = array(
    'project_id' => 'proj-1',
    'announcement_id' => 42,
    'target' => 'fake_target',
    'revision' => 1,
    'identity_hash' => 'identity-42',
    'content_hash' => 'content-42-r1',
    'title' => 'Announcement 42',
    'body' => 'Preview body',
    'preview_id' => 'preview-42',
);

$first = $engine->deliver($request);
assertSameValue(true, $first['ok'], 'first deliver ok');
assertSameValue('delivered', $first['status'], 'first deliver status');
assertSameValue('fake-42', $first['external_id'], 'first external id');
assertSameValue(1, $fake->calls(), 'connector called once');

$second = $engine->deliver($request);
assertSameValue(true, $second['ok'], 'second deliver ok');
assertSameValue('skipped', $second['status'], 'same revision skips');
assertSameValue('fake-42', $second['external_id'], 'skip keeps external id');
assertSameValue(1, $fake->calls(), 'connector not called again on skip');

$requestUpdated = $request;
$requestUpdated['revision'] = 2;
$requestUpdated['content_hash'] = 'content-42-r2';
$third = $engine->deliver($requestUpdated);
assertSameValue(true, $third['ok'], 'updated revision ok');
assertSameValue('delivered', $third['status'], 'updated status');
assertSameValue('fake-42', $third['external_id'], 'update reuses external id');
assertSameValue(2, $fake->calls(), 'connector called for update');
assertSameValue(2, $third['binding']->revision(), 'binding revision updated');

$diag = $liveDiagnostics->collect();
assertSameValue('delivery', $diag['engine'], 'diagnostics engine id');
assertTrue($diag['success_count'] >= 2, 'diagnostics success count');
assertTrue($diag['skipped_count'] >= 1, 'diagnostics skipped count');
assertTrue(in_array('fake_target', $diag['connector_ids'], true), 'diagnostics connector ids');

// --- Architecture isolation ---

$deliveryFiles = array(
    'src/Delivery/DeliveryState.php',
    'src/Delivery/DeliveryBinding.php',
    'src/Delivery/DeliveryConnectorInterface.php',
    'src/Delivery/DeliveryConnectorRegistry.php',
    'src/Delivery/DeliveryRegistry.php',
    'src/Delivery/DeliveryPayloadBuilder.php',
    'src/Delivery/DeliveryDiagnostics.php',
    'src/Delivery/DeliveryEngine.php',
    'src/Modules/DeliveryModule.php',
);

foreach ($deliveryFiles as $relative) {
    $contents = file_get_contents($pluginDirectory . '/' . $relative);
    assertTrue($contents !== false, $relative . ' readable');
    $insertToken = 'wp_' . 'insert_post';
    $updateToken = 'wp_' . 'update_post';
    $remoteToken = 'wp_' . 'remote_';
    $restToken = 'register_' . 'rest_route';
    $curlToken = 'curl' . '_';
    $hubToken = 'Hub' . 'Connector';
    $wpConnectorToken = 'Word' . 'PressConnector';
    assertTrue(stripos($contents, $insertToken) === false, $relative . ' no insert post API');
    assertTrue(stripos($contents, $updateToken) === false, $relative . ' no update post API');
    assertTrue(stripos($contents, $remoteToken) === false, $relative . ' no remote HTTP helper');
    assertTrue(stripos($contents, $restToken) === false, $relative . ' no REST');
    assertTrue(stripos($contents, $curlToken) === false, $relative . ' no curl');
    assertTrue(strpos($contents, $hubToken) === false, $relative . ' no Hub connector');
    assertTrue(strpos($contents, $wpConnectorToken) === false, $relative . ' no WP connector');
}

// --- Module wiring ---

$databaseGlobalKey = 'wp' . 'db';
$GLOBALS[$databaseGlobalKey] = (object) array(
    'prefix' => 'wp_',
);

$plugin = new Plugin();
assertTrue($plugin instanceof Plugin, 'Plugin constructs with DeliveryModule');

$container = new ServiceContainer();
$moduleRegistry = new ModuleRegistry();
$container->set(ModuleRegistry::class, $moduleRegistry);
$moduleRegistry->register(new CorePlatformModule());
$moduleRegistry->register(new SourceRegistryModule());
$moduleRegistry->register(new AcquisitionModule());
$moduleRegistry->register(new AnnouncementModule());
$moduleRegistry->register(new BlueprintModule());
$moduleRegistry->register(new PromptContextModule());
$moduleRegistry->register(new PromptPackageModule());
$moduleRegistry->register(new GenerationRequestModule());
$moduleRegistry->register(new GenerationResultModule());
$moduleRegistry->register(new EditorialSliceModule());
$moduleRegistry->register(new DeliveryModule());
$loader = new ModuleLoader($moduleRegistry, $container);
$loader->load();

assertTrue($moduleRegistry->has('delivery'), 'delivery module registered');
assertTrue(
    $container->get(DeliveryEngine::class) instanceof DeliveryEngine,
    'DeliveryEngine resolves'
);
assertTrue(
    $container->get(DeliveryRegistry::class) instanceof DeliveryRegistry,
    'DeliveryRegistry resolves'
);
assertTrue(
    $container->get(DeliveryConnectorRegistry::class) instanceof DeliveryConnectorRegistry,
    'DeliveryConnectorRegistry resolves'
);
assertSameValue(
    0,
    count($container->get(DeliveryConnectorRegistry::class)->all()),
    'core registers zero concrete connectors'
);

$pluginContents = file_get_contents($pluginDirectory . '/src/Core/Plugin.php');
assertTrue(strpos($pluginContents, 'DeliveryModule') !== false, 'Plugin registers DeliveryModule');

if ($failures !== array()) {
    fwrite(STDERR, "Delivery Core smoke test failed.\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, '- ' . $failure . "\n");
    }
    exit(1);
}

fwrite(STDOUT, "Delivery Core smoke test passed.\n");
fwrite(STDOUT, 'Assertions: ' . $passed . "\n");
exit(0);
