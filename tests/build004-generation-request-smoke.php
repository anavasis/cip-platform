<?php

/**
 * BUILD-004 Generation Request domain smoke / unit / architecture tests.
 *
 * No providers, HTTP, queues, workers, retries, persistence, UI,
 * prompt rendering, Generation Result, workflow, or publishing.
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

use StudyMentor\ContentEngine\Blueprint\ContentBlueprintBuilder;
use StudyMentor\ContentEngine\Core\ModuleLoader;
use StudyMentor\ContentEngine\Core\ModuleRegistry;
use StudyMentor\ContentEngine\Core\Plugin;
use StudyMentor\ContentEngine\Core\ServiceContainer;
use StudyMentor\ContentEngine\GenerationRequest\GenerationModelReference;
use StudyMentor\ContentEngine\GenerationRequest\GenerationParameters;
use StudyMentor\ContentEngine\GenerationRequest\GenerationRequest;
use StudyMentor\ContentEngine\GenerationRequest\GenerationRequestBuilder;
use StudyMentor\ContentEngine\GenerationRequest\GenerationRequestRepositoryInterface;
use StudyMentor\ContentEngine\GenerationRequest\GenerationRequestStatus;
use StudyMentor\ContentEngine\GenerationRequest\GenerationRequestValidator;
use StudyMentor\ContentEngine\Modules\AcquisitionModule;
use StudyMentor\ContentEngine\Modules\AnnouncementModule;
use StudyMentor\ContentEngine\Modules\BlueprintModule;
use StudyMentor\ContentEngine\Modules\CorePlatformModule;
use StudyMentor\ContentEngine\Modules\GenerationRequestModule;
use StudyMentor\ContentEngine\Modules\PromptContextModule;
use StudyMentor\ContentEngine\Modules\PromptPackageModule;
use StudyMentor\ContentEngine\Modules\SourceRegistryModule;
use StudyMentor\ContentEngine\PromptContext\PromptContextBuilder;
use StudyMentor\ContentEngine\PromptPackage\PromptPackage;
use StudyMentor\ContentEngine\PromptPackage\PromptPackageBuilder;
use StudyMentor\ContentEngine\PromptPackage\PromptPackageStatus;
use StudyMentor\ContentEngine\PromptPackage\PromptTemplateReference;

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

// --- Unit: status enum ---

assertTrue(GenerationRequestStatus::isValid(GenerationRequestStatus::DRAFT), 'draft valid');
assertTrue(GenerationRequestStatus::isValid(GenerationRequestStatus::READY), 'ready valid');
assertTrue(GenerationRequestStatus::isValid(GenerationRequestStatus::SUPERSEDED), 'superseded valid');
assertTrue(GenerationRequestStatus::isValid(GenerationRequestStatus::CANCELLED), 'cancelled valid');
assertTrue(!GenerationRequestStatus::isValid('running'), 'running is not a domain status');

// --- Build sealed package fixture via upstream domains ---

$blueprintBuilder = new ContentBlueprintBuilder();
$contextBuilder = new PromptContextBuilder();
$packageBuilder = new PromptPackageBuilder();
$requestBuilder = new GenerationRequestBuilder();
$validator = new GenerationRequestValidator();

$announcementSnapshot = array(
    'announcement_id' => 42,
    'source_id' => 3,
    'raw_title' => 'Scholarship deadline extended',
    'canonical_url' => 'https://example.edu/news/scholarship',
    'source_content_hash' => str_repeat('a', 64),
    'announcement_revision_no' => 1,
    'language' => 'el',
    'raw_payload' => array(
        'summary' => 'The scholarship deadline has been extended to September.',
        'category' => 'scholarships',
    ),
);

$blueprint = $blueprintBuilder->buildFromAnnouncement($announcementSnapshot);
$context = $contextBuilder->buildFromAnnouncementAndBlueprint($announcementSnapshot, $blueprint);
$package = $packageBuilder->buildFromContextAndBlueprint(
    $context,
    $packageBuilder->blueprintReferenceFromContext($context),
    new PromptTemplateReference(array(
        'template_id' => 'news_brief.v1',
        'template_version' => '1.0.0',
    ))
);
assertSameValue(PromptPackageStatus::SEALED, $package->status(), 'fixture package must be sealed');

$model = new GenerationModelReference(array(
    'model_id' => 'text.default',
    'model_version' => '1',
));
$parameters = new GenerationParameters(array(
    'temperature' => 0.2,
    'max_output_tokens' => 1200,
    'top_p' => 0.9,
    'response_format' => GenerationParameters::FORMAT_TEXT,
));

$request = $requestBuilder->buildFromPackage($package, $model, $parameters);

assertTrue($request instanceof GenerationRequest, 'builder must return GenerationRequest');
assertSameValue(42, $request->announcementId(), 'announcement id from package');
assertSameValue($package->packageId(), $request->packageId(), 'package id bound');
assertSameValue($package->packageHash(), $request->packageHash(), 'package hash bound');
assertSameValue(GenerationRequestStatus::READY, $request->status(), 'builder marks ready');
assertTrue($request->requestId() !== '', 'request id generated');
assertTrue(strlen($request->requestHash()) === 64, 'request hash sha256 hex');
assertSameValue('text.default', $request->modelReference()->modelId(), 'model id bound');
assertSameValue(0.2, $request->parameters()->temperature(), 'temperature bound');

$validation = $validator->validate($request);
assertTrue($validation['valid'] === true, 'built request must be valid');
assertTrue($validation['ready'] === true, 'built request must be ready');
assertTrue($validator->isReady($request) === true, 'isReady true');
assertTrue(
    $validator->requestHashMatchesBinding($request) === true,
    'request_hash must match binding payload'
);

// Deterministic hash
$requestB = $requestBuilder->buildFromPackage(
    $package,
    $model,
    $parameters,
    array('request_id' => 'gr_fixed')
);
assertSameValue(
    $request->requestHash(),
    $requestB->requestHash(),
    'identical binding must yield identical request_hash'
);

// Parameter change => new hash
$requestC = $requestBuilder->buildFromPackage(
    $package,
    $model,
    new GenerationParameters(array(
        'temperature' => 0.7,
        'max_output_tokens' => 1200,
        'top_p' => 0.9,
        'response_format' => GenerationParameters::FORMAT_TEXT,
    ))
);
assertTrue(
    $request->requestHash() !== $requestC->requestHash(),
    'parameter change must change request_hash'
);

// Model change => new hash
$requestD = $requestBuilder->buildFromPackage(
    $package,
    new GenerationModelReference(array(
        'model_id' => 'text.default',
        'model_version' => '2',
    )),
    $parameters
);
assertTrue(
    $request->requestHash() !== $requestD->requestHash(),
    'model version change must change request_hash'
);

// Lineage override included in hash
$requestE = $requestBuilder->buildFromPackage(
    $package,
    $model,
    $parameters,
    array('lineage_id' => 'lin_42_1')
);
assertSameValue('lin_42_1', $requestE->lineageId(), 'lineage override preserved');
assertTrue(
    $request->requestHash() !== $requestE->requestHash(),
    'lineage change must change request_hash'
);

// Reject unsealed package
$unsealed = new PromptPackage(array(
    'package_id' => 'pp_x',
    'announcement_id' => 1,
    'context_id' => 'pc_x',
    'context_hash' => str_repeat('b', 64),
    'package_hash' => str_repeat('c', 64),
    'status' => PromptPackageStatus::DRAFT,
    'blueprint_reference' => array(
        'blueprint_id' => 'bp_x',
        'blueprint_revision' => 1,
        'announcement_id' => 1,
    ),
    'template_reference' => array(
        'template_id' => 't',
        'template_version' => '1',
    ),
));
$unsealedThrown = false;
try {
    $requestBuilder->buildFromPackage($unsealed, $model, $parameters);
} catch (\InvalidArgumentException $e) {
    $unsealedThrown = ($e->getMessage() === 'package_not_sealed');
}
assertTrue($unsealedThrown, 'unsealed package must throw');

// Reject empty model id
$modelThrown = false;
try {
    $requestBuilder->buildFromPackage(
        $package,
        new GenerationModelReference(array('model_id' => '', 'model_version' => '1')),
        $parameters
    );
} catch (\InvalidArgumentException $e) {
    $modelThrown = ($e->getMessage() === 'model_id_required');
}
assertTrue($modelThrown, 'empty model id must throw');

$invalid = new GenerationRequest(array(
    'request_id' => '',
    'announcement_id' => 0,
    'package_id' => '',
    'package_hash' => 'short',
    'status' => 'nope',
    'request_hash' => 'x',
    'model_reference' => array('model_id' => '', 'model_version' => ''),
    'parameters' => array(
        'temperature' => 9.0,
        'max_output_tokens' => 0,
        'top_p' => 2.0,
        'response_format' => 'xml',
    ),
));
$invalidResult = $validator->validate($invalid);
assertTrue($invalidResult['valid'] === false, 'invalid request must fail');
assertTrue(
    in_array('request_id_required', $invalidResult['errors'], true),
    'must report request_id_required'
);
assertTrue(
    in_array('response_format_invalid', $invalidResult['errors'], true),
    'must report response_format_invalid'
);
assertTrue($validator->isReady($invalid) === false, 'invalid cannot be ready');

$serialized = $request->toArray();
assertTrue(
    isset($serialized['model_reference'], $serialized['parameters'], $serialized['request_hash']),
    'toArray must expose core groups'
);
$rehydrated = new GenerationRequest($serialized);
assertSameValue($request->requestId(), $rehydrated->requestId(), 'toArray round-trip id');
assertSameValue($request->requestHash(), $rehydrated->requestHash(), 'toArray round-trip hash');

// --- Architecture: interface-only repo; no providers/HTTP/queue/result ---

assertTrue(
    interface_exists(GenerationRequestRepositoryInterface::class),
    'GenerationRequestRepositoryInterface must exist'
);

$requestDir = $pluginDirectory . '/src/GenerationRequest';
$requestFiles = glob($requestDir . '/*.php');
assertTrue(is_array($requestFiles) && count($requestFiles) > 0, 'GenerationRequest directory required');

foreach ($requestFiles as $file) {
    $contents = file_get_contents($file);
    assertTrue($contents !== false, 'must read ' . basename($file));
    $dbToken = '$' . 'wpdb';
    $ddlToken = 'CREATE' . ' TABLE';
    $restToken = 'register_' . 'rest_route';
    $menuToken = 'add_' . 'menu_page';
    $httpToken = 'wp_' . 'remote_';
    $curlToken = 'cu' . 'rl_';
    $vendorA = 'open' . 'ai';
    $vendorB = 'anthrop' . 'ic';
    $vendorC = 'gem' . 'ini';
    $queueToken = 'wp_' . 'schedule_';
    $resultToken = 'Generation' . 'Result';
    $systemPromptToken = 'You are a';
    assertTrue(strpos($contents, $dbToken) === false, basename($file) . ' must not use db handle');
    assertTrue(stripos($contents, $ddlToken) === false, basename($file) . ' must not DDL');
    assertTrue(stripos($contents, $restToken) === false, basename($file) . ' must not REST');
    assertTrue(stripos($contents, $menuToken) === false, basename($file) . ' must not admin menu');
    assertTrue(stripos($contents, $httpToken) === false, basename($file) . ' must not HTTP');
    assertTrue(stripos($contents, $curlToken) === false, basename($file) . ' must not curl');
    assertTrue(stripos($contents, $vendorA) === false, basename($file) . ' must not vendor A');
    assertTrue(stripos($contents, $vendorB) === false, basename($file) . ' must not vendor B');
    assertTrue(stripos($contents, $vendorC) === false, basename($file) . ' must not vendor C');
    assertTrue(stripos($contents, $queueToken) === false, basename($file) . ' must not schedule/queue');
    assertTrue(strpos($contents, $resultToken) === false, basename($file) . ' must not implement Result');
    assertTrue(strpos($contents, $systemPromptToken) === false, basename($file) . ' must not prompt prose');
}

$repoInterfaceFile = $requestDir . '/GenerationRequestRepositoryInterface.php';
$repoInterfaceContents = file_get_contents($repoInterfaceFile);
assertTrue(
    strpos($repoInterfaceContents, 'interface GenerationRequestRepositoryInterface') !== false,
    'repository file must declare an interface'
);
assertTrue(
    preg_match('/\bfinal\s+class\b|\bclass\s+\w+/', $repoInterfaceContents) !== 1,
    'BUILD-004 must not ship a concrete generation request repository class'
);

// --- Module registration ---

$databaseGlobalKey = 'wp' . 'db';
$GLOBALS[$databaseGlobalKey] = (object) array(
    'prefix' => 'wp_',
);

$plugin = new Plugin();
assertTrue($plugin instanceof Plugin, 'Plugin must construct with GenerationRequestModule');

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
$loader = new ModuleLoader($moduleRegistry, $container);
$loader->load();

assertTrue($moduleRegistry->has('generation_request'), 'generation_request module registered');
assertTrue(
    $container->get(GenerationRequestBuilder::class) instanceof GenerationRequestBuilder,
    'builder resolves from container'
);
assertTrue(
    $container->get(GenerationRequestValidator::class) instanceof GenerationRequestValidator,
    'validator resolves from container'
);

$pluginContents = file_get_contents($pluginDirectory . '/src/Core/Plugin.php');
assertTrue(
    strpos($pluginContents, 'GenerationRequestModule') !== false,
    'Plugin must register GenerationRequestModule'
);

if ($failures !== array()) {
    fwrite(STDERR, "BUILD-004 Generation Request smoke test failed.\n");

    foreach ($failures as $failure) {
        fwrite(STDERR, '- ' . $failure . "\n");
    }

    exit(1);
}

echo "BUILD-004 Generation Request smoke test passed.\n";
echo 'Assertions: ' . $passed . "\n";
