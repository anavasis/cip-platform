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
        return $this->findBySourceAndIdentityHash($sourceId, $identityHash) !== null;
    }

    /**
     * @param int $sourceId
     * @param string $identityHash
     * @return array<string, mixed>|null
     */
    public function findBySourceAndIdentityHash($sourceId, $identityHash)
    {
        $normalizedSourceId = (int) $sourceId;
        $normalizedHash = (string) $identityHash;

        if ($normalizedSourceId <= 0 || $normalizedHash === '') {
            return null;
        }

        $query = $this->wpdb->prepare(
            'SELECT id, source_id, identity_hash, identity_basis, source_guid, canonical_url, '
            . 'source_published_at_utc, raw_title, content_hash, raw_payload, revision_no, '
            . 'first_seen_at_utc, last_seen_at_utc, created_at_utc, updated_at_utc '
            . 'FROM ' . $this->tableName . ' WHERE source_id = %d AND identity_hash = %s LIMIT 1',
            $normalizedSourceId,
            $normalizedHash
        );

        $row = $this->wpdb->get_row($query, ARRAY_A);

        return is_array($row) ? $row : null;
    }

    /**
     * @return int
     */
    public function lastInsertId()
    {
        return isset($this->wpdb->insert_id) ? (int) $this->wpdb->insert_id : 0;
    }

    /**
     * Lifecycle: refresh seen timestamps for unchanged announcements.
     *
     * @param int $itemId
     * @param string $lastSeenAtUtc
     * @param string $updatedAtUtc
     * @return bool
     */
    public function markUnchanged($itemId, $lastSeenAtUtc, $updatedAtUtc)
    {
        $id = (int) $itemId;

        if ($id <= 0) {
            return false;
        }

        $result = $this->wpdb->update(
            $this->tableName,
            array(
                'last_seen_at_utc' => (string) $lastSeenAtUtc,
                'updated_at_utc' => (string) $updatedAtUtc,
            ),
            array('id' => $id),
            array('%s', '%s'),
            array('%d')
        );

        return $result !== false;
    }

    /**
     * Lifecycle: persist content change and bump revision.
     *
     * @param int $itemId
     * @param array<string, mixed> $data
     * @return bool
     */
    public function applyContentUpdate($itemId, array $data)
    {
        $id = (int) $itemId;

        if ($id <= 0) {
            return false;
        }

        $allowed = array(
            'source_guid' => '%s',
            'canonical_url' => '%s',
            'source_published_at_utc' => '%s',
            'raw_title' => '%s',
            'content_hash' => '%s',
            'raw_payload' => '%s',
            'revision_no' => '%d',
            'last_seen_at_utc' => '%s',
            'updated_at_utc' => '%s',
        );

        $updateData = array();
        $updateFormats = array();

        foreach ($allowed as $column => $format) {
            if (!array_key_exists($column, $data)) {
                continue;
            }

            $updateData[$column] = $data[$column];
            $updateFormats[] = $format;
        }

        if ($updateData === array()) {
            return false;
        }

        $result = $this->wpdb->update(
            $this->tableName,
            $updateData,
            array('id' => $id),
            $updateFormats,
            array('%d')
        );

        return $result !== false;
    }
}
