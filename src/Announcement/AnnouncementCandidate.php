<?php

namespace StudyMentor\ContentEngine\Announcement;

defined('ABSPATH') || exit;

/**
 * Immutable announcement candidate prior to lifecycle decision.
 */
final class AnnouncementCandidate
{
    private $sourceId;
    private $title;
    private $canonicalUrl;
    private $sourceGuid;
    private $publishedAtUtc;
    private $rawPayload;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data)
    {
        $this->sourceId = isset($data['source_id']) ? (int) $data['source_id'] : 0;
        $this->title = isset($data['title']) ? (string) $data['title'] : '';
        $this->canonicalUrl = isset($data['canonical_url']) ? (string) $data['canonical_url'] : '';
        $this->sourceGuid = isset($data['source_guid']) ? (string) $data['source_guid'] : '';
        $this->publishedAtUtc = isset($data['published_at_utc']) ? (string) $data['published_at_utc'] : '';
        $this->rawPayload = isset($data['raw_payload']) && is_array($data['raw_payload'])
            ? $data['raw_payload']
            : array();
    }

    /** @return int */
    public function sourceId()
    {
        return $this->sourceId;
    }

    /** @return string */
    public function title()
    {
        return $this->title;
    }

    /** @return string */
    public function canonicalUrl()
    {
        return $this->canonicalUrl;
    }

    /** @return string */
    public function sourceGuid()
    {
        return $this->sourceGuid;
    }

    /** @return string */
    public function publishedAtUtc()
    {
        return $this->publishedAtUtc;
    }

    /**
     * @return array<string, mixed>
     */
    public function rawPayload()
    {
        return $this->rawPayload;
    }
}
