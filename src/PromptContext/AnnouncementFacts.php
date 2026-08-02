<?php

namespace StudyMentor\ContentEngine\PromptContext;

defined('ABSPATH') || exit;

/**
 * Structured factual snapshot extracted from an Announcement.
 * Source facts only — not prompt prose.
 */
final class AnnouncementFacts
{
    private $announcementId;
    private $sourceId;
    private $rawTitle;
    private $canonicalUrl;
    private $sourceGuid;
    private $publishedAtUtc;
    private $contentHash;
    private $revisionNo;
    private $language;
    private $summaryText;
    /** @var array<string, string> */
    private $keyFacts;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data)
    {
        $this->announcementId = isset($data['announcement_id']) ? (int) $data['announcement_id'] : 0;
        $this->sourceId = isset($data['source_id']) ? (int) $data['source_id'] : 0;
        $this->rawTitle = isset($data['raw_title']) ? trim((string) $data['raw_title']) : '';
        $this->canonicalUrl = isset($data['canonical_url'])
            ? trim((string) $data['canonical_url'])
            : '';
        $this->sourceGuid = isset($data['source_guid']) ? trim((string) $data['source_guid']) : '';
        $this->publishedAtUtc = isset($data['published_at_utc'])
            ? (string) $data['published_at_utc']
            : '';
        $this->contentHash = isset($data['content_hash']) ? (string) $data['content_hash'] : '';
        $this->revisionNo = isset($data['revision_no']) ? (int) $data['revision_no'] : 1;
        $this->language = isset($data['language']) ? (string) $data['language'] : '';
        $this->summaryText = isset($data['summary_text'])
            ? trim((string) $data['summary_text'])
            : '';
        $this->keyFacts = array();

        if (isset($data['key_facts']) && is_array($data['key_facts'])) {
            foreach ($data['key_facts'] as $key => $value) {
                if (!is_scalar($key) || !is_scalar($value)) {
                    continue;
                }

                $normalizedKey = trim((string) $key);
                $normalizedValue = trim((string) $value);

                if ($normalizedKey === '' || $normalizedValue === '') {
                    continue;
                }

                $this->keyFacts[$normalizedKey] = $normalizedValue;
            }
        }
    }

    /** @return int */
    public function announcementId()
    {
        return $this->announcementId;
    }

    /** @return int */
    public function sourceId()
    {
        return $this->sourceId;
    }

    /** @return string */
    public function rawTitle()
    {
        return $this->rawTitle;
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

    /** @return string */
    public function contentHash()
    {
        return $this->contentHash;
    }

    /** @return int */
    public function revisionNo()
    {
        return $this->revisionNo;
    }

    /** @return string */
    public function language()
    {
        return $this->language;
    }

    /** @return string */
    public function summaryText()
    {
        return $this->summaryText;
    }

    /**
     * @return array<string, string>
     */
    public function keyFacts()
    {
        return $this->keyFacts;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray()
    {
        return array(
            'announcement_id' => $this->announcementId,
            'source_id' => $this->sourceId,
            'raw_title' => $this->rawTitle,
            'canonical_url' => $this->canonicalUrl,
            'source_guid' => $this->sourceGuid,
            'published_at_utc' => $this->publishedAtUtc,
            'content_hash' => $this->contentHash,
            'revision_no' => $this->revisionNo,
            'language' => $this->language,
            'summary_text' => $this->summaryText,
            'key_facts' => $this->keyFacts,
        );
    }
}
