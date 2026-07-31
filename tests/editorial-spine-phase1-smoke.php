<?php

/**
 * Editorial Spine Phase 1 smoke test.
 *
 * Exercises announcement identity, extraction, lifecycle decisions, and
 * editorial ingestion wiring without WordPress boot or network I/O.
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

final class EditorialSpineFakeWpdb
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
}

$GLOBALS['wpdb'] = new EditorialSpineFakeWpdb();

require_once $pluginDirectory . '/src/Core/Autoloader.php';

use StudyMentor\ContentEngine\Admin\SourceCheckService;
use StudyMentor\ContentEngine\Announcement\AnnouncementCandidate;
use StudyMentor\ContentEngine\Announcement\AnnouncementIdentityService;
use StudyMentor\ContentEngine\Announcement\AnnouncementItemExtractor;
use StudyMentor\ContentEngine\Announcement\AnnouncementLifecycleService;
use StudyMentor\ContentEngine\Announcement\EditorialIngestionService;
use StudyMentor\ContentEngine\Announcement\LifecycleOutcome;
use StudyMentor\ContentEngine\Core\ModuleLoader;
use StudyMentor\ContentEngine\Core\ModuleRegistry;
use StudyMentor\ContentEngine\Core\Plugin;
use StudyMentor\ContentEngine\Core\ServiceContainer;
use StudyMentor\ContentEngine\Data\SourceItemRepository;
use StudyMentor\ContentEngine\Fingerprint\FingerprintService;
use StudyMentor\ContentEngine\Modules\AcquisitionModule;
use StudyMentor\ContentEngine\Modules\AnnouncementModule;
use StudyMentor\ContentEngine\Modules\CorePlatformModule;
use StudyMentor\ContentEngine\Modules\SourceRegistryModule;
use StudyMentor\ContentEngine\Platform\PlatformDiagnostics;
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

// --- Composition root ---

$plugin = new Plugin();
assertInstance(Plugin::class, $plugin, 'Plugin must construct with announcement module registered');

$container = new ServiceContainer();
$moduleRegistry = new ModuleRegistry();
$container->set(ModuleRegistry::class, $moduleRegistry);
$moduleRegistry->register(new CorePlatformModule());
$moduleRegistry->register(new SourceRegistryModule());
$moduleRegistry->register(new AcquisitionModule());
$moduleRegistry->register(new AnnouncementModule());

$moduleLoader = new ModuleLoader($moduleRegistry, $container);
$moduleLoader->load();
assertSameValue(ModuleLoader::STATE_LOADED, $moduleLoader->state(), 'ModuleLoader must reach loaded state');
assertTrue($moduleRegistry->has('announcement'), 'announcement module must be registered');

$ingestion = $container->get(EditorialIngestionService::class);
$lifecycle = $container->get(AnnouncementLifecycleService::class);
$identity = $container->get(AnnouncementIdentityService::class);
$extractor = $container->get(AnnouncementItemExtractor::class);

assertInstance(EditorialIngestionService::class, $ingestion, 'EditorialIngestionService must resolve');
assertInstance(AnnouncementLifecycleService::class, $lifecycle, 'AnnouncementLifecycleService must resolve');

// --- Item identity distinct from feed fingerprint ---

$itemHash = $identity->identityHash('https://example.com/a/1');
$feedFingerprint = (new FingerprintService())->fingerprint('<rss></rss>', 'https://example.com/a/1', 'src-1');
assertTrue($itemHash !== '', 'Item identity hash must be non-empty');
assertTrue(
    $itemHash !== $feedFingerprint['identity_hash'],
    'Item identity must differ from feed-level FingerprintService identity'
);

// --- Full-item extraction beyond preview limit ---

$rssItems = '';

for ($i = 1; $i <= 7; $i++) {
    $rssItems .= '<item><title>Item ' . $i . '</title>'
        . '<link>https://example.com/item-' . $i . '</link>'
        . '<guid>https://example.com/item-' . $i . '</guid>'
        . '<pubDate>Mon, 01 Jan 2024 00:00:0' . $i . ' +0000</pubDate></item>';
}

$rssBody = '<?xml version="1.0"?><rss version="2.0"><channel><title>Feed</title>'
    . $rssItems
    . '</channel></rss>';
$extracted = $extractor->extract($rssBody, 42, 'rss', '');
assertTrue($extracted['success'] === true, 'RSS extraction must succeed');
assertSameValue(7, count($extracted['candidates']), 'Extractor must return all RSS items, not preview limit');

// --- Lifecycle: new → unchanged → updated → duplicate ---

$candidateA = new AnnouncementCandidate(array(
    'source_id' => 7,
    'title' => 'Alpha',
    'canonical_url' => 'https://example.com/alpha',
    'source_guid' => 'https://example.com/alpha',
    'published_at_utc' => '2024-01-01 00:00:00',
    'raw_payload' => array('title' => 'Alpha'),
));

$first = $lifecycle->apply(array($candidateA));
assertTrue($first->success() === true, 'First lifecycle apply must succeed');
assertSameValue(1, $first->newCount(), 'First apply must classify new');
assertSameValue(LifecycleOutcome::NEW_ITEM, $first->decisions()[0]->outcome(), 'First decision must be new');
assertSameValue(1, $first->decisions()[0]->revisionNo(), 'New item revision must be 1');

$second = $lifecycle->apply(array($candidateA));
assertSameValue(1, $second->unchangedCount(), 'Same content must be unchanged');
assertSameValue(LifecycleOutcome::UNCHANGED, $second->decisions()[0]->outcome(), 'Second decision must be unchanged');

$candidateAUpdated = new AnnouncementCandidate(array(
    'source_id' => 7,
    'title' => 'Alpha Revised',
    'canonical_url' => 'https://example.com/alpha',
    'source_guid' => 'https://example.com/alpha',
    'published_at_utc' => '2024-01-02 00:00:00',
    'raw_payload' => array('title' => 'Alpha Revised'),
));
$third = $lifecycle->apply(array($candidateAUpdated));
assertSameValue(1, $third->updatedCount(), 'Changed content must be updated');
assertSameValue(LifecycleOutcome::UPDATED, $third->decisions()[0]->outcome(), 'Third decision must be updated');
assertSameValue(2, $third->decisions()[0]->revisionNo(), 'Updated revision must increment');

$duplicateBatch = $lifecycle->apply(array($candidateAUpdated, $candidateAUpdated));
assertSameValue(1, $duplicateBatch->unchangedCount(), 'Duplicate batch first item may be unchanged');
assertSameValue(1, $duplicateBatch->duplicateCount(), 'Duplicate batch second item must be duplicate');
assertSameValue(
    LifecycleOutcome::DUPLICATE,
    $duplicateBatch->decisions()[1]->outcome(),
    'Second batch decision must be duplicate'
);

// --- Editorial ingestion pre-acquire path ---

$rejected = $ingestion->ingestFromSource(0);
assertTrue($rejected->success() === false, 'ingestFromSource(0) must fail');
assertSameValue('invalid_source_id', $rejected->errorCode(), 'ingestFromSource(0) must return invalid_source_id');

$candidateIngest = $ingestion->ingestCandidates(array(
    new AnnouncementCandidate(array(
        'source_id' => 9,
        'title' => 'Beta',
        'canonical_url' => 'https://example.com/beta',
        'source_guid' => '',
        'published_at_utc' => '2024-02-01 00:00:00',
        'raw_payload' => array('title' => 'Beta'),
    )),
));
assertTrue($candidateIngest->success() === true, 'ingestCandidates must succeed');
assertSameValue(1, $candidateIngest->newCount(), 'ingestCandidates must create new announcement');

// --- Diagnostics + phase ---

$platformDiagnostics = $container->get(PlatformDiagnostics::class);
$diagnostics = $platformDiagnostics->collect();
assertSameValue(
    'editorial-workspace-phase2',
    $diagnostics['versions']['platform_phase'],
    'Platform phase must match Editorial Spine Phase 1'
);
assertTrue(in_array('announcement', $diagnostics['module_ids'], true), 'Diagnostics must include announcement module');
assertSameValue(
    'Ready',
    $diagnostics['confirmations']['announcement_lifecycle'],
    'Announcement lifecycle confirmation must be Ready'
);
assertTrue(
    isset($diagnostics['announcement_lifecycle']['last_batch'])
    && is_array($diagnostics['announcement_lifecycle']['last_batch']),
    'Diagnostics must expose last lifecycle batch'
);

$versionRegistry = $container->get(VersionRegistry::class);
assertSameValue(
    'editorial-workspace-phase2',
    $versionRegistry->get('platform_phase'),
    'VersionRegistry phase must match Editorial Spine Phase 1'
);

// --- Source Check remains ungated ---

$sourceCheckService = $container->get(SourceCheckService::class);
$invalidCheck = $sourceCheckService->check(0);
assertSameValue(false, $invalidCheck['success'], 'SourceCheckService::check(0) must fail');
assertSameValue('invalid_id', $invalidCheck['error_code'], 'SourceCheckService must remain ungated');

$repository = $container->get(SourceItemRepository::class);
assertInstance(SourceItemRepository::class, $repository, 'SourceItemRepository must resolve');

if ($failures !== array()) {
    fwrite(STDERR, "Editorial Spine Phase 1 smoke test failed.\n");

    foreach ($failures as $failure) {
        fwrite(STDERR, '- ' . $failure . "\n");
    }

    exit(1);
}

echo "Editorial Spine Phase 1 smoke test passed.\n";
echo 'Assertions: ' . $passed . "\n";
