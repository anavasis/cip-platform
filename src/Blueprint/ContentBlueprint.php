<?php

namespace StudyMentor\ContentEngine\Blueprint;

defined('ABSPATH') || exit;

/**
 * Canonical Content Asset Blueprint aggregate (ADR-001).
 * Defines what content should be generated — not prompts, not provider config.
 */
final class ContentBlueprint
{
    private $blueprintId;
    private $announcementId;
    private $lineageId;
    private $blueprintRevision;
    private $sourceContentHash;
    private $announcementRevisionNo;
    private $createdBy;
    private $status;
    private $articleType;
    private $targetAudience;
    private $language;
    private $tone;
    private $targetLength;
    /** @var array<int, string> */
    private $titleCandidates;
    /** @var array<int, HeadingNode> */
    private $headingHierarchy;
    /** @var array<int, SectionSpec> */
    private $requiredSections;
    private $faqRequirements;
    /** @var array<int, CtaRequirement> */
    private $ctaRequirements;
    /** @var array<int, LinkRequirement> */
    private $internalLinkRequirements;
    /** @var array<int, LinkRequirement> */
    private $externalReferenceRequirements;
    /** @var array<int, SchemaRequirement> */
    private $schemaRequirements;
    private $seoConstraints;
    /** @var array<int, ValidationRuleSpec> */
    private $validationRules;
    private $createdAtUtc;
    private $updatedAtUtc;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data)
    {
        $this->blueprintId = isset($data['blueprint_id']) ? (string) $data['blueprint_id'] : '';
        $this->announcementId = isset($data['announcement_id']) ? (int) $data['announcement_id'] : 0;
        $this->lineageId = isset($data['lineage_id']) ? (string) $data['lineage_id'] : '';
        $this->blueprintRevision = isset($data['blueprint_revision'])
            ? (int) $data['blueprint_revision']
            : 1;
        $this->sourceContentHash = isset($data['source_content_hash'])
            ? (string) $data['source_content_hash']
            : '';
        $this->announcementRevisionNo = isset($data['announcement_revision_no'])
            ? (int) $data['announcement_revision_no']
            : 1;
        $this->createdBy = isset($data['created_by']) ? (string) $data['created_by'] : 'system';
        $this->status = isset($data['status']) ? (string) $data['status'] : BlueprintStatus::DRAFT;
        $this->articleType = isset($data['article_type'])
            ? (string) $data['article_type']
            : ArticleType::NEWS_BRIEF;
        $this->targetAudience = isset($data['target_audience'])
            ? (string) $data['target_audience']
            : '';
        $this->language = isset($data['language']) ? (string) $data['language'] : 'el';
        $this->tone = isset($data['tone']) ? (string) $data['tone'] : Tone::NEUTRAL;
        $this->targetLength = isset($data['target_length']) && is_array($data['target_length'])
            ? new LengthTarget($data['target_length'])
            : new LengthTarget(array(
                'unit' => LengthTarget::UNIT_WORDS,
                'min' => 300,
                'max' => 900,
                'ideal' => 550,
            ));
        $this->titleCandidates = array();
        if (isset($data['title_candidates']) && is_array($data['title_candidates'])) {
            foreach ($data['title_candidates'] as $title) {
                if (is_scalar($title) && trim((string) $title) !== '') {
                    $this->titleCandidates[] = trim((string) $title);
                }
            }
        }
        $this->headingHierarchy = $this->mapObjects(
            isset($data['heading_hierarchy']) ? $data['heading_hierarchy'] : array(),
            HeadingNode::class
        );
        $this->requiredSections = $this->mapObjects(
            isset($data['required_sections']) ? $data['required_sections'] : array(),
            SectionSpec::class
        );
        $this->faqRequirements = isset($data['faq_requirements']) && is_array($data['faq_requirements'])
            ? new FaqRequirements($data['faq_requirements'])
            : new FaqRequirements(array('enabled' => false));
        $this->ctaRequirements = $this->mapObjects(
            isset($data['cta_requirements']) ? $data['cta_requirements'] : array(),
            CtaRequirement::class
        );
        $this->internalLinkRequirements = $this->mapObjects(
            isset($data['internal_link_requirements']) ? $data['internal_link_requirements'] : array(),
            LinkRequirement::class
        );
        $this->externalReferenceRequirements = $this->mapObjects(
            isset($data['external_reference_requirements'])
                ? $data['external_reference_requirements']
                : array(),
            LinkRequirement::class
        );
        $this->schemaRequirements = $this->mapObjects(
            isset($data['schema_requirements']) ? $data['schema_requirements'] : array(),
            SchemaRequirement::class
        );
        $this->seoConstraints = isset($data['seo_constraints']) && is_array($data['seo_constraints'])
            ? new SeoConstraints($data['seo_constraints'])
            : new SeoConstraints(array());
        $this->validationRules = $this->mapObjects(
            isset($data['validation_rules']) ? $data['validation_rules'] : array(),
            ValidationRuleSpec::class
        );
        $this->createdAtUtc = isset($data['created_at_utc']) ? (string) $data['created_at_utc'] : '';
        $this->updatedAtUtc = isset($data['updated_at_utc']) ? (string) $data['updated_at_utc'] : '';
    }

    /** @return string */
    public function blueprintId()
    {
        return $this->blueprintId;
    }

    /** @return int */
    public function announcementId()
    {
        return $this->announcementId;
    }

    /** @return string */
    public function lineageId()
    {
        return $this->lineageId;
    }

    /** @return int */
    public function blueprintRevision()
    {
        return $this->blueprintRevision;
    }

    /** @return string */
    public function sourceContentHash()
    {
        return $this->sourceContentHash;
    }

    /** @return int */
    public function announcementRevisionNo()
    {
        return $this->announcementRevisionNo;
    }

    /** @return string */
    public function createdBy()
    {
        return $this->createdBy;
    }

    /** @return string */
    public function status()
    {
        return $this->status;
    }

    /** @return string */
    public function articleType()
    {
        return $this->articleType;
    }

    /** @return string */
    public function targetAudience()
    {
        return $this->targetAudience;
    }

    /** @return string */
    public function language()
    {
        return $this->language;
    }

    /** @return string */
    public function tone()
    {
        return $this->tone;
    }

    /** @return LengthTarget */
    public function targetLength()
    {
        return $this->targetLength;
    }

    /**
     * @return array<int, string>
     */
    public function titleCandidates()
    {
        return $this->titleCandidates;
    }

    /**
     * @return array<int, HeadingNode>
     */
    public function headingHierarchy()
    {
        return $this->headingHierarchy;
    }

    /**
     * @return array<int, SectionSpec>
     */
    public function requiredSections()
    {
        return $this->requiredSections;
    }

    /** @return FaqRequirements */
    public function faqRequirements()
    {
        return $this->faqRequirements;
    }

    /**
     * @return array<int, CtaRequirement>
     */
    public function ctaRequirements()
    {
        return $this->ctaRequirements;
    }

    /**
     * @return array<int, LinkRequirement>
     */
    public function internalLinkRequirements()
    {
        return $this->internalLinkRequirements;
    }

    /**
     * @return array<int, LinkRequirement>
     */
    public function externalReferenceRequirements()
    {
        return $this->externalReferenceRequirements;
    }

    /**
     * @return array<int, SchemaRequirement>
     */
    public function schemaRequirements()
    {
        return $this->schemaRequirements;
    }

    /** @return SeoConstraints */
    public function seoConstraints()
    {
        return $this->seoConstraints;
    }

    /**
     * @return array<int, ValidationRuleSpec>
     */
    public function validationRules()
    {
        return $this->validationRules;
    }

    /** @return string */
    public function createdAtUtc()
    {
        return $this->createdAtUtc;
    }

    /** @return string */
    public function updatedAtUtc()
    {
        return $this->updatedAtUtc;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray()
    {
        return array(
            'blueprint_id' => $this->blueprintId,
            'announcement_id' => $this->announcementId,
            'lineage_id' => $this->lineageId,
            'blueprint_revision' => $this->blueprintRevision,
            'source_content_hash' => $this->sourceContentHash,
            'announcement_revision_no' => $this->announcementRevisionNo,
            'created_by' => $this->createdBy,
            'status' => $this->status,
            'article_type' => $this->articleType,
            'target_audience' => $this->targetAudience,
            'language' => $this->language,
            'tone' => $this->tone,
            'target_length' => $this->targetLength->toArray(),
            'title_candidates' => $this->titleCandidates,
            'heading_hierarchy' => $this->mapToArray($this->headingHierarchy),
            'required_sections' => $this->mapToArray($this->requiredSections),
            'faq_requirements' => $this->faqRequirements->toArray(),
            'cta_requirements' => $this->mapToArray($this->ctaRequirements),
            'internal_link_requirements' => $this->mapToArray($this->internalLinkRequirements),
            'external_reference_requirements' => $this->mapToArray($this->externalReferenceRequirements),
            'schema_requirements' => $this->mapToArray($this->schemaRequirements),
            'seo_constraints' => $this->seoConstraints->toArray(),
            'validation_rules' => $this->mapToArray($this->validationRules),
            'created_at_utc' => $this->createdAtUtc,
            'updated_at_utc' => $this->updatedAtUtc,
        );
    }

    /**
     * @param mixed $items
     * @param class-string $className
     * @return array<int, object>
     */
    private function mapObjects($items, $className)
    {
        $out = array();

        if (!is_array($items)) {
            return $out;
        }

        foreach ($items as $item) {
            if ($item instanceof $className) {
                $out[] = $item;
                continue;
            }

            if (is_array($item)) {
                $out[] = new $className($item);
            }
        }

        return $out;
    }

    /**
     * @param array<int, object> $items
     * @return array<int, array<string, mixed>>
     */
    private function mapToArray(array $items)
    {
        $out = array();

        foreach ($items as $item) {
            if (is_object($item) && method_exists($item, 'toArray')) {
                $out[] = $item->toArray();
            }
        }

        return $out;
    }
}
