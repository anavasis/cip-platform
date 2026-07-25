<?php

namespace StudyMentor\ContentEngine\Data;

defined('ABSPATH') || exit;

final class SourceItemRepository
{
    private $wpdb;
    private $tableName;

    public function __construct($wpdbObject)
    {
        $this->wpdb = $wpdbObject;
        $this->tableName = $this->wpdb->prefix . 'smce_source_items';
    }

    public function insert(array $data)
    {
        $formats = array(
            'source_id' => '%d',
            'identity_hash' => '%s',
            'identity_basis' => '%s',
            'source_guid' => '%s',
            'canonical_url' => '%s',
            'source_published_at_utc' => '%s',
            'raw_title' => '%s',
            'content_hash' => '%s',
            'raw_payload' => '%s',
            'revision_no' => '%d',
            'first_seen_at_utc' => '%s',
            'last_seen_at_utc' => '%s',
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

        return $result !== false;
    }

    public function existsBySourceAndIdentityHash(int $sourceId, string $identityHash): bool
    {
        if ($sourceId <= 0 || $identityHash === '') {
            return false;
        }

        $query = $this->wpdb->prepare(
            'SELECT id FROM ' . $this->tableName . ' WHERE source_id = %d AND identity_hash = %s LIMIT 1',
            $sourceId,
            $identityHash
        );

        $foundId = $this->wpdb->get_var($query);

        return $foundId !== null && $foundId !== '';
    }
}
