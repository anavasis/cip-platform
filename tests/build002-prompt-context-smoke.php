<?php

/**
 * BUILD-002 Prompt Context domain smoke / unit / architecture tests.
 *
 * No database, UI, AI providers, prompt templates, packages, or publishing.
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

use StudyMentor\ContentEngine\Blueprint\ArticleType;
use StudyMentor\ContentEngine\Blueprint\ContentBlueprintBuilder;
use StudyMentor\ContentEngine\Core\ModuleLoader;
use StudyMentor\ContentEngine\Core\ModuleRegistry;
use StudyMentor\ContentEngine\Core\Plugin;
use StudyMentor\ContentEngine\Core\ServiceContainer;
use StudyMentor\ContentEngine\Modules\AcquisitionModule;
use StudyMentor\ContentEngine\Modules\AnnouncementModule;
use StudyMentor\ContentEngine\Modules\BlueprintModule;
use StudyMentor\ContentEngine\Modules\CorePlatformModule;
use StudyMentor\ContentEngine\Modules\PromptContextModule;
use StudyMentor\ContentEngine\Modules\SourceRegistryModule;
use StudyMentor\ContentEngine\PromptContext\AnnouncementFacts;
use StudyMentor\ContentEngine\PromptContext\BlueprintProjection;
use StudyMentor\ContentEngine\PromptContext\PromptContext;
use StudyMentor\ContentEngine\PromptContext\PromptContextBuilder;
use StudyMentor\ContentEngine\PromptContext\PromptContextRepositoryInterface;
use StudyMentor\ContentEngine\PromptContext\PromptContextStatus;
use StudyMentor\ContentEngine\PromptContext\PromptContextValidator;

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

assertTrue(PromptContextStatus::isValid(PromptContextStatus::DRAFT), 'draft status valid');
assertTrue(PromptContextStatus::isValid(PromptContextStatus::READY), 'ready status valid');
assertTrue(PromptContextStatus::isValid(PromptContextStatus::SUPERSEDED), 'superseded status valid');
assertTrue(!PromptContextStatus::isValid('published'), 'published is not a prompt context status');

// --- Unit: builder + validator ---

$blueprintBuilder = new ContentBlueprintBuilder();
$contextBuilder = new PromptContextBuilder();
$validator = new PromptContextValidator();

$announcementSnapshot = array(
    'announcement_id' => 42,
    'source_id' => 3,
    'raw_title' => 'Scholarship deadline extended',
    'canonical_url' => 'https://example.edu/news/scholarship',
    'source_guid' => 'guid-42',
    'source_published_at_utc' => '2026-07-01 10:00:00',
    'source_content_hash' => str_repeat('a', 64),
    'announcement_revision_no' => 1,
    'language' => 'el',
    'raw_payload' => array(
        'summary' => 'The scholarship deadline has been extended to September.',
        'category' => 'scholarships',
        'deadline' => '2026-09-30',
        'institution' => 'Example University',
    ),
);

$blueprint = $blueprintBuilder->buildFromAnnouncement($announcementSnapshot);
$context = $contextBuilder->buildFromAnnouncementAndBlueprint($announcementSnapshot, $blueprint);

assertTrue($context instanceof PromptContext, 'builder must return PromptContext');
assertSameValue(42, $context->announcementId(), 'announcement id must be preserved');
assertSameValue($blueprint->blueprintId(), $context->blueprintId(), 'blueprint id must bind');
assertSameValue(PromptContextStatus::DRAFT, $context->status(), 'builder must create draft status');
assertTrue($context->contextId() !== '', 'context id must be generated');
assertTrue(strlen($context->contextHash()) === 64, 'context hash must be sha256 hex');
assertTrue($context->facts() instanceof AnnouncementFacts, 'facts VO required');
assertTrue($context->blueprintProjection() instanceof BlueprintProjection, 'projection VO required');
assertSameValue(
    'Scholarship deadline extended',
    $context->facts()->rawTitle(),
    'facts must carry announcement title'
);
assertSameValue(
    'scholarships',
    $context->facts()->keyFacts()['category'],
    'key facts must extract category'
);
assertTrue(
    strpos($context->facts()->summaryText(), 'scholarship deadline') !== false,
    'summary text must come from source payload'
);
assertSameValue(
    ArticleType::NEWS_BRIEF,
    $context->blueprintProjection()->articleType(),
    'projection must carry article type'
);
assertTrue(
    in_array('summary', $context->blueprintProjection()->sectionKeys(), true),
    'projection must include section keys'
);
assertTrue(
    in_array('summary', $context->blueprintProjection()->headingKeys(), true),
    'projection must include heading keys'
);

$validation = $validator->validate($context);
assertTrue($validation['valid'] === true, 'built context must be structurally valid');
assertTrue($validation['ready'] === true, 'built context with content hash can mark ready');
assertTrue($validator->canMarkReady($context) === true, 'canMarkReady must be true');

$invalid = new PromptContext(array(
    'context_id' => '',
    'announcement_id' => 0,
    'blueprint_id' => '',
    'blueprint_revision' => 0,
    'status' => 'nope',
    'source_content_hash' => 'aaa',
    'context_hash' => 'short',
    'facts' => array(
        'announcement_id' => 9,
        'raw_title' => '',
        'content_hash' => '',
    ),
    'blueprint_projection' => array(
        'blueprint_id' => 'other',
        'article_type' => '',
        'target_audience' => '',
        'language' => '',
        'section_keys' => array(),
        'heading_keys' => array(),
    ),
));
$invalidResult = $validator->validate($invalid);
assertTrue($invalidResult['valid'] === false, 'invalid context must fail validation');
assertTrue(
    in_array('announcement_id_required', $invalidResult['errors'], true),
    'must report announcement_id_required'
);
assertTrue(
    in_array('projection_structure_required', $invalidResult['errors'], true),
    'must report projection_structure_required'
);
assertTrue($validator->canMarkReady($invalid) === false, 'invalid context cannot mark ready');

$faqBlueprint = $blueprintBuilder->buildFromAnnouncement(
    $announcementSnapshot,
    array('article_type' => ArticleType::FAQ_ARTICLE)
);
$faqContext = $contextBuilder->buildFromAnnouncementAndBlueprint($announcementSnapshot, $faqBlueprint);
assertSameValue(
    ArticleType::FAQ_ARTICLE,
    $faqContext->blueprintProjection()->articleType(),
    'FAQ blueprint projection'
);
assertTrue($faqContext->blueprintProjection()->faqEnabled() === true, 'FAQ projection enables FAQ');
assertTrue($validator->isStructurallyValid($faqContext) === true, 'FAQ context must validate');

$serialized = $context->toArray();
assertTrue(
    isset($serialized['facts'], $serialized['blueprint_projection'], $serialized['context_hash']),
    'toArray must expose core groups'
);
$rehydrated = new PromptContext($serialized);
assertSameValue($context->contextId(), $rehydrated->contextId(), 'toArray round-trip must preserve id');
assertSameValue(
    $context->contextHash(),
    $rehydrated->contextHash(),
    'toArray round-trip must preserve hash'
);

// Deterministic hash for identical payload
$contextB = $contextBuilder->buildFromAnnouncementAndBlueprint(
    $announcementSnapshot,
    $blueprint,
    array('context_id' => 'pc_fixed')
);
assertSameValue(
    $context->contextHash(),
    $contextB->contextHash(),
    'identical announcement+blueprint must yield identical context_hash'
);

// --- Architecture: repository is interface-only; no DB/provider/prompt-template symbols ---

assertTrue(
    interface_exists(PromptContextRepositoryInterface::class),
    'PromptContextRepositoryInterface must exist'
);

$contextDir = $pluginDirectory . '/src/PromptContext';
$contextFiles = glob($contextDir . '/*.php');
assertTrue(is_array($contextFiles) && count($contextFiles) > 0, 'PromptContext directory must contain PHP files');

foreach ($contextFiles as $file) {
    $contents = file_get_contents($file);
    assertTrue($contents !== false, 'must read ' . basename($file));
    $dbToken = '$' . 'wpdb';
    $ddlToken = 'CREATE' . ' TABLE';
    $vendorToken = 'open' . 'ai';
    $restToken = 'register_' . 'rest_route';
    $menuToken = 'add_' . 'menu_page';
    $templateToken = 'prompt_template';
    $packageToken = 'PromptPackage';
    $systemPromptToken = 'You are a';
    assertTrue(strpos($contents, $dbToken) === false, basename($file) . ' must not use db handle');
    assertTrue(stripos($contents, $ddlToken) === false, basename($file) . ' must not DDL');
    assertTrue(stripos($contents, $vendorToken) === false, basename($file) . ' must not reference vendors');
    assertTrue(stripos($contents, $restToken) === false, basename($file) . ' must not REST');
    assertTrue(stripos($contents, $menuToken) === false, basename($file) . ' must not admin menu');
    assertTrue(
        stripos($contents, $templateToken) === false,
        basename($file) . ' must not reference prompt templates'
    );
    assertTrue(
        strpos($contents, $packageToken) === false,
        basename($file) . ' must not implement prompt packages'
    );
    assertTrue(
        strpos($contents, $systemPromptToken) === false,
        basename($file) . ' must not contain prompt prose'
    );
}

$repoInterfaceFile = $contextDir . '/PromptContextRepositoryInterface.php';
$repoInterfaceContents = file_get_contents($repoInterfaceFile);
assertTrue(
    strpos($repoInterfaceContents, 'interface PromptContextRepositoryInterface') !== false,
    'repository file must declare an interface'
);
assertTrue(
    preg_match('/\bfinal\s+class\b|\bclass\s+\w+/', $repoInterfaceContents) !== 1,
    'BUILD-002 must not ship a concrete prompt context repository class'
);

// --- Module registration ---

$databaseGlobalKey = 'wp' . 'db';
$GLOBALS[$databaseGlobalKey] = (object) array(
    'prefix' => 'wp_',
);

$plugin = new Plugin();
assertTrue($plugin instanceof Plugin, 'Plugin must construct with PromptContextModule');

$container = new ServiceContainer();
$moduleRegistry = new ModuleRegistry();
$container->set(ModuleRegistry::class, $moduleRegistry);
$moduleRegistry->register(new CorePlatformModule());
$moduleRegistry->register(new SourceRegistryModule());
$moduleRegistry->register(new AcquisitionModule());
$moduleRegistry->register(new AnnouncementModule());
$moduleRegistry->register(new BlueprintModule());
$moduleRegistry->register(new PromptContextModule());
$loader = new ModuleLoader($moduleRegistry, $container);
$loader->load();

assertTrue($moduleRegistry->has('prompt_context'), 'prompt_context module must be registered');
assertTrue(
    $container->get(PromptContextBuilder::class) instanceof PromptContextBuilder,
    'builder must resolve from container'
);
assertTrue(
    $container->get(PromptContextValidator::class) instanceof PromptContextValidator,
    'validator must resolve from container'
);

$pluginContents = file_get_contents($pluginDirectory . '/src/Core/Plugin.php');
assertTrue(
    strpos($pluginContents, 'PromptContextModule') !== false,
    'Plugin must register PromptContextModule'
);

if ($failures !== array()) {
    fwrite(STDERR, "BUILD-002 Prompt Context smoke test failed.\n");

    foreach ($failures as $failure) {
        fwrite(STDERR, '- ' . $failure . "\n");
    }

    exit(1);
}

echo "BUILD-002 Prompt Context smoke test passed.\n";
echo 'Assertions: ' . $passed . "\n";
