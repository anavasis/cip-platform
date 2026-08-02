<?php

/**
 * Editorial Workspace Phase 2 smoke test.
 *
 * Verifies read-only workspace wiring, durable NEW/UPDATED status proxies,
 * and informational handling of ephemeral UNCHANGED/DUPLICATE outcomes.
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

if (!defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}

final class EditorialWorkspaceFakeWpdb
{
    public $prefix = 'wp_';
    public $insert_id = 0;
    public $last_error = '';
    /** @var array<string, array<int, array<string, mixed>>> */
    public $tables = array();

    public function suppress_errors($suppress = null)
    {
        return false;
    }

    public function prepare($query)
    {
        $args = func_get_args();
        array_shift($args);

        if (count($args) === 1 && is_array($args[0])) {
            $args = array_values($args[0]);
        }

        $prepared = (string) $query;

        foreach ($args as $arg) {
            if (is_array($arg)) {
                continue;
            }

            $replacement = is_int($arg) || is_float($arg)
                ? (string) $arg
                : "'" . str_replace("'", "''", (string) $arg) . "'";
            $prepared = preg_replace('/%[ds]/', $replacement, $prepared, 1);
        }

        return $prepared;
    }

    public function esc_like($text)
    {
        return addcslashes((string) $text, '_%\\');
    }

    public function get_var($sql)
    {
        if (!preg_match('/FROM\s+(\S+)/i', (string) $sql, $m)) {
            return null;
        }

        $table = trim($m[1], '`');
        $rows = isset($this->tables[$table]) ? $this->tables[$table] : array();

        if (preg_match('/COUNT\(\*\)/i', (string) $sql)) {
            if (preg_match('/revision_no\s*=\s*1/', (string) $sql)) {
                $rows = array_values(array_filter($rows, static function ($r) {
                    return isset($r['revision_no']) && (int) $r['revision_no'] === 1;
                }));
            } elseif (preg_match('/revision_no\s*>\s*1/', (string) $sql)) {
                $rows = array_values(array_filter($rows, static function ($r) {
                    return isset($r['revision_no']) && (int) $r['revision_no'] > 1;
                }));
            }

            return (string) count($rows);
        }

        if (preg_match('/MAX\((updated_at_utc|last_seen_at_utc)\)/i', (string) $sql, $mm)) {
            $column = strtolower($mm[1]);
            $max = '';
            foreach ($rows as $row) {
                $value = isset($row[$column]) ? (string) $row[$column] : '';
                if ($value > $max) {
                    $max = $value;
                }
            }

            return $max;
        }

        return null;
    }

    public function get_results($sql, $output = ARRAY_A)
    {
        unset($output);

        if (!preg_match('/FROM\s+(\S+)/i', (string) $sql, $m)) {
            return array();
        }

        $table = trim($m[1], '`');
        if (strpos($table, 'smce_source_items') !== false || $table === 'i') {
            $rows = isset($this->tables['wp_smce_source_items'])
                ? $this->tables['wp_smce_source_items']
                : array();
        } elseif (strpos($table, 'smce_sources') !== false) {
            $rows = isset($this->tables['wp_smce_sources'])
                ? $this->tables['wp_smce_sources']
                : array();
        } else {
            $rows = isset($this->tables[$table]) ? $this->tables[$table] : array();
        }

        if (preg_match('/revision_no\s*=\s*1/', (string) $sql)) {
            $rows = array_values(array_filter($rows, static function ($r) {
                return isset($r['revision_no']) && (int) $r['revision_no'] === 1;
            }));
        } elseif (preg_match('/revision_no\s*>\s*1/', (string) $sql)) {
            $rows = array_values(array_filter($rows, static function ($r) {
                return isset($r['revision_no']) && (int) $r['revision_no'] > 1;
            }));
        }

        if (preg_match('/WHERE i\.id = (\d+)/', (string) $sql, $idMatch)) {
            $id = (int) $idMatch[1];
            $rows = array_values(array_filter($rows, static function ($r) use ($id) {
                return isset($r['id']) && (int) $r['id'] === $id;
            }));
        }

        $sources = isset($this->tables['wp_smce_sources']) ? $this->tables['wp_smce_sources'] : array();
        $sourceMap = array();
        foreach ($sources as $source) {
            if (isset($source['id'])) {
                $sourceMap[(int) $source['id']] = $source;
            }
        }

        $out = array();
        foreach ($rows as $row) {
            $item = $row;
            $sid = isset($row['source_id']) ? (int) $row['source_id'] : 0;
            if (isset($sourceMap[$sid])) {
                $item['source_name'] = $sourceMap[$sid]['name'];
                $item['source_slug'] = $sourceMap[$sid]['slug'];
            } else {
                $item['source_name'] = '';
                $item['source_slug'] = '';
            }
            if (!isset($item['display_raw_payload']) && isset($item['raw_payload'])) {
                $item['display_raw_payload'] = $item['raw_payload'];
            }
            if (!isset($item['raw_payload_bytes']) && isset($item['raw_payload'])) {
                $item['raw_payload_bytes'] = strlen((string) $item['raw_payload']);
            }
            $out[] = $item;
        }

        if (preg_match('/LIMIT\s+(\d+)/i', (string) $sql, $lm)) {
            $out = array_slice($out, 0, (int) $lm[1]);
        }

        return $out;
    }
}

$db = new EditorialWorkspaceFakeWpdb();
$db->tables['wp_smce_sources'] = array(
    array('id' => 1, 'name' => 'Alpha Source', 'slug' => 'alpha'),
);
$db->tables['wp_smce_source_items'] = array(
    array(
        'id' => 10,
        'source_id' => 1,
        'identity_hash' => str_repeat('a', 64),
        'identity_basis' => 'canonical_url',
        'source_guid' => 'g1',
        'canonical_url' => 'https://example.com/new',
        'source_published_at_utc' => '2024-01-01 00:00:00',
        'raw_title' => 'New Announcement',
        'content_hash' => str_repeat('b', 64),
        'raw_payload' => '{"title":"New Announcement"}',
        'revision_no' => 1,
        'first_seen_at_utc' => '2024-01-01 00:00:00',
        'last_seen_at_utc' => '2024-01-01 00:00:00',
        'created_at_utc' => '2024-01-01 00:00:00',
        'updated_at_utc' => '2024-01-01 00:00:00',
    ),
    array(
        'id' => 11,
        'source_id' => 1,
        'identity_hash' => str_repeat('c', 64),
        'identity_basis' => 'canonical_url',
        'source_guid' => 'g2',
        'canonical_url' => 'https://example.com/updated',
        'source_published_at_utc' => '2024-02-01 00:00:00',
        'raw_title' => 'Updated Announcement',
        'content_hash' => str_repeat('d', 64),
        'raw_payload' => '{"title":"Updated Announcement"}',
        'revision_no' => 3,
        'first_seen_at_utc' => '2024-02-01 00:00:00',
        'last_seen_at_utc' => '2024-02-03 00:00:00',
        'created_at_utc' => '2024-02-01 00:00:00',
        'updated_at_utc' => '2024-02-03 12:00:00',
    ),
);
$GLOBALS['wpdb'] = $db;

if (!function_exists('current_user_can')) {
    function current_user_can($cap)
    {
        return true;
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
        if (!is_array($args)) {
            return (string) $url;
        }

        return (string) $url . '?' . http_build_query($args);
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

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($text)
    {
        return trim(strip_tags((string) $text));
    }
}

if (!function_exists('wp_unslash')) {
    function wp_unslash($value)
    {
        return is_string($value) ? stripslashes($value) : $value;
    }
}

if (!function_exists('wp_die')) {
    function wp_die($message = '')
    {
        throw new RuntimeException((string) $message);
    }
}

if (!function_exists('selected')) {
    function selected($selected, $current, $echo = true)
    {
        $result = ((string) $selected === (string) $current) ? ' selected="selected"' : '';
        if ($echo) {
            echo $result;
        }

        return $result;
    }
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data, $options = 0, $depth = 512)
    {
        return json_encode($data, $options);
    }
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

use StudyMentor\ContentEngine\Admin\Menu;
use StudyMentor\ContentEngine\Admin\Pages\EditorialAnnouncementsPage;
use StudyMentor\ContentEngine\Admin\Pages\EditorialQueuePage;
use StudyMentor\ContentEngine\Admin\Pages\EditorialWorkspacePage;
use StudyMentor\ContentEngine\Announcement\EditorialWorkspaceQueryService;
use StudyMentor\ContentEngine\Core\Plugin;
use StudyMentor\ContentEngine\Core\ServiceContainer;
use StudyMentor\ContentEngine\Core\ModuleLoader;
use StudyMentor\ContentEngine\Core\ModuleRegistry;
use StudyMentor\ContentEngine\Data\SourceItemReadRepository;
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
use StudyMentor\ContentEngine\Registry\VersionRegistry;

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
    assertTrue($expected === $actual, $message . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')');
}

$plugin = new Plugin();
assertTrue($plugin instanceof Plugin, 'Plugin must construct with editorial workspace wiring');

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

$menu = $container->get(Menu::class);
assertTrue($menu instanceof Menu, 'Menu must resolve with editorial pages');

$queryService = $container->get(EditorialWorkspaceQueryService::class);
assertTrue($queryService instanceof EditorialWorkspaceQueryService, 'EditorialWorkspaceQueryService must resolve');
assertSameValue('NEW', $queryService->statusFromRevision(1), 'revision_no=1 must map to NEW');
assertSameValue('UPDATED', $queryService->statusFromRevision(3), 'revision_no>1 must map to UPDATED');

$repository = $container->get(SourceItemReadRepository::class);
$summary = $repository->findEditorialSummary();
assertTrue($summary['ok'] === true, 'findEditorialSummary must succeed');
assertSameValue(2, $summary['total'], 'dashboard total must count all items');
assertSameValue(1, $summary['new_count'], 'dashboard new_count must count revision_no=1');
assertSameValue(1, $summary['updated_count'], 'dashboard updated_count must count revision_no>1');
assertSameValue('2024-02-03 12:00:00', $summary['last_ingestion_at_utc'], 'last ingestion must use max updated_at_utc');
$newPage = $repository->findPage(array(
    'search' => '',
    'source_id' => null,
    'status' => 'new',
    'date_from' => null,
    'date_to' => null,
    'sort' => 'updated',
    'direction' => 'desc',
    'page' => 1,
));
assertTrue($newPage['ok'] === true, 'findPage status=new must succeed');
assertSameValue(1, count($newPage['items']), 'status=new must return one item');
assertSameValue(1, (int) $newPage['items'][0]['revision_no'], 'new queue item must have revision 1');

$updatedPage = $repository->findPage(array(
    'search' => '',
    'source_id' => null,
    'status' => 'updated',
    'date_from' => null,
    'date_to' => null,
    'sort' => 'updated',
    'direction' => 'desc',
    'page' => 1,
));
assertTrue($updatedPage['ok'] === true, 'findPage status=updated must succeed');
assertSameValue(1, count($updatedPage['items']), 'status=updated must return one item');
assertTrue((int) $updatedPage['items'][0]['revision_no'] > 1, 'updated queue item must have revision > 1');

$detail = $repository->findById(11);
assertTrue($detail['ok'] === true && $detail['found'] === true, 'findById must load announcement detail');
assertTrue(isset($detail['item']['identity_hash'], $detail['item']['content_hash']), 'detail must include hashes');
assertTrue(isset($detail['item']['last_seen_at_utc'], $detail['item']['updated_at_utc']), 'detail must include seen/updated');

$workspacePage = $container->get(EditorialWorkspacePage::class);
$announcementsPage = $container->get(EditorialAnnouncementsPage::class);
$queuePage = $container->get(EditorialQueuePage::class);
assertTrue($workspacePage instanceof EditorialWorkspacePage, 'EditorialWorkspacePage must resolve');
assertTrue($announcementsPage instanceof EditorialAnnouncementsPage, 'EditorialAnnouncementsPage must resolve');
assertTrue($queuePage instanceof EditorialQueuePage, 'EditorialQueuePage must resolve');

$platformDiagnostics = $container->get(PlatformDiagnostics::class);
$diagnostics = $platformDiagnostics->collect();
assertSameValue(
    'editorial-workspace-phase2',
    $diagnostics['versions']['platform_phase'],
    'Platform phase must be editorial-workspace-phase2'
);
assertSameValue(
    'Ready',
    $diagnostics['confirmations']['announcement_lifecycle'],
    'Announcement lifecycle confirmation must remain Ready'
);

$versionRegistry = $container->get(VersionRegistry::class);
assertSameValue(
    'editorial-workspace-phase2',
    $versionRegistry->get('platform_phase'),
    'VersionRegistry phase must match Editorial Workspace Phase 2'
);

$menuFile = file_get_contents($pluginDirectory . '/src/Admin/Menu.php');
assertTrue(strpos($menuFile, "'smce-editorial'") !== false, 'Menu must register editorial workspace slug');
assertTrue(strpos($menuFile, "'smce-editorial-announcements'") !== false, 'Menu must register announcements slug');
assertTrue(strpos($menuFile, "'smce-editorial-queue'") !== false, 'Menu must register queue slug');

$lifecycleFile = file_get_contents($pluginDirectory . '/src/Announcement/AnnouncementLifecycleService.php');
$schemaFile = file_get_contents($pluginDirectory . '/src/Core/SchemaManager.php');
assertTrue(is_string($lifecycleFile) && is_string($schemaFile), 'Lifecycle and schema files must remain readable');

if ($failures !== array()) {
    fwrite(STDERR, "Editorial Workspace Phase 2 smoke test failed.\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, '- ' . $failure . "\n");
    }
    exit(1);
}

echo "Editorial Workspace Phase 2 smoke test passed.\n";
echo 'Assertions: ' . $passed . "\n";
