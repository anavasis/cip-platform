<?php

namespace StudyMentor\ContentEngine\Core;

defined('ABSPATH') || exit;

final class SchemaManager
{
    const OPTION_NAME = 'smce_db_version';
    const SOURCES_SUFFIX = 'smce_sources';
    const SOURCE_ITEMS_SUFFIX = 'smce_source_items';

    private $wpdb;

    public function __construct($wpdbObject = null)
    {
        if ($wpdbObject !== null) {
            $this->wpdb = $wpdbObject;
            return;
        }

        global $wpdb;
        $this->wpdb = isset($wpdb) ? $wpdb : null;
    }

    public function migrate()
    {
        if (!$this->isDbObjectReady()) {
            return false;
        }

        $targetVersion = defined('SMCE_DB_VERSION') ? SMCE_DB_VERSION : '1.0.0';
        $currentVersion = $this->getCurrentVersion();

        if ($this->isVersionAtLeast($currentVersion, $targetVersion)) {
            if ($this->verifyRequiredTables()) {
                return true;
            }

            if (!$this->runSchemaCreation()) {
                return false;
            }

            return $this->verifyRequiredTables();
        }

        if (!$this->runSchemaCreation()) {
            return false;
        }

        if (!$this->verifyRequiredTables()) {
            return false;
        }

        update_option(self::OPTION_NAME, $targetVersion);
        $storedVersion = $this->getCurrentVersion();

        return $storedVersion === $targetVersion;
    }

    public function getCurrentVersion()
    {
        $value = get_option(self::OPTION_NAME, '');

        return is_string($value) ? $value : '';
    }

    public function getRequiredTables()
    {
        return array(
            $this->resolveTableName(self::SOURCES_SUFFIX),
            $this->resolveTableName(self::SOURCE_ITEMS_SUFFIX),
        );
    }

    public function verifyRequiredTables()
    {
        if (!$this->isDbObjectReady()) {
            return false;
        }

        foreach ($this->getRequiredTables() as $tableName) {
            $tableLike = $this->wpdb->esc_like($tableName);
            $query = $this->wpdb->prepare('SHOW TABLES LIKE %s', $tableLike);

            if (!is_string($query) || $query === '') {
                return false;
            }

            $foundTable = $this->wpdb->get_var($query);

            if ($foundTable !== $tableName) {
                return false;
            }
        }

        return true;
    }

    private function buildCreateTableDefinitions()
    {
        $sourcesTable = $this->resolveTableName(self::SOURCES_SUFFIX);
        $itemsTable = $this->resolveTableName(self::SOURCE_ITEMS_SUFFIX);
        $charsetCollate = $this->wpdb->get_charset_collate();

        return array(
            "CREATE TABLE {$sourcesTable} (
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
slug varchar(128) NOT NULL,
name varchar(191) NOT NULL,
source_type varchar(32) NOT NULL,
base_url text NULL,
feed_url text NOT NULL,
feed_url_hash char(64) NOT NULL,
allowed_domains longtext NOT NULL,
parser_profile varchar(64) DEFAULT NULL,
enabled tinyint(1) NOT NULL DEFAULT 0,
manual_only tinyint(1) NOT NULL DEFAULT 1,
created_at_utc datetime NOT NULL,
updated_at_utc datetime NOT NULL,
PRIMARY KEY  (id),
UNIQUE KEY slug_uq (slug),
UNIQUE KEY feed_hash_uq (feed_url_hash)
) {$charsetCollate};",
            "CREATE TABLE {$itemsTable} (
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
source_id bigint(20) unsigned NOT NULL,
identity_hash char(64) NOT NULL,
identity_basis varchar(24) NOT NULL,
source_guid text NULL,
canonical_url text NULL,
source_published_at_utc datetime DEFAULT NULL,
raw_title text NULL,
content_hash char(64) DEFAULT NULL,
raw_payload longtext NOT NULL,
revision_no int(10) unsigned NOT NULL DEFAULT 1,
first_seen_at_utc datetime NOT NULL,
last_seen_at_utc datetime NOT NULL,
created_at_utc datetime NOT NULL,
updated_at_utc datetime NOT NULL,
PRIMARY KEY  (id),
UNIQUE KEY src_identity_uq (source_id,identity_hash),
KEY src_pub_idx (source_id,source_published_at_utc),
KEY src_seen_idx (source_id,last_seen_at_utc)
) {$charsetCollate};",
        );
    }

    private function resolveTableName($suffix)
    {
        return $this->wpdb->prefix . $suffix;
    }

    private function runSchemaCreation()
    {
        if (!defined('ABSPATH')) {
            return false;
        }

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        if (!function_exists('dbDelta')) {
            return false;
        }

        foreach ($this->buildCreateTableDefinitions() as $sql) {
            dbDelta($sql);
        }

        return true;
    }

    private function isVersionAtLeast($currentVersion, $targetVersion)
    {
        if ($currentVersion === '') {
            return false;
        }

        return version_compare($currentVersion, $targetVersion, '>=');
    }

    private function isDbObjectReady()
    {
        return is_object($this->wpdb)
            && isset($this->wpdb->prefix)
            && method_exists($this->wpdb, 'prepare')
            && method_exists($this->wpdb, 'esc_like')
            && method_exists($this->wpdb, 'get_charset_collate');
    }
}
