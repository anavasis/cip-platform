<?php

/**
 * BUILD-005 Generation Result domain smoke / unit / architecture tests.
 *
 * No providers, HTTP, queues, workers, retries, persistence, UI,
 * prompt rendering, workflow, compliance, or publishing.
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
use StudyMentor\ContentEngine\GenerationRequest\GenerationRequestStatus;
use StudyMentor\ContentEngine\GenerationResult\GeneratedArtifactReference;
use StudyMentor\ContentEngine\GenerationResult\GenerationResult;
use StudyMentor\ContentEngine\GenerationResult\GenerationResultBuilder;
use StudyMentor\ContentEngine\GenerationResult\GenerationResultRepositoryInterface;
use StudyMentor\ContentEngine\GenerationResult\GenerationResultStatus;
use StudyMentor\ContentEngine\GenerationResult\GenerationResultValidator;
use StudyMentor\ContentEngine\GenerationResult\ProviderExecutionReference;
use StudyMentor\ContentEngine\Modules\AcquisitionModule;
use StudyMentor\ContentEngine\Modules\AnnouncementModule;
use StudyMentor\ContentEngine\Modules\BlueprintModule;
use StudyMentor\ContentEngine\Modules\CorePlatformModule;
use StudyMentor\ContentEngine\Modules\GenerationRequestModule;
use StudyMentor\ContentEngine\Modules\GenerationResultModule;
use StudyMentor\ContentEngine\Modules\PromptContextModule;
use StudyMentor\ContentEngine\Modules\PromptPackageModule;
use StudyMentor\ContentEngine\Modules\SourceRegistryModule;
use StudyMentor\ContentEngine\PromptContext\PromptContextBuilder;
use StudyMentor\ContentEngine\PromptPackage\PromptPackageBuilder;
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

assertTrue(GenerationResultStatus::isValid(GenerationResultStatus::SUCCESS), 'success valid');
assertTrue(GenerationResultStatus::isValid(GenerationResultStatus::ERROR), 'error valid');
assertTrue(!GenerationResultStatus::isValid('pending'), 'pending is not a result status');

// --- Upstream fixtures: package → request ---

$blueprintBuilder = new ContentBlueprintBuilder();
$contextBuilder = new PromptContextBuilder();
$packageBuilder = new PromptPackageBuilder();
$requestBuilder = new GenerationRequestBuilder();
$resultBuilder = new GenerationResultBuilder();
$validator = new GenerationResultValidator();

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
$request = $requestBuilder->buildFromPackage(
    $package,
    new GenerationModelReference(array(
        'model_id' => 'text.default',
        'model_version' => '1',
    )),
    new GenerationParameters(array(
        'temperature' => 0.2,
        'max_output_tokens' => 1200,
        'response_format' => GenerationParameters::FORMAT_TEXT,
    ))
);
assertSameValue(GenerationRequestStatus::READY, $request->status(), 'fixture request ready');

$execution = new ProviderExecutionReference(array(
    'execution_id' => 'exec_42_1',
    'provider_code' => 'adapter.default',
    'started_at_utc' => '2026-07-31 15:00:00',
    'completed_at_utc' => '2026-07-31 15:00:02',
));
$artifact = new GeneratedArtifactReference(array(
    'artifact_id' => 'art_42_1',
    'artifact_kind' => GeneratedArtifactReference::KIND_CONTENT_CANDIDATE,
    'content_hash' => str_repeat('d', 64),
    'mime_type' => 'text/plain',
));

$success = $resultBuilder->buildSuccessFromRequest(
    $request,
    $execution,
    array($artifact),
    array('duration_ms' => 1800)
);

assertTrue($success instanceof GenerationResult, 'builder returns GenerationResult');
assertSameValue($request->requestId(), $success->requestId(), 'request id bound');
assertSameValue($request->requestHash(), $success->requestHash(), 'request hash bound');
assertSameValue($request->packageId(), $success->packageId(), 'package id bound');
assertSameValue(GenerationResultStatus::SUCCESS, $success->status(), 'success status');
assertSameValue(1800, $success->durationMs(), 'duration bound');
assertTrue($success->resultId() !== '', 'result id generated');
assertTrue(strlen($success->resultHash()) === 64, 'result hash sha256');
assertSameValue(1, count($success->artifacts()), 'one artifact');
assertSameValue('art_42_1', $success->artifacts()[0]->artifactId(), 'artifact id');
assertSameValue('adapter.default', $success->providerExecution()->providerCode(), 'opaque provider code');

$validation = $validator->validate($success);
assertTrue($validation['valid'] === true, 'success result valid');
assertTrue($validation['complete'] === true, 'success result complete');
assertTrue($validator->resultHashMatchesBinding($success) === true, 'result_hash matches binding');

// Deterministic hash
$successB = $resultBuilder->buildSuccessFromRequest(
    $request,
    $execution,
    array($artifact),
    array('duration_ms' => 1800, 'result_id' => 'gres_fixed')
);
assertSameValue(
    $success->resultHash(),
    $successB->resultHash(),
    'identical binding yields identical result_hash'
);

// Artifact change => new hash
$successC = $resultBuilder->buildSuccessFromRequest(
    $request,
    $execution,
    array(
        new GeneratedArtifactReference(array(
            'artifact_id' => 'art_42_2',
            'artifact_kind' => GeneratedArtifactReference::KIND_STRUCTURED_CANDIDATE,
            'content_hash' => str_repeat('e', 64),
        )),
    ),
    array('duration_ms' => 1800)
);
assertTrue(
    $success->resultHash() !== $successC->resultHash(),
    'artifact change must change result_hash'
);

// Error outcome
$error = $resultBuilder->buildErrorFromRequest(
    $request,
    $execution,
    'provider_unavailable',
    'Upstream adapter returned unavailable',
    array('duration_ms' => 50)
);
assertSameValue(GenerationResultStatus::ERROR, $error->status(), 'error status');
assertSameValue('provider_unavailable', $error->errorCode(), 'error code');
assertTrue($error->artifacts() === array(), 'error has no artifacts');
assertTrue($validator->isComplete($error) === true, 'error result complete');
assertTrue($validator->resultHashMatchesBinding($error) === true, 'error hash matches');
assertTrue(
    $success->resultHash() !== $error->resultHash(),
    'success and error hashes differ'
);

// Reject non-ready request
$notReady = new GenerationRequest(array(
    'request_id' => 'gr_x',
    'announcement_id' => 1,
    'package_id' => 'pp_x',
    'package_hash' => str_repeat('f', 64),
    'request_hash' => str_repeat('c', 64),
    'status' => GenerationRequestStatus::DRAFT,
    'model_reference' => array('model_id' => 'text.default', 'model_version' => '1'),
    'parameters' => array(),
));
$notReadyThrown = false;
try {
    $resultBuilder->buildSuccessFromRequest($notReady, $execution, array($artifact));
} catch (\InvalidArgumentException $e) {
    $notReadyThrown = ($e->getMessage() === 'request_not_ready');
}
assertTrue($notReadyThrown, 'non-ready request must throw');

// Reject success without artifacts
$noArtifactsThrown = false;
try {
    $resultBuilder->buildSuccessFromRequest($request, $execution, array());
} catch (\InvalidArgumentException $e) {
    $noArtifactsThrown = ($e->getMessage() === 'artifacts_required');
}
assertTrue($noArtifactsThrown, 'success without artifacts must throw');

// Reject error without code
$noErrorCodeThrown = false;
try {
    $resultBuilder->buildErrorFromRequest($request, $execution, '');
} catch (\InvalidArgumentException $e) {
    $noErrorCodeThrown = ($e->getMessage() === 'error_code_required');
}
assertTrue($noErrorCodeThrown, 'error without code must throw');

$invalid = new GenerationResult(array(
    'result_id' => '',
    'request_id' => '',
    'request_hash' => 'short',
    'announcement_id' => 0,
    'package_id' => '',
    'package_hash' => 'x',
    'status' => 'nope',
    'result_hash' => 'y',
    'duration_ms' => -1,
    'provider_execution' => array(
        'execution_id' => '',
        'provider_code' => '',
    ),
    'artifacts' => array(),
    'error_code' => '',
));
$invalidResult = $validator->validate($invalid);
assertTrue($invalidResult['valid'] === false, 'invalid result fails');
assertTrue(
    in_array('result_id_required', $invalidResult['errors'], true),
    'must report result_id_required'
);
assertTrue($validator->isComplete($invalid) === false, 'invalid not complete');

$serialized = $success->toArray();
assertTrue(
    isset(
        $serialized['provider_execution'],
        $serialized['artifacts'],
        $serialized['result_hash']
    ),
    'toArray exposes core groups'
);
$rehydrated = new GenerationResult($serialized);
assertSameValue($success->resultId(), $rehydrated->resultId(), 'round-trip id');
assertSameValue($success->resultHash(), $rehydrated->resultHash(), 'round-trip hash');

// --- Architecture ---

assertTrue(
    interface_exists(GenerationResultRepositoryInterface::class),
    'GenerationResultRepositoryInterface must exist'
);

$resultDir = $pluginDirectory . '/src/GenerationResult';
$resultFiles = glob($resultDir . '/*.php');
assertTrue(is_array($resultFiles) && count($resultFiles) > 0, 'GenerationResult directory required');

foreach ($resultFiles as $file) {
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
    $queueToken = 'wp_' . 'schedule_';
    $systemPromptToken = 'You are a';
    $retryToken = 'retry_policy';
    $workerToken = 'dispatch_job';
    assertTrue(strpos($contents, $dbToken) === false, basename($file) . ' must not use db handle');
    assertTrue(stripos($contents, $ddlToken) === false, basename($file) . ' must not DDL');
    assertTrue(stripos($contents, $restToken) === false, basename($file) . ' must not REST');
    assertTrue(stripos($contents, $menuToken) === false, basename($file) . ' must not admin menu');
    assertTrue(stripos($contents, $httpToken) === false, basename($file) . ' must not HTTP');
    assertTrue(stripos($contents, $curlToken) === false, basename($file) . ' must not curl');
    assertTrue(stripos($contents, $vendorA) === false, basename($file) . ' must not vendor A');
    assertTrue(stripos($contents, $vendorB) === false, basename($file) . ' must not vendor B');
    assertTrue(stripos($contents, $queueToken) === false, basename($file) . ' must not schedule/queue');
    assertTrue(strpos($contents, $systemPromptToken) === false, basename($file) . ' must not prompt prose');
    assertTrue(stripos($contents, $retryToken) === false, basename($file) . ' must not retry logic');
    assertTrue(stripos($contents, $workerToken) === false, basename($file) . ' must not workers');
}

// Artifact reference must not carry body/prose fields
$artifactFile = $resultDir . '/GeneratedArtifactReference.php';
$artifactContents = file_get_contents($artifactFile);
assertTrue(
    strpos($artifactContents, 'artifact_body') === false
        && strpos($artifactContents, 'content_body') === false
        && strpos($artifactContents, 'system_prompt') === false
        && strpos($artifactContents, 'rendered_prompt') === false,
    'GeneratedArtifactReference must not carry payload/prompt fields'
);

$repoInterfaceFile = $resultDir . '/GenerationResultRepositoryInterface.php';
$repoInterfaceContents = file_get_contents($repoInterfaceFile);
assertTrue(
    strpos($repoInterfaceContents, 'interface GenerationResultRepositoryInterface') !== false,
    'repository file must declare an interface'
);
assertTrue(
    preg_match('/\bfinal\s+class\b|\bclass\s+\w+/', $repoInterfaceContents) !== 1,
    'BUILD-005 must not ship a concrete generation result repository class'
);

// --- Module registration ---

$databaseGlobalKey = 'wp' . 'db';
$GLOBALS[$databaseGlobalKey] = (object) array(
    'prefix' => 'wp_',
);

$plugin = new Plugin();
assertTrue($plugin instanceof Plugin, 'Plugin constructs with GenerationResultModule');

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
$loader = new ModuleLoader($moduleRegistry, $container);
$loader->load();

assertTrue($moduleRegistry->has('generation_result'), 'generation_result module registered');
assertTrue(
    $container->get(GenerationResultBuilder::class) instanceof GenerationResultBuilder,
    'builder resolves'
);
assertTrue(
    $container->get(GenerationResultValidator::class) instanceof GenerationResultValidator,
    'validator resolves'
);

$pluginContents = file_get_contents($pluginDirectory . '/src/Core/Plugin.php');
assertTrue(
    strpos($pluginContents, 'GenerationResultModule') !== false,
    'Plugin registers GenerationResultModule'
);

if ($failures !== array()) {
    fwrite(STDERR, "BUILD-005 Generation Result smoke test failed.\n");

    foreach ($failures as $failure) {
        fwrite(STDERR, '- ' . $failure . "\n");
    }

    exit(1);
}

echo "BUILD-005 Generation Result smoke test passed.\n";
echo 'Assertions: ' . $passed . "\n";
