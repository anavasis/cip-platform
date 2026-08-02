<?php

/**
 * Editorial Slice A smoke: ingest → lifecycle → generate → BUILD-001…005 → stub → preview.
 *
 * No publishing, workflow, compliance, real AI, or persistent preview storage.
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

if (!defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
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

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data, $options = 0, $depth = 512)
    {
        return json_encode($data, $options, $depth);
    }
}

if (!function_exists('esc_html')) {
    function esc_html($text)
    {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_url')) {
    function esc_url($url)
    {
        return (string) $url;
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr($text)
    {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('admin_url')) {
    function admin_url($path = '')
    {
        return 'https://example.test/wp-admin/' . ltrim((string) $path, '/');
    }
}

if (!function_exists('add_query_arg')) {
    function add_query_arg($args, $url = '')
    {
        $base = $url !== '' ? (string) $url : 'https://example.test/wp-admin/admin.php';
        $query = http_build_query($args);

        return $base . (strpos($base, '?') === false ? '?' : '&') . $query;
    }
}

if (!function_exists('wp_nonce_field')) {
    function wp_nonce_field($action = -1, $name = '_wpnonce', $referer = true, $echo = true)
    {
        $field = '<input type="hidden" name="' . esc_attr($name) . '" value="test-nonce" />';
        if ($echo) {
            echo $field;
        }

        return $field;
    }
}

final class EditorialSliceAFakeWpdb
{
    public $prefix = 'wp_';
    public $insert_id = 0;
    /** @var array<int, array<string, mixed>> */
    private $rows = array();
    private $nextId = 1;

    public function prepare($query)
    {
        $args = func_get_args();
        array_shift($args);
        $prepared = (string) $query;

        foreach ($args as $arg) {
            $replacement = is_int($arg) ? (string) $arg : "'" . str_replace("'", "''", (string) $arg) . "'";
            $prepared = preg_replace('/%[ds]/', $replacement, $prepared, 1);
        }

        return $prepared;
    }

    public function insert($table, $data, $formats = null)
    {
        unset($table, $formats);
        $row = $data;
        $row['id'] = $this->nextId;
        $this->rows[$this->nextId] = $row;
        $this->insert_id = $this->nextId;
        $this->nextId++;

        return 1;
    }

    public function update($table, $data, $where, $format = null, $whereFormat = null)
    {
        unset($table, $format, $whereFormat);
        $id = isset($where['id']) ? (int) $where['id'] : 0;

        if ($id <= 0 || !isset($this->rows[$id])) {
            return false;
        }

        foreach ($data as $key => $value) {
            $this->rows[$id][$key] = $value;
        }

        return 1;
    }

    public function get_row($query, $output = ARRAY_A)
    {
        unset($output);

        if (!preg_match("/source_id = (\d+).*identity_hash = '([^']+)'/", (string) $query, $matches)) {
            return null;
        }

        $sourceId = (int) $matches[1];
        $identityHash = (string) $matches[2];

        foreach ($this->rows as $row) {
            if (
                isset($row['source_id'], $row['identity_hash'])
                && (int) $row['source_id'] === $sourceId
                && (string) $row['identity_hash'] === $identityHash
            ) {
                return $row;
            }
        }

        return null;
    }

    public function get_var($query)
    {
        $row = $this->get_row($query);

        return is_array($row) && isset($row['id']) ? $row['id'] : null;
    }

    public function get_results($query, $output = ARRAY_A)
    {
        unset($query, $output);

        return array();
    }
}

$GLOBALS['wpdb'] = new EditorialSliceAFakeWpdb();

require_once $pluginDirectory . '/src/Core/Autoloader.php';
StudyMentor\ContentEngine\Core\Autoloader::register($pluginDirectory . '/src');

use StudyMentor\ContentEngine\Announcement\AnnouncementCandidate;
use StudyMentor\ContentEngine\Announcement\EditorialIngestionService;
use StudyMentor\ContentEngine\Article\ArticlePreview;
use StudyMentor\ContentEngine\Article\ArticlePreviewRepositoryInterface;
use StudyMentor\ContentEngine\Article\InMemoryArticlePreviewRepository;
use StudyMentor\ContentEngine\Core\ModuleLoader;
use StudyMentor\ContentEngine\Core\ModuleRegistry;
use StudyMentor\ContentEngine\Core\Plugin;
use StudyMentor\ContentEngine\Core\ServiceContainer;
use StudyMentor\ContentEngine\Editorial\AnnouncementSnapshotMapper;
use StudyMentor\ContentEngine\Generation\AiProviderInterface;
use StudyMentor\ContentEngine\Generation\GenerationOrchestrator;
use StudyMentor\ContentEngine\Generation\StubAiProvider;
use StudyMentor\ContentEngine\GenerationRequest\GenerationModelReference;
use StudyMentor\ContentEngine\GenerationRequest\GenerationParameters;
use StudyMentor\ContentEngine\GenerationRequest\GenerationRequestBuilder;
use StudyMentor\ContentEngine\GenerationResult\GenerationResult;
use StudyMentor\ContentEngine\GenerationResult\GenerationResultStatus;
use StudyMentor\ContentEngine\Modules\AcquisitionModule;
use StudyMentor\ContentEngine\Modules\AnnouncementModule;
use StudyMentor\ContentEngine\Modules\BlueprintModule;
use StudyMentor\ContentEngine\Modules\CorePlatformModule;
use StudyMentor\ContentEngine\Modules\EditorialSliceModule;
use StudyMentor\ContentEngine\Modules\GenerationRequestModule;
use StudyMentor\ContentEngine\Modules\GenerationResultModule;
use StudyMentor\ContentEngine\Modules\PromptContextModule;
use StudyMentor\ContentEngine\Modules\PromptPackageModule;
use StudyMentor\ContentEngine\Modules\SourceRegistryModule;
use StudyMentor\ContentEngine\Platform\PlatformDiagnostics;
use StudyMentor\ContentEngine\PromptContext\PromptContextBuilder;
use StudyMentor\ContentEngine\PromptPackage\PromptPackageBuilder;
use StudyMentor\ContentEngine\PromptPackage\PromptTemplateReference;
use StudyMentor\ContentEngine\Blueprint\ContentBlueprintBuilder;

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

// --- Domain availability ---

assertTrue(class_exists(AnnouncementSnapshotMapper::class), 'AnnouncementSnapshotMapper available');
assertTrue(class_exists(GenerationOrchestrator::class), 'GenerationOrchestrator available');
assertTrue(class_exists(StubAiProvider::class), 'StubAiProvider available');
assertTrue(class_exists(ArticlePreview::class), 'ArticlePreview available');
assertTrue(interface_exists(ArticlePreviewRepositoryInterface::class), 'ArticlePreviewRepositoryInterface available');
assertTrue(class_exists(InMemoryArticlePreviewRepository::class), 'InMemoryArticlePreviewRepository available');
assertTrue(class_exists(EditorialSliceModule::class), 'EditorialSliceModule available');

// --- Composition root ---

$plugin = new Plugin();
assertTrue($plugin instanceof Plugin, 'Plugin constructs with Editorial Slice wiring');

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

$moduleLoader = new ModuleLoader($moduleRegistry, $container);
$moduleLoader->load();
assertSameValue(ModuleLoader::STATE_LOADED, $moduleLoader->state(), 'ModuleLoader loaded');
assertTrue($moduleRegistry->has('editorial_slice'), 'editorial_slice module registered');
assertTrue($moduleRegistry->has('announcement'), 'announcement module registered');
assertTrue($moduleRegistry->has('blueprint'), 'blueprint module registered');
assertTrue($moduleRegistry->has('prompt_context'), 'prompt_context module registered');
assertTrue($moduleRegistry->has('prompt_package'), 'prompt_package module registered');
assertTrue($moduleRegistry->has('generation_request'), 'generation_request module registered');
assertTrue($moduleRegistry->has('generation_result'), 'generation_result module registered');

$ingestion = $container->get(EditorialIngestionService::class);
$orchestrator = $container->get(GenerationOrchestrator::class);
$mapper = $container->get(AnnouncementSnapshotMapper::class);
$previewRepo = $container->get(ArticlePreviewRepositoryInterface::class);
$provider = $container->get(AiProviderInterface::class);
$platformDiagnostics = $container->get(PlatformDiagnostics::class);

assertTrue($ingestion instanceof EditorialIngestionService, 'EditorialIngestionService resolves');
assertTrue($orchestrator instanceof GenerationOrchestrator, 'GenerationOrchestrator resolves');
assertTrue($mapper instanceof AnnouncementSnapshotMapper, 'AnnouncementSnapshotMapper resolves');
assertTrue($previewRepo instanceof InMemoryArticlePreviewRepository, 'Preview repo is in-memory');
assertTrue($provider instanceof StubAiProvider, 'AiProvider is StubAiProvider');

// --- Manual ingestion (candidate path; no HTTP) ---

$ingestResult = $ingestion->ingestCandidates(array(
    new AnnouncementCandidate(array(
        'source_id' => 21,
        'title' => 'Slice A scholarship notice',
        'canonical_url' => 'https://example.edu/slice-a/1',
        'source_guid' => 'https://example.edu/slice-a/1',
        'published_at_utc' => '2024-06-01 10:00:00',
        'raw_payload' => array(
            'title' => 'Slice A scholarship notice',
            'summary' => 'Students may apply until September.',
            'category' => 'scholarships',
        ),
    )),
));
assertTrue($ingestResult->success() === true, 'Manual ingestion via candidates succeeds');
assertSameValue(1, $ingestResult->newCount(), 'Ingestion creates NEW announcement');

$platformDiagnostics->recordLastIngestion(array(
    'at' => current_time('mysql', true),
    'ok' => true,
    'source_id' => 21,
    'new_count' => $ingestResult->newCount(),
    'updated_count' => $ingestResult->updatedCount(),
    'unchanged_count' => $ingestResult->unchangedCount(),
    'duplicate_count' => $ingestResult->duplicateCount(),
    'candidates' => $ingestResult->candidates(),
));

// --- Snapshot mapper ---

$announcementItem = array(
    'id' => 101,
    'source_id' => 21,
    'raw_title' => 'Slice A scholarship notice',
    'canonical_url' => 'https://example.edu/slice-a/1',
    'source_guid' => 'https://example.edu/slice-a/1',
    'source_published_at_utc' => '2024-06-01 10:00:00',
    'content_hash' => str_repeat('b', 64),
    'revision_no' => 1,
    'display_raw_payload' => wp_json_encode(array(
        'title' => 'Slice A scholarship notice',
        'summary' => 'Students may apply until September.',
        'category' => 'scholarships',
    )),
);

$snapshot = $mapper->fromSourceItem($announcementItem);
assertSameValue(101, $snapshot['announcement_id'], 'Mapper sets announcement_id');
assertSameValue('Slice A scholarship notice', $snapshot['raw_title'], 'Mapper sets raw_title');
assertSameValue(str_repeat('b', 64), $snapshot['source_content_hash'], 'Mapper sets content hash');
assertTrue(is_array($snapshot['raw_payload']), 'Mapper decodes raw_payload');

// --- Full generate pipeline ---

$outcome = $orchestrator->generateFromAnnouncement($announcementItem);
assertTrue($outcome['ok'] === true, 'GenerationOrchestrator succeeds: ' . (isset($outcome['error']) ? $outcome['error'] : ''));
assertTrue(isset($outcome['stages']) && is_array($outcome['stages']), 'Stages payload present');
assertTrue($outcome['stages']['build_001'] === true, 'BUILD-001 executed');
assertTrue($outcome['stages']['build_002'] === true, 'BUILD-002 executed');
assertTrue($outcome['stages']['build_003'] === true, 'BUILD-003 executed');
assertTrue($outcome['stages']['build_004'] === true, 'BUILD-004 executed');
assertTrue($outcome['stages']['stub_provider'] === true, 'Stub provider executed');
assertTrue($outcome['stages']['build_005'] === true, 'BUILD-005 executed');
assertTrue($outcome['stages']['preview_stored'] === true, 'Preview stored');

assertTrue(isset($outcome['result']) && $outcome['result'] instanceof GenerationResult, 'GenerationResult produced');
assertSameValue(
    GenerationResultStatus::SUCCESS,
    $outcome['result']->status(),
    'GenerationResult status is success'
);

assertTrue(isset($outcome['preview']) && $outcome['preview'] instanceof ArticlePreview, 'Preview aggregate returned');
$stored = $previewRepo->findLatestForAnnouncement(101);
assertTrue($stored instanceof ArticlePreview, 'Preview stored in repository');
assertSameValue($outcome['preview_id'], $stored->previewId(), 'Stored preview id matches');
assertTrue(strpos($stored->body(), 'Stub article preview') !== false, 'Stub body present in preview');

// --- Preview rendered (view panel) ---

$viewData = array(
    'title' => 'Announcements',
    'mode' => 'detail',
    'error_messages' => array(),
    'success_messages' => array(),
    'workspace_url' => add_query_arg(array('page' => 'smce-editorial'), admin_url('admin.php')),
    'back_url' => add_query_arg(array('page' => 'smce-editorial-announcements'), admin_url('admin.php')),
    'detail_item' => array(
        'id' => '101',
        'source_id' => '21',
        'source_name' => 'Example',
        'source_slug' => 'example',
        'identity_hash' => str_repeat('c', 64),
        'content_hash' => str_repeat('b', 64),
        'raw_title' => 'Slice A scholarship notice',
        'status' => 'NEW',
        'revision_no' => '1',
        'first_seen_at_utc' => '2024-06-01 10:00:00',
        'last_seen_at_utc' => '2024-06-01 10:00:00',
        'updated_at_utc' => '2024-06-01 10:00:00',
        'raw_payload_bytes' => 32,
        'payload_truncated' => false,
        'json_is_valid' => true,
        'pretty_payload' => '{}',
        'raw_payload' => '{}',
    ),
    'generate_form_url' => add_query_arg(
        array('page' => 'smce-editorial-announcements', 'item_id' => 101),
        admin_url('admin.php')
    ),
    'generate_nonce_action' => 'smce_editorial_generate_101',
    'article_preview' => $stored->toArray(),
    'generation_result' => array(
        'blueprint_id' => $outcome['blueprint_id'],
        'request_id' => $outcome['request_id'],
        'result_id' => $outcome['result_id'],
        'preview_id' => $outcome['preview_id'],
        'stages' => $outcome['stages'],
    ),
    'lifecycle_diagnostics' => array(
        'status' => 'ready',
        'store' => 'smce_source_items',
        'last_batch' => null,
    ),
    'spine_ready' => 'Ready',
    'last_generation' => null,
    'filters' => array(),
    'form_url' => admin_url('admin.php'),
    'reset_url' => '',
    'source_options' => array(),
    'source_options_truncated' => false,
    'items' => array(),
    'page' => 1,
    'has_previous' => false,
    'has_next' => false,
    'limit_reached' => false,
    'previous_url' => '',
    'next_url' => '',
);

ob_start();
$data = $viewData;
require $pluginDirectory . '/views/admin/editorial-announcements.php';
$rendered = ob_get_clean();
assertTrue(strpos($rendered, 'smce-article-preview-panel') !== false, 'Preview panel markup rendered');
assertTrue(strpos($rendered, 'Stub article preview') !== false, 'Preview body rendered');
assertTrue(strpos($rendered, 'Generate') !== false, 'Generate action present');

// --- Diagnostics ---

$diagnostics = $platformDiagnostics->collect();
assertTrue(isset($diagnostics['last_ingestion']) && is_array($diagnostics['last_ingestion']), 'last_ingestion present');
assertTrue($diagnostics['last_ingestion']['ok'] === true, 'last_ingestion ok');
assertTrue(isset($diagnostics['last_generation']) && is_array($diagnostics['last_generation']), 'last_generation present');
assertTrue($diagnostics['last_generation']['ok'] === true, 'last_generation ok');
assertTrue(in_array('editorial_slice', $diagnostics['module_ids'], true), 'diagnostics lists editorial_slice');

// --- Stub provider is offline / deterministic for the same request ---

$stubSource = file_get_contents($pluginDirectory . '/src/Generation/StubAiProvider.php');
$remoteNeedle = 'wp_' . 'remote_';
$curlNeedle = 'cur' . 'l_';
$openaiNeedle = 'open' . 'ai';
$anthropicNeedle = 'anthro' . 'pic';
$geminiNeedle = 'gem' . 'ini';
assertTrue(strpos($stubSource, $remoteNeedle) === false, 'Stub has no HTTP remote helper');
assertTrue(stripos($stubSource, $openaiNeedle) === false, 'Stub has no OpenAI');
assertTrue(stripos($stubSource, $anthropicNeedle) === false, 'Stub has no Anthropic');
assertTrue(stripos($stubSource, $geminiNeedle) === false, 'Stub has no Gemini');
assertTrue(strpos($stubSource, $curlNeedle) === false, 'Stub has no curl');

$blueprint = (new ContentBlueprintBuilder())->buildFromAnnouncement($snapshot);
$context = (new PromptContextBuilder())->buildFromAnnouncementAndBlueprint($snapshot, $blueprint);
$packageBuilder = new PromptPackageBuilder();
$package = $packageBuilder->buildFromContextAndBlueprint(
    $context,
    $packageBuilder->blueprintReferenceFromContext($context),
    new PromptTemplateReference(array(
        'template_id' => 'smce.editorial.slice_a',
        'template_version' => '1.0.0',
    ))
);
$fixedRequest = (new GenerationRequestBuilder())->buildFromPackage(
    $package,
    new GenerationModelReference(array(
        'model_id' => 'smce.stub.deterministic',
        'model_version' => '1',
    )),
    new GenerationParameters(array(
        'temperature' => 0.0,
        'max_output_tokens' => 2048,
        'response_format' => GenerationParameters::FORMAT_TEXT,
        'seed' => 1,
    ))
);
$providerOutA = $provider->generate($fixedRequest);
$providerOutB = $provider->generate($fixedRequest);
assertSameValue($providerOutA['body'], $providerOutB['body'], 'Stub provider is deterministic for same request');
assertSameValue($providerOutA['content_hash'], $providerOutB['content_hash'], 'Stub content hash is stable');

if ($failures === array()) {
    fwrite(STDOUT, "Editorial Slice A smoke passed ({$passed} assertions).\n");
    exit(0);
}

fwrite(STDERR, "Editorial Slice A smoke failed.\n");
foreach ($failures as $failure) {
    fwrite(STDERR, '- ' . $failure . "\n");
}
exit(1);
