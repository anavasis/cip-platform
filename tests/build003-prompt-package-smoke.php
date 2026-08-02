<?php

/**
 * BUILD-003 Prompt Package domain smoke / unit / architecture tests.
 *
 * No database, UI, AI providers, prompt text/rendering, templates body,
 * Generation Request, workflow, or publishing.
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
use StudyMentor\ContentEngine\Modules\AcquisitionModule;
use StudyMentor\ContentEngine\Modules\AnnouncementModule;
use StudyMentor\ContentEngine\Modules\BlueprintModule;
use StudyMentor\ContentEngine\Modules\CorePlatformModule;
use StudyMentor\ContentEngine\Modules\PromptContextModule;
use StudyMentor\ContentEngine\Modules\PromptPackageModule;
use StudyMentor\ContentEngine\Modules\SourceRegistryModule;
use StudyMentor\ContentEngine\PromptContext\PromptContextBuilder;
use StudyMentor\ContentEngine\PromptPackage\BlueprintReference;
use StudyMentor\ContentEngine\PromptPackage\PromptPackage;
use StudyMentor\ContentEngine\PromptPackage\PromptPackageBuilder;
use StudyMentor\ContentEngine\PromptPackage\PromptPackageRepositoryInterface;
use StudyMentor\ContentEngine\PromptPackage\PromptPackageStatus;
use StudyMentor\ContentEngine\PromptPackage\PromptPackageValidator;
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

assertTrue(PromptPackageStatus::isValid(PromptPackageStatus::DRAFT), 'draft status valid');
assertTrue(PromptPackageStatus::isValid(PromptPackageStatus::SEALED), 'sealed status valid');
assertTrue(PromptPackageStatus::isValid(PromptPackageStatus::SUPERSEDED), 'superseded status valid');
assertTrue(!PromptPackageStatus::isValid('published'), 'published is not a package status');

// --- Unit: builder + validator ---

$blueprintBuilder = new ContentBlueprintBuilder();
$contextBuilder = new PromptContextBuilder();
$packageBuilder = new PromptPackageBuilder();
$validator = new PromptPackageValidator();

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
    ),
);

$blueprint = $blueprintBuilder->buildFromAnnouncement($announcementSnapshot);
$context = $contextBuilder->buildFromAnnouncementAndBlueprint($announcementSnapshot, $blueprint);
$blueprintRef = $packageBuilder->blueprintReferenceFromContext($context);
$templateRef = new PromptTemplateReference(array(
    'template_id' => 'news_brief.v1',
    'template_version' => '1.0.0',
));

$package = $packageBuilder->buildFromContextAndBlueprint(
    $context,
    $blueprintRef,
    $templateRef
);

assertTrue($package instanceof PromptPackage, 'builder must return PromptPackage');
assertSameValue(42, $package->announcementId(), 'announcement id preserved');
assertSameValue($context->contextId(), $package->contextId(), 'context id bound');
assertSameValue($context->contextHash(), $package->contextHash(), 'context hash bound');
assertSameValue($blueprint->blueprintId(), $package->blueprintReference()->blueprintId(), 'blueprint id bound');
assertSameValue(PromptPackageStatus::SEALED, $package->status(), 'builder seals package');
assertTrue($package->packageId() !== '', 'package id generated');
assertTrue(strlen($package->packageHash()) === 64, 'package hash sha256 hex');
assertSameValue('news_brief.v1', $package->templateReference()->templateId(), 'template id bound');
assertSameValue('1.0.0', $package->templateReference()->templateVersion(), 'template version bound');

$validation = $validator->validate($package);
assertTrue($validation['valid'] === true, 'built package must be structurally valid');
assertTrue($validation['sealed'] === true, 'built package must be sealed');
assertTrue($validator->isSealed($package) === true, 'isSealed true');
assertTrue(
    $validator->packageHashMatchesBinding($package) === true,
    'package_hash must match binding payload'
);

// Deterministic hash
$packageB = $packageBuilder->buildFromContextAndBlueprint(
    $context,
    $blueprintRef,
    $templateRef,
    array('package_id' => 'pp_fixed')
);
assertSameValue(
    $package->packageHash(),
    $packageB->packageHash(),
    'identical binding must yield identical package_hash'
);

// Template version change => new hash
$packageC = $packageBuilder->buildFromContextAndBlueprint(
    $context,
    $blueprintRef,
    new PromptTemplateReference(array(
        'template_id' => 'news_brief.v1',
        'template_version' => '1.0.1',
    ))
);
assertTrue(
    $package->packageHash() !== $packageC->packageHash(),
    'template version change must change package_hash'
);

// Identity mismatch must throw
$mismatchThrown = false;
try {
    $packageBuilder->buildFromContextAndBlueprint(
        $context,
        new BlueprintReference(array(
            'blueprint_id' => $context->blueprintId(),
            'blueprint_revision' => $context->blueprintRevision(),
            'announcement_id' => 999,
        )),
        $templateRef
    );
} catch (\InvalidArgumentException $e) {
    $mismatchThrown = ($e->getMessage() === 'announcement_id_mismatch');
}
assertTrue($mismatchThrown, 'announcement_id mismatch must throw');

$blueprintMismatchThrown = false;
try {
    $packageBuilder->buildFromContextAndBlueprint(
        $context,
        new BlueprintReference(array(
            'blueprint_id' => 'bp_other',
            'blueprint_revision' => $context->blueprintRevision(),
            'announcement_id' => $context->announcementId(),
        )),
        $templateRef
    );
} catch (\InvalidArgumentException $e) {
    $blueprintMismatchThrown = ($e->getMessage() === 'blueprint_id_mismatch');
}
assertTrue($blueprintMismatchThrown, 'blueprint_id mismatch must throw');

$invalid = new PromptPackage(array(
    'package_id' => '',
    'announcement_id' => 0,
    'context_id' => '',
    'context_hash' => 'short',
    'status' => 'nope',
    'package_hash' => 'x',
    'blueprint_reference' => array(
        'blueprint_id' => '',
        'blueprint_revision' => 0,
        'announcement_id' => 9,
    ),
    'template_reference' => array(
        'template_id' => '',
        'template_version' => '',
    ),
));
$invalidResult = $validator->validate($invalid);
assertTrue($invalidResult['valid'] === false, 'invalid package must fail');
assertTrue(
    in_array('package_id_required', $invalidResult['errors'], true),
    'must report package_id_required'
);
assertTrue(
    in_array('template_id_required', $invalidResult['errors'], true),
    'must report template_id_required'
);
assertTrue($validator->isSealed($invalid) === false, 'invalid cannot be sealed');

$serialized = $package->toArray();
assertTrue(
    isset(
        $serialized['blueprint_reference'],
        $serialized['template_reference'],
        $serialized['package_hash']
    ),
    'toArray must expose core groups'
);
$rehydrated = new PromptPackage($serialized);
assertSameValue($package->packageId(), $rehydrated->packageId(), 'toArray round-trip id');
assertSameValue($package->packageHash(), $rehydrated->packageHash(), 'toArray round-trip hash');

// --- Architecture: repository interface-only; no DB/provider/prompt prose ---

assertTrue(
    interface_exists(PromptPackageRepositoryInterface::class),
    'PromptPackageRepositoryInterface must exist'
);

$packageDir = $pluginDirectory . '/src/PromptPackage';
$packageFiles = glob($packageDir . '/*.php');
assertTrue(is_array($packageFiles) && count($packageFiles) > 0, 'PromptPackage directory must contain PHP');

foreach ($packageFiles as $file) {
    $contents = file_get_contents($file);
    assertTrue($contents !== false, 'must read ' . basename($file));
    $dbToken = '$' . 'wpdb';
    $ddlToken = 'CREATE' . ' TABLE';
    $vendorToken = 'open' . 'ai';
    $restToken = 'register_' . 'rest_route';
    $menuToken = 'add_' . 'menu_page';
    $systemPromptToken = 'You are a';
    $renderToken = 'render_prompt';
    $genReqToken = 'GenerationRequest';
    assertTrue(strpos($contents, $dbToken) === false, basename($file) . ' must not use db handle');
    assertTrue(stripos($contents, $ddlToken) === false, basename($file) . ' must not DDL');
    assertTrue(stripos($contents, $vendorToken) === false, basename($file) . ' must not reference vendors');
    assertTrue(stripos($contents, $restToken) === false, basename($file) . ' must not REST');
    assertTrue(stripos($contents, $menuToken) === false, basename($file) . ' must not admin menu');
    assertTrue(
        strpos($contents, $systemPromptToken) === false,
        basename($file) . ' must not contain prompt prose'
    );
    assertTrue(
        stripos($contents, $renderToken) === false,
        basename($file) . ' must not render prompts'
    );
    assertTrue(
        strpos($contents, $genReqToken) === false,
        basename($file) . ' must not implement Generation Request'
    );
}

// Template reference is metadata only — no template body fields
$templateRefFile = $packageDir . '/PromptTemplateReference.php';
$templateRefContents = file_get_contents($templateRefFile);
assertTrue(
    strpos($templateRefContents, 'template_body') === false
        && strpos($templateRefContents, 'system_prompt') === false
        && strpos($templateRefContents, 'user_prompt') === false,
    'PromptTemplateReference must not carry prompt body fields'
);

$repoInterfaceFile = $packageDir . '/PromptPackageRepositoryInterface.php';
$repoInterfaceContents = file_get_contents($repoInterfaceFile);
assertTrue(
    strpos($repoInterfaceContents, 'interface PromptPackageRepositoryInterface') !== false,
    'repository file must declare an interface'
);
assertTrue(
    preg_match('/\bfinal\s+class\b|\bclass\s+\w+/', $repoInterfaceContents) !== 1,
    'BUILD-003 must not ship a concrete prompt package repository class'
);

// --- Module registration ---

$databaseGlobalKey = 'wp' . 'db';
$GLOBALS[$databaseGlobalKey] = (object) array(
    'prefix' => 'wp_',
);

$plugin = new Plugin();
assertTrue($plugin instanceof Plugin, 'Plugin must construct with PromptPackageModule');

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
$loader = new ModuleLoader($moduleRegistry, $container);
$loader->load();

assertTrue($moduleRegistry->has('prompt_package'), 'prompt_package module must be registered');
assertTrue(
    $container->get(PromptPackageBuilder::class) instanceof PromptPackageBuilder,
    'builder must resolve from container'
);
assertTrue(
    $container->get(PromptPackageValidator::class) instanceof PromptPackageValidator,
    'validator must resolve from container'
);

$pluginContents = file_get_contents($pluginDirectory . '/src/Core/Plugin.php');
assertTrue(
    strpos($pluginContents, 'PromptPackageModule') !== false,
    'Plugin must register PromptPackageModule'
);

if ($failures !== array()) {
    fwrite(STDERR, "BUILD-003 Prompt Package smoke test failed.\n");

    foreach ($failures as $failure) {
        fwrite(STDERR, '- ' . $failure . "\n");
    }

    exit(1);
}

echo "BUILD-003 Prompt Package smoke test passed.\n";
echo 'Assertions: ' . $passed . "\n";
