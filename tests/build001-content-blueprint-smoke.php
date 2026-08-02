<?php

/**
 * BUILD-001 Content Blueprint domain smoke / unit / architecture tests.
 *
 * No database, UI, AI, prompts, providers, workflow, or publishing.
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
use StudyMentor\ContentEngine\Blueprint\BlueprintStatus;
use StudyMentor\ContentEngine\Blueprint\ContentBlueprint;
use StudyMentor\ContentEngine\Blueprint\ContentBlueprintBuilder;
use StudyMentor\ContentEngine\Blueprint\ContentBlueprintRepositoryInterface;
use StudyMentor\ContentEngine\Blueprint\ContentBlueprintValidator;
use StudyMentor\ContentEngine\Blueprint\Tone;
use StudyMentor\ContentEngine\Core\ModuleLoader;
use StudyMentor\ContentEngine\Core\ModuleRegistry;
use StudyMentor\ContentEngine\Core\Plugin;
use StudyMentor\ContentEngine\Core\ServiceContainer;
use StudyMentor\ContentEngine\Modules\AcquisitionModule;
use StudyMentor\ContentEngine\Modules\AnnouncementModule;
use StudyMentor\ContentEngine\Modules\BlueprintModule;
use StudyMentor\ContentEngine\Modules\CorePlatformModule;
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

// --- Unit: enums ---

assertTrue(BlueprintStatus::isValid(BlueprintStatus::DRAFT), 'draft status valid');
assertTrue(BlueprintStatus::isValid(BlueprintStatus::READY), 'ready status valid');
assertTrue(!BlueprintStatus::isValid('published'), 'published is not a blueprint status');
assertTrue(ArticleType::isValid(ArticleType::NEWS_BRIEF), 'news_brief article type valid');
assertTrue(Tone::isValid(Tone::NEUTRAL), 'neutral tone valid');

// --- Unit: builder + validator ---

$builder = new ContentBlueprintBuilder();
$validator = new ContentBlueprintValidator();

$blueprint = $builder->buildFromAnnouncement(array(
    'announcement_id' => 42,
    'raw_title' => 'Scholarship deadline extended',
    'source_content_hash' => str_repeat('a', 64),
    'announcement_revision_no' => 1,
    'language' => 'el',
));

assertTrue($blueprint instanceof ContentBlueprint, 'builder must return ContentBlueprint');
assertSameValue(42, $blueprint->announcementId(), 'announcement id must be preserved');
assertSameValue(BlueprintStatus::DRAFT, $blueprint->status(), 'builder must create draft status');
assertSameValue(ArticleType::NEWS_BRIEF, $blueprint->articleType(), 'default article type is news_brief');
assertTrue($blueprint->blueprintId() !== '', 'blueprint id must be generated');
assertTrue(count($blueprint->requiredSections()) >= 1, 'preset must include required sections');
assertTrue(count($blueprint->headingHierarchy()) >= 1, 'preset must include headings');
assertSameValue('Scholarship deadline extended', $blueprint->titleCandidates()[0], 'title candidate from announcement');

$validation = $validator->validate($blueprint);
assertTrue($validation['valid'] === true, 'built blueprint must be structurally valid');
assertTrue($validation['ready'] === true, 'built blueprint with content hash can mark ready');
assertTrue($validator->canMarkReady($blueprint) === true, 'canMarkReady must be true');

$invalid = new ContentBlueprint(array(
    'blueprint_id' => '',
    'announcement_id' => 0,
    'status' => 'nope',
    'article_type' => 'unknown',
    'target_audience' => '',
    'language' => '',
    'tone' => 'loud',
    'target_length' => array('unit' => 'words', 'min' => 10, 'max' => 5, 'ideal' => 7),
    'required_sections' => array(),
    'heading_hierarchy' => array(),
    'source_content_hash' => '',
));
$invalidResult = $validator->validate($invalid);
assertTrue($invalidResult['valid'] === false, 'invalid blueprint must fail validation');
assertTrue(in_array('announcement_id_required', $invalidResult['errors'], true), 'must report announcement_id_required');
assertTrue(in_array('structure_required', $invalidResult['errors'], true), 'must report structure_required');
assertTrue($validator->canMarkReady($invalid) === false, 'invalid blueprint cannot mark ready');

$faqBlueprint = $builder->buildFromAnnouncement(
    array(
        'announcement_id' => 7,
        'raw_title' => 'FAQ source',
        'source_content_hash' => str_repeat('b', 64),
        'announcement_revision_no' => 2,
    ),
    array('article_type' => ArticleType::FAQ_ARTICLE)
);
assertSameValue(ArticleType::FAQ_ARTICLE, $faqBlueprint->articleType(), 'override article type');
assertTrue($faqBlueprint->faqRequirements()->enabled() === true, 'FAQ preset enables FAQ');
assertTrue($validator->isStructurallyValid($faqBlueprint) === true, 'FAQ blueprint must validate');

$serialized = $blueprint->toArray();
assertTrue(isset($serialized['required_sections'], $serialized['seo_constraints']), 'toArray must expose core groups');
$rehydrated = new ContentBlueprint($serialized);
assertSameValue($blueprint->blueprintId(), $rehydrated->blueprintId(), 'toArray round-trip must preserve id');

// --- Architecture: repository is interface-only; no DB symbols in Blueprint domain ---

assertTrue(
    interface_exists(ContentBlueprintRepositoryInterface::class),
    'ContentBlueprintRepositoryInterface must exist'
);

$blueprintDir = $pluginDirectory . '/src/Blueprint';
$blueprintFiles = glob($blueprintDir . '/*.php');
assertTrue(is_array($blueprintFiles) && count($blueprintFiles) > 0, 'Blueprint directory must contain PHP files');

foreach ($blueprintFiles as $file) {
    $contents = file_get_contents($file);
    assertTrue($contents !== false, 'must read ' . basename($file));
    $dbToken = '$' . 'wpdb';
    $ddlToken = 'CREATE' . ' TABLE';
    $vendorToken = 'open' . 'ai';
    $restToken = 'register_' . 'rest_route';
    $menuToken = 'add_' . 'menu_page';
    assertTrue(strpos($contents, $dbToken) === false, basename($file) . ' must not use db handle');
    assertTrue(stripos($contents, $ddlToken) === false, basename($file) . ' must not DDL');
    assertTrue(stripos($contents, $vendorToken) === false, basename($file) . ' must not reference vendors');
    assertTrue(stripos($contents, $restToken) === false, basename($file) . ' must not REST');
    assertTrue(stripos($contents, $menuToken) === false, basename($file) . ' must not admin menu');
}

$repoInterfaceFile = $blueprintDir . '/ContentBlueprintRepositoryInterface.php';
$repoInterfaceContents = file_get_contents($repoInterfaceFile);
assertTrue(
    strpos($repoInterfaceContents, 'interface ContentBlueprintRepositoryInterface') !== false,
    'repository file must declare an interface'
);
assertTrue(
    preg_match('/\bfinal\s+class\b|\bclass\s+\w+/', $repoInterfaceContents) !== 1,
    'BUILD-001 must not ship a concrete blueprint repository class'
);

// --- Module registration ---

$databaseGlobalKey = 'wp' . 'db';
$GLOBALS[$databaseGlobalKey] = (object) array(
    'prefix' => 'wp_',
);

$plugin = new Plugin();
assertTrue($plugin instanceof Plugin, 'Plugin must construct with BlueprintModule');

$container = new ServiceContainer();
$moduleRegistry = new ModuleRegistry();
$container->set(ModuleRegistry::class, $moduleRegistry);
$moduleRegistry->register(new CorePlatformModule());
$moduleRegistry->register(new SourceRegistryModule());
$moduleRegistry->register(new AcquisitionModule());
$moduleRegistry->register(new AnnouncementModule());
$moduleRegistry->register(new BlueprintModule());
$loader = new ModuleLoader($moduleRegistry, $container);
$loader->load();

assertTrue($moduleRegistry->has('blueprint'), 'blueprint module must be registered');
assertTrue(
    $container->get(ContentBlueprintBuilder::class) instanceof ContentBlueprintBuilder,
    'builder must resolve from container'
);
assertTrue(
    $container->get(ContentBlueprintValidator::class) instanceof ContentBlueprintValidator,
    'validator must resolve from container'
);

$pluginContents = file_get_contents($pluginDirectory . '/src/Core/Plugin.php');
assertTrue(strpos($pluginContents, 'BlueprintModule') !== false, 'Plugin must register BlueprintModule');

if ($failures !== array()) {
    fwrite(STDERR, "BUILD-001 Content Blueprint smoke test failed.\n");

    foreach ($failures as $failure) {
        fwrite(STDERR, '- ' . $failure . "\n");
    }

    exit(1);
}

echo "BUILD-001 Content Blueprint smoke test passed.\n";
echo 'Assertions: ' . $passed . "\n";
