<?php

/**
 * CIP-003E evidence and diagnostics smoke test.
 *
 * Exercises metadata-only evidence export and enriched diagnostics without
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

use StudyMentor\ContentEngine\Acquisition\AcquisitionDiagnostics;
use StudyMentor\ContentEngine\Acquisition\AcquisitionResult;
use StudyMentor\ContentEngine\Acquisition\SourceAcquisitionService;
use StudyMentor\ContentEngine\Core\ModuleLoader;
use StudyMentor\ContentEngine\Core\ModuleRegistry;
use StudyMentor\ContentEngine\Core\Plugin;
use StudyMentor\ContentEngine\Core\ServiceContainer;
use StudyMentor\ContentEngine\Evidence\Evidence;
use StudyMentor\ContentEngine\Evidence\EvidenceRepositoryInterface;
use StudyMentor\ContentEngine\Evidence\InMemoryEvidenceRepository;
use StudyMentor\ContentEngine\Fingerprint\FingerprintService;
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

// --- FingerprintService describe and deterministic fingerprint ---

$fingerprintService = new FingerprintService();
$description = $fingerprintService->describe();

assertTrue(is_array($description), 'FingerprintService::describe() must return an array');
assertSameValue('sha256', $description['algorithm'], 'FingerprintService describe must include algorithm');
assertTrue(
    isset($description['body_hash'], $description['content_hash'], $description['identity_hash']),
    'FingerprintService describe must include hash field descriptions'
);

$firstFingerprint = $fingerprintService->fingerprint("line one\r\nline two\n", 'https://Example.com/feed', 'src-1');
$secondFingerprint = $fingerprintService->fingerprint("line one\r\nline two\n", 'https://Example.com/feed', 'src-1');

assertSameValue(
    $firstFingerprint['body_hash'],
    $secondFingerprint['body_hash'],
    'FingerprintService fingerprint must be deterministic for body_hash'
);
assertSameValue(
    $firstFingerprint['content_hash'],
    $secondFingerprint['content_hash'],
    'FingerprintService fingerprint must be deterministic for content_hash'
);
assertSameValue(
    $firstFingerprint['identity_hash'],
    $secondFingerprint['identity_hash'],
    'FingerprintService fingerprint must be deterministic for identity_hash'
);

// --- Evidence metadata export ---

$evidence = new Evidence(array(
    'source' => 'test-source',
    'source_type' => 'rss',
    'url' => 'https://example.com/rss',
    'fetched_at' => '2026-07-30T12:00:00Z',
    'http_status' => 200,
    'headers' => array('content-type' => 'application/rss+xml'),
    'mime_type' => 'application/rss+xml',
    'body' => '<rss><channel></channel></rss>',
    'content_hash' => hash('sha256', 'normalized-body'),
    'fetch_duration' => 0.12,
    'collector' => 'safe_feed',
    'parser_profile' => '',
    'body_hash' => hash('sha256', '<rss><channel></channel></rss>'),
    'identity_hash' => hash('sha256', 'test-source|https://example.com/rss'),
    'final_url' => 'https://example.com/rss',
    'response_bytes' => 32,
));

$metadata = $evidence->toMetadataArray();

assertTrue(!isset($metadata['body']), 'toMetadataArray must omit body');
assertSameValue(true, $metadata['body_omitted'], 'toMetadataArray must set body_omitted');
assertSameValue('rss', $metadata['source_type'], 'toMetadataArray must include source_type');

// --- InMemoryEvidenceRepository store operations and summaries ---

$repository = new InMemoryEvidenceRepository();
$repository->store($evidence);
$repository->store($evidence);

assertSameValue(2, $repository->storeOperations(), 'Duplicate store must increment storeOperations');
assertSameValue(1, $repository->count(), 'Duplicate store must not increase evidence count');

$summaries = $repository->summaries();

assertTrue(isset($summaries[0]['storage_key']), 'summaries must include storage_key');
assertTrue(!isset($summaries[0]['body']), 'summaries must not include body');
assertSameValue(true, $summaries[0]['body_omitted'], 'summaries must set body_omitted');

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

$acquisitionDiagnostics = $container->get(AcquisitionDiagnostics::class);
$diagnosticsStatus = $acquisitionDiagnostics->status();

assertTrue(
    isset($diagnosticsStatus['fingerprint']) && is_array($diagnosticsStatus['fingerprint']),
    'AcquisitionDiagnostics must expose fingerprint block'
);
assertTrue(
    isset($diagnosticsStatus['evidence']) && is_array($diagnosticsStatus['evidence']),
    'AcquisitionDiagnostics must expose evidence block'
);
assertTrue(
    isset($diagnosticsStatus['evidence']['entries']) && is_array($diagnosticsStatus['evidence']['entries']),
    'AcquisitionDiagnostics evidence block must include entries'
);
assertTrue(
    count($diagnosticsStatus['evidence']['entries']) <= 10,
    'AcquisitionDiagnostics must cap evidence entries at 10'
);

// --- Pre-fetch acquire records diagnostics but stores no evidence ---

$sourceAcquisitionService = $container->get(SourceAcquisitionService::class);
$evidenceRepository = $container->get(EvidenceRepositoryInterface::class);
$evidenceCountBefore = $evidenceRepository->count();
$recordedBefore = $diagnosticsStatus['acquisitions_recorded'];

$acquireResult = $sourceAcquisitionService->acquire(array(
    'source_id' => 1,
    'source_key' => 'test-source',
    'url' => '',
    'allowed_domains' => array('example.com'),
    'source_type' => 'rss',
    'parser_profile' => '',
));

assertTrue($acquireResult->success() === false, 'acquire() must fail for missing feed URL');
assertSameValue(
    $evidenceCountBefore,
    $evidenceRepository->count(),
    'Evidence must not be stored on pre-fetch acquisition failure'
);

$updatedStatus = $acquisitionDiagnostics->status();
assertSameValue(
    $recordedBefore + 1,
    $updatedStatus['acquisitions_recorded'],
    'AcquisitionDiagnostics must record engine acquisition attempts'
);

$lastResult = $updatedStatus['last_result'];
assertTrue(is_array($lastResult), 'last_result must be an array after acquisition attempt');

$acquisitionDiagnostics->recordResult(new AcquisitionResult(array(
    'success' => true,
    'evidence' => $evidence,
)));

$statusWithEvidence = $acquisitionDiagnostics->status();
$lastWithEvidence = $statusWithEvidence['last_result'];

assertTrue(
    is_array($lastWithEvidence)
    && isset($lastWithEvidence['evidence'])
    && is_array($lastWithEvidence['evidence']),
    'last_result must include evidence metadata when evidence is present'
);
assertTrue(
    !isset($lastWithEvidence['evidence']['body']),
    'last_result evidence must not expose body'
);
assertSameValue(
    true,
    $lastWithEvidence['evidence']['body_omitted'],
    'last_result evidence must set body_omitted'
);

// --- Platform diagnostics confirmations ---

$platformDiagnostics = $container->get(PlatformDiagnostics::class);
$diagnostics = $platformDiagnostics->collect();

assertSameValue(
    'editorial-workspace-phase2',
    $diagnostics['versions']['platform_phase'],
    'PlatformDiagnostics phase label must match CIP-003E label'
);
assertSameValue(
    'In-Memory',
    $diagnostics['confirmations']['evidence_store'],
    'PlatformDiagnostics evidence_store confirmation must be In-Memory'
);
assertTrue(
    $diagnostics['confirmations']['acquisition'] === 'Active',
    'PlatformDiagnostics acquisition confirmation must be Active'
);

$versionRegistry = $container->get(VersionRegistry::class);
assertSameValue(
    'editorial-workspace-phase2',
    $versionRegistry->get('platform_phase'),
    'VersionRegistry platform phase must match CIP-003E label'
);

$capabilityRegistry = $container->get(CapabilityRegistry::class);
assertTrue(
    $capabilityRegistry->isEnabled(CapabilityRegistry::ACQUISITION) === true,
    'acquisition capability must be enabled in CIP-003E'
);

if ($failures !== array()) {
    fwrite(STDERR, "CIP-003E evidence diagnostics smoke test failed.\n");

    foreach ($failures as $failure) {
        fwrite(STDERR, '- ' . $failure . "\n");
    }

    exit(1);
}

echo "CIP-003E evidence diagnostics smoke test passed.\n";
echo 'Assertions: ' . $passed . "\n";
