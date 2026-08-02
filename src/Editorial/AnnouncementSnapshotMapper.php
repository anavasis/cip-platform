<?php

namespace StudyMentor\ContentEngine\Editorial;

defined('ABSPATH') || exit;

/**
 * Maps a stored announcement row into BUILD-001/002 announcement snapshot input.
 */
final class AnnouncementSnapshotMapper
{
    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    public function fromSourceItem(array $item)
    {
        $rawPayload = array();
        $payloadSource = '';

        if (isset($item['raw_payload']) && is_string($item['raw_payload'])) {
            $payloadSource = $item['raw_payload'];
        } elseif (isset($item['display_raw_payload']) && is_string($item['display_raw_payload'])) {
            $payloadSource = $item['display_raw_payload'];
        }

        if ($payloadSource !== '') {
            $decoded = json_decode($payloadSource, true);
            if (is_array($decoded)) {
                $rawPayload = $decoded;
            }
        }

        $announcementId = 0;
        if (isset($item['announcement_id'])) {
            $announcementId = (int) $item['announcement_id'];
        } elseif (isset($item['id'])) {
            $announcementId = (int) $item['id'];
        }

        $contentHash = '';
        if (isset($item['source_content_hash'])) {
            $contentHash = (string) $item['source_content_hash'];
        } elseif (isset($item['content_hash'])) {
            $contentHash = (string) $item['content_hash'];
        }

        $revisionNo = 1;
        if (isset($item['announcement_revision_no'])) {
            $revisionNo = (int) $item['announcement_revision_no'];
        } elseif (isset($item['revision_no'])) {
            $revisionNo = (int) $item['revision_no'];
        }

        $publishedAt = '';
        if (isset($item['published_at_utc'])) {
            $publishedAt = (string) $item['published_at_utc'];
        } elseif (isset($item['source_published_at_utc'])) {
            $publishedAt = (string) $item['source_published_at_utc'];
        }

        $rawTitle = '';
        if (isset($item['raw_title'])) {
            $rawTitle = trim((string) $item['raw_title']);
        } elseif (isset($item['title'])) {
            $rawTitle = trim((string) $item['title']);
        }

        return array(
            'announcement_id' => $announcementId,
            'source_id' => isset($item['source_id']) ? (int) $item['source_id'] : 0,
            'raw_title' => $rawTitle,
            'canonical_url' => isset($item['canonical_url']) ? (string) $item['canonical_url'] : '',
            'source_guid' => isset($item['source_guid']) ? (string) $item['source_guid'] : '',
            'published_at_utc' => $publishedAt,
            'source_published_at_utc' => $publishedAt,
            'source_content_hash' => $contentHash,
            'content_hash' => $contentHash,
            'announcement_revision_no' => $revisionNo,
            'revision_no' => $revisionNo,
            'language' => isset($item['language']) ? (string) $item['language'] : 'el',
            'raw_payload' => $rawPayload,
        );
    }
}
