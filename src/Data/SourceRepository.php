<?php

namespace StudyMentor\ContentEngine\Data;

defined('ABSPATH') || exit;

final class SourceRepository
{
    private $wpdb;
    private $tableName;

    public function __construct($wpdbObject)
    {
        $this->wpdb = $wpdbObject;
        $this->tableName = $this->wpdb->prefix . 'smce_sources';
    }

    public function findAll()
    {
        $query = 'SELECT id, slug, name, source_type, base_url, feed_url, feed_url_hash, '
            . 'allowed_domains, parser_profile, enabled, manual_only, '
            . 'created_at_utc, updated_at_utc FROM ' . $this->tableName . ' ORDER BY id ASC';

        $results = $this->wpdb->get_results($query, ARRAY_A);

        return is_array($results) ? $results : array();
    }

    public function findById($id)
    {
        $sourceId = (int) $id;

        if ($sourceId <= 0) {
            return null;
        }

        $query = $this->wpdb->prepare(
            'SELECT id, slug, name, source_type, base_url, feed_url, feed_url_hash, '
            . 'allowed_domains, parser_profile, enabled, manual_only, '
            . 'created_at_utc, updated_at_utc FROM ' . $this->tableName . ' WHERE id = %d LIMIT 1',
            $sourceId
        );

        $row = $this->wpdb->get_row($query, ARRAY_A);

        return is_array($row) ? $row : null;
    }

    public function slugExists($slug, $excludeId = 0)
    {
        $exclude = (int) $excludeId;

        if ($exclude > 0) {
            $query = $this->wpdb->prepare(
                'SELECT id FROM ' . $this->tableName . ' WHERE slug = %s AND id <> %d LIMIT 1',
                $slug,
                $exclude
            );
        } else {
            $query = $this->wpdb->prepare(
                'SELECT id FROM ' . $this->tableName . ' WHERE slug = %s LIMIT 1',
                $slug
            );
        }

        $foundId = $this->wpdb->get_var($query);

        return $foundId !== null && $foundId !== '';
    }

    public function feedHashExists($hash, $excludeId = 0)
    {
        $exclude = (int) $excludeId;

        if ($exclude > 0) {
            $query = $this->wpdb->prepare(
                'SELECT id FROM ' . $this->tableName . ' WHERE feed_url_hash = %s AND id <> %d LIMIT 1',
                $hash,
                $exclude
            );
        } else {
            $query = $this->wpdb->prepare(
                'SELECT id FROM ' . $this->tableName . ' WHERE feed_url_hash = %s LIMIT 1',
                $hash
            );
        }

        $foundId = $this->wpdb->get_var($query);

        return $foundId !== null && $foundId !== '';
    }

    public function insert(array $data)
    {
        $formats = array(
            'slug' => '%s',
            'name' => '%s',
            'source_type' => '%s',
            'base_url' => '%s',
            'feed_url' => '%s',
            'feed_url_hash' => '%s',
            'allowed_domains' => '%s',
            'parser_profile' => '%s',
            'enabled' => '%d',
            'manual_only' => '%d',
            'created_at_utc' => '%s',
            'updated_at_utc' => '%s',
        );

        $insertData = array();
        $insertFormats = array();

        foreach ($formats as $column => $format) {
            if (!array_key_exists($column, $data)) {
                return false;
            }

            $insertData[$column] = $data[$column];
            $insertFormats[] = $format;
        }

        $result = $this->wpdb->insert(
            $this->tableName,
            $insertData,
            $insertFormats
        );

        if ($result === false) {
            return false;
        }

        return (int) $this->wpdb->insert_id;
    }

    public function update($id, array $data)
    {
        $sourceId = (int) $id;

        if ($sourceId <= 0 || $data === array()) {
            return false;
        }

        $allowedColumns = array(
            'name' => '%s',
            'source_type' => '%s',
            'base_url' => '%s',
            'feed_url' => '%s',
            'feed_url_hash' => '%s',
            'allowed_domains' => '%s',
            'parser_profile' => '%s',
            'manual_only' => '%d',
            'updated_at_utc' => '%s',
        );

        $updateData = array();
        $updateFormats = array();

        foreach ($data as $column => $value) {
            if (!isset($allowedColumns[$column])) {
                continue;
            }

            $updateData[$column] = $value;
            $updateFormats[] = $allowedColumns[$column];
        }

        if ($updateData === array()) {
            return false;
        }

        $result = $this->wpdb->update(
            $this->tableName,
            $updateData,
            array('id' => $sourceId),
            $updateFormats,
            array('%d')
        );

        return $result !== false;
    }

    public function setEnabled($id, $enabled)
    {
        $sourceId = (int) $id;
        $enabledValue = (int) $enabled;

        if ($sourceId <= 0 || ($enabledValue !== 0 && $enabledValue !== 1)) {
            return false;
        }

        $utcNow = current_time('mysql', true);

        $result = $this->wpdb->update(
            $this->tableName,
            array(
                'enabled' => $enabledValue,
                'updated_at_utc' => $utcNow,
            ),
            array('id' => $sourceId),
            array('%d', '%s'),
            array('%d')
        );

        return $result !== false;
    }
}
