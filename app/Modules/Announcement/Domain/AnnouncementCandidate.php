<?php

namespace App\Modules\Announcement\Domain;

/**
 * Immutable announcement candidate prior to a lifecycle decision.
 */
final class AnnouncementCandidate
{
    private readonly string $sourceId;

    private readonly string $title;

    private readonly string $canonicalUrl;

    private readonly string $sourceGuid;

    private readonly string $publishedAtUtc;

    /**
     * @var array<string, mixed>
     */
    private readonly array $rawPayload;

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(array $data)
    {
        $this->sourceId = isset($data['source_id']) ? (string) $data['source_id'] : '';
        $this->title = isset($data['title']) ? (string) $data['title'] : '';
        $this->canonicalUrl = isset($data['canonical_url']) ? (string) $data['canonical_url'] : '';
        $this->sourceGuid = isset($data['source_guid']) ? (string) $data['source_guid'] : '';
        $this->publishedAtUtc = isset($data['published_at_utc']) ? (string) $data['published_at_utc'] : '';
        $this->rawPayload = isset($data['raw_payload']) && is_array($data['raw_payload'])
            ? $data['raw_payload']
            : [];
    }

    public function sourceId(): string
    {
        return $this->sourceId;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function canonicalUrl(): string
    {
        return $this->canonicalUrl;
    }

    public function sourceGuid(): string
    {
        return $this->sourceGuid;
    }

    public function publishedAtUtc(): string
    {
        return $this->publishedAtUtc;
    }

    /**
     * @return array<string, mixed>
     */
    public function rawPayload(): array
    {
        return $this->rawPayload;
    }
}
