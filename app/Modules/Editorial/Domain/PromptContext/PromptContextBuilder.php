<?php

namespace App\Modules\Editorial\Domain\PromptContext;

use App\Modules\Editorial\Domain\Blueprint\ContentBlueprint;


/**
 * Builds a Prompt Context from an Announcement snapshot + Content Blueprint.
 * Provider-independent structured data only — no prompt text or packages.
 */
final class PromptContextBuilder
{
    private const SUMMARY_MAX_CHARS = 2000;

    /**
     * @param array<string, mixed> $announcementSnapshot
     * @param ContentBlueprint $blueprint
     * @param array<string, mixed> $overrides
     * @return PromptContext
     */
    public function buildFromAnnouncementAndBlueprint(
        array $announcementSnapshot,
        ContentBlueprint $blueprint,
        array $overrides = array()
    ) {
        $announcementId = $this->resolveAnnouncementId($announcementSnapshot, $blueprint);
        $facts = $this->buildFacts($announcementSnapshot, $blueprint, $announcementId);
        $projection = $this->buildProjection($blueprint);
        $now = $this->utcNow();
        $sourceHash = $facts->contentHash() !== ''
            ? $facts->contentHash()
            : $blueprint->sourceContentHash();

        $payload = array(
            'facts' => $facts->toArray(),
            'blueprint_projection' => $projection->toArray(),
        );

        $data = array(
            'context_id' => isset($overrides['context_id'])
                ? (string) $overrides['context_id']
                : $this->newContextId($announcementId, $blueprint->blueprintId()),
            'announcement_id' => $announcementId,
            'blueprint_id' => $blueprint->blueprintId(),
            'blueprint_revision' => $blueprint->blueprintRevision(),
            'source_content_hash' => $sourceHash,
            'announcement_revision_no' => $facts->revisionNo(),
            'status' => PromptContextStatus::DRAFT,
            'facts' => $facts,
            'blueprint_projection' => $projection,
            'context_hash' => $this->hashPayload($payload),
            'created_at_utc' => $now,
            'updated_at_utc' => $now,
        );

        return new PromptContext($data);
    }

    /**
     * @param array<string, mixed> $announcementSnapshot
     * @param ContentBlueprint $blueprint
     * @return string
     */
    private function resolveAnnouncementId(array $announcementSnapshot, ContentBlueprint $blueprint)
    {
        if (isset($announcementSnapshot['announcement_id'])) {
            return trim((string) $announcementSnapshot['announcement_id']);
        }

        if (isset($announcementSnapshot['id'])) {
            return trim((string) $announcementSnapshot['id']);
        }

        return $blueprint->announcementId();
    }

    /**
     * @param array<string, mixed> $announcementSnapshot
     * @param ContentBlueprint $blueprint
     * @param string $announcementId
     * @return AnnouncementFacts
     */
    private function buildFacts(array $announcementSnapshot, ContentBlueprint $blueprint, $announcementId)
    {
        $rawPayload = isset($announcementSnapshot['raw_payload'])
            && is_array($announcementSnapshot['raw_payload'])
            ? $announcementSnapshot['raw_payload']
            : array();

        $rawTitle = '';
        if (isset($announcementSnapshot['raw_title'])) {
            $rawTitle = trim((string) $announcementSnapshot['raw_title']);
        } elseif (isset($announcementSnapshot['title'])) {
            $rawTitle = trim((string) $announcementSnapshot['title']);
        }

        $contentHash = '';
        if (isset($announcementSnapshot['source_content_hash'])) {
            $contentHash = (string) $announcementSnapshot['source_content_hash'];
        } elseif (isset($announcementSnapshot['content_hash'])) {
            $contentHash = (string) $announcementSnapshot['content_hash'];
        } else {
            $contentHash = $blueprint->sourceContentHash();
        }

        $revisionNo = 1;
        if (isset($announcementSnapshot['announcement_revision_no'])) {
            $revisionNo = (int) $announcementSnapshot['announcement_revision_no'];
        } elseif (isset($announcementSnapshot['revision_no'])) {
            $revisionNo = (int) $announcementSnapshot['revision_no'];
        } else {
            $revisionNo = $blueprint->announcementRevisionNo();
        }

        $publishedAt = '';
        if (isset($announcementSnapshot['published_at_utc'])) {
            $publishedAt = (string) $announcementSnapshot['published_at_utc'];
        } elseif (isset($announcementSnapshot['source_published_at_utc'])) {
            $publishedAt = (string) $announcementSnapshot['source_published_at_utc'];
        }

        $language = '';
        if (isset($announcementSnapshot['language'])) {
            $language = (string) $announcementSnapshot['language'];
        } else {
            $language = $blueprint->language();
        }

        return new AnnouncementFacts(array(
            'announcement_id' => $announcementId,
            'source_id' => isset($announcementSnapshot['source_id'])
                ? trim((string) $announcementSnapshot['source_id'])
                : '',
            'raw_title' => $rawTitle,
            'canonical_url' => isset($announcementSnapshot['canonical_url'])
                ? (string) $announcementSnapshot['canonical_url']
                : '',
            'source_guid' => isset($announcementSnapshot['source_guid'])
                ? (string) $announcementSnapshot['source_guid']
                : '',
            'published_at_utc' => $publishedAt,
            'content_hash' => $contentHash,
            'revision_no' => $revisionNo,
            'language' => $language,
            'summary_text' => $this->extractSummaryText($rawPayload),
            'key_facts' => $this->extractKeyFacts($rawPayload),
        ));
    }

    /**
     * @param ContentBlueprint $blueprint
     * @return BlueprintProjection
     */
    private function buildProjection(ContentBlueprint $blueprint)
    {
        $sectionKeys = array();
        foreach ($blueprint->requiredSections() as $section) {
            if (is_object($section) && method_exists($section, 'sectionKey')) {
                $key = (string) $section->sectionKey();
                if ($key !== '') {
                    $sectionKeys[] = $key;
                }
            }
        }

        $headingKeys = array();
        foreach ($blueprint->headingHierarchy() as $heading) {
            if (is_object($heading) && method_exists($heading, 'headingKey')) {
                $key = (string) $heading->headingKey();
                if ($key !== '') {
                    $headingKeys[] = $key;
                }
            }
        }

        $ctaKeys = array();
        foreach ($blueprint->ctaRequirements() as $cta) {
            if (is_object($cta) && method_exists($cta, 'ctaKey')) {
                $key = (string) $cta->ctaKey();
                if ($key !== '') {
                    $ctaKeys[] = $key;
                }
            }
        }

        $schemaTypes = array();
        foreach ($blueprint->schemaRequirements() as $schema) {
            if (is_object($schema) && method_exists($schema, 'schemaType')) {
                $type = (string) $schema->schemaType();
                if ($type !== '') {
                    $schemaTypes[] = $type;
                }
            }
        }

        $ruleIds = array();
        foreach ($blueprint->validationRules() as $rule) {
            if (is_object($rule) && method_exists($rule, 'ruleId')) {
                $ruleId = (string) $rule->ruleId();
                if ($ruleId !== '') {
                    $ruleIds[] = $ruleId;
                }
            }
        }

        $seo = $blueprint->seoConstraints();

        return new BlueprintProjection(array(
            'blueprint_id' => $blueprint->blueprintId(),
            'blueprint_revision' => $blueprint->blueprintRevision(),
            'article_type' => $blueprint->articleType(),
            'target_audience' => $blueprint->targetAudience(),
            'language' => $blueprint->language(),
            'tone' => $blueprint->tone(),
            'title_candidates' => $blueprint->titleCandidates(),
            'section_keys' => $sectionKeys,
            'heading_keys' => $headingKeys,
            'target_length' => $blueprint->targetLength()->toArray(),
            'faq_enabled' => $blueprint->faqRequirements()->enabled(),
            'cta_keys' => $ctaKeys,
            'schema_types' => $schemaTypes,
            'primary_topic' => $seo->primaryTopic(),
            'must_include_terms' => $seo->mustIncludeTerms(),
            'avoid_terms' => $seo->avoidTerms(),
            'meta_description_required' => $seo->metaDescriptionRequired(),
            'validation_rule_ids' => $ruleIds,
        ));
    }

    /**
     * @param array<string, mixed> $rawPayload
     * @return string
     */
    private function extractSummaryText(array $rawPayload)
    {
        $candidates = array('summary', 'description', 'excerpt', 'content', 'body', 'text');

        foreach ($candidates as $key) {
            if (!isset($rawPayload[$key]) || !is_scalar($rawPayload[$key])) {
                continue;
            }

            $text = trim($this->stripTags((string) $rawPayload[$key]));
            if ($text === '') {
                continue;
            }

            if (function_exists('mb_substr')) {
                return (string) mb_substr($text, 0, self::SUMMARY_MAX_CHARS);
            }

            return substr($text, 0, self::SUMMARY_MAX_CHARS);
        }

        return '';
    }

    /**
     * @param string $text
     * @return string
     */
    private function stripTags($text)
    {
        return strip_tags($text);
    }

    /**
     * @param array<string, mixed> $rawPayload
     * @return array<string, string>
     */
    private function extractKeyFacts(array $rawPayload)
    {
        $allowedKeys = array(
            'category',
            'deadline',
            'application_deadline',
            'institution',
            'program',
            'location',
            'tags',
        );
        $facts = array();

        foreach ($allowedKeys as $key) {
            if (!isset($rawPayload[$key]) || !is_scalar($rawPayload[$key])) {
                continue;
            }

            $value = trim((string) $rawPayload[$key]);
            if ($value !== '') {
                $facts[$key] = $value;
            }
        }

        return $facts;
    }

    /**
     * @param array<string, mixed> $payload
     * @return string
     */
    private function hashPayload(array $payload)
    {
        $canonical = $this->canonicalize($payload);
        $encoded = json_encode($canonical);

        if (!is_string($encoded) || $encoded === '') {
            $encoded = serialize($canonical);
        }

        return hash('sha256', $encoded);
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private function canonicalize($value)
    {
        if (!is_array($value)) {
            return $value;
        }

        if ($value !== array() && array_keys($value) !== range(0, count($value) - 1)) {
            ksort($value);
        }

        $out = array();
        foreach ($value as $key => $item) {
            $out[$key] = $this->canonicalize($item);
        }

        return $out;
    }

    /**
     * @param string $announcementId
     * @param string $blueprintId
     * @return string
     */
    private function newContextId($announcementId, $blueprintId)
    {
        $seed = (string) $announcementId . '|' . (string) $blueprintId . '|' . uniqid('', true);

        return 'pc_' . $announcementId . '_' . substr(hash('sha256', $seed), 0, 12);
    }

    /**
     * @return string
     */
    private function utcNow()
    {
        return gmdate('Y-m-d H:i:s');
    }
}
