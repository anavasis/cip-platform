<?php

namespace App\Modules\Editorial\Domain\PromptContext;


/**
 * Blueprint projection metadata embedded in a Prompt Context.
 * Constrains generation intent without carrying prompt prose.
 */
final class BlueprintProjection
{
    private $blueprintId;
    private $blueprintRevision;
    private $articleType;
    private $targetAudience;
    private $language;
    private $tone;
    /** @var array<int, string> */
    private $titleCandidates;
    /** @var array<int, string> */
    private $sectionKeys;
    /** @var array<int, string> */
    private $headingKeys;
    /** @var array<string, mixed> */
    private $targetLength;
    private $faqEnabled;
    /** @var array<int, string> */
    private $ctaKeys;
    /** @var array<int, string> */
    private $schemaTypes;
    private $primaryTopic;
    /** @var array<int, string> */
    private $mustIncludeTerms;
    /** @var array<int, string> */
    private $avoidTerms;
    private $metaDescriptionRequired;
    /** @var array<int, string> */
    private $validationRuleIds;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data)
    {
        $this->blueprintId = isset($data['blueprint_id']) ? (string) $data['blueprint_id'] : '';
        $this->blueprintRevision = isset($data['blueprint_revision'])
            ? (int) $data['blueprint_revision']
            : 1;
        $this->articleType = isset($data['article_type']) ? (string) $data['article_type'] : '';
        $this->targetAudience = isset($data['target_audience'])
            ? (string) $data['target_audience']
            : '';
        $this->language = isset($data['language']) ? (string) $data['language'] : '';
        $this->tone = isset($data['tone']) ? (string) $data['tone'] : '';
        $this->titleCandidates = $this->stringList(
            isset($data['title_candidates']) ? $data['title_candidates'] : array()
        );
        $this->sectionKeys = $this->stringList(
            isset($data['section_keys']) ? $data['section_keys'] : array()
        );
        $this->headingKeys = $this->stringList(
            isset($data['heading_keys']) ? $data['heading_keys'] : array()
        );
        $this->targetLength = isset($data['target_length']) && is_array($data['target_length'])
            ? $data['target_length']
            : array();
        $this->faqEnabled = isset($data['faq_enabled']) && $data['faq_enabled'] === true;
        $this->ctaKeys = $this->stringList(
            isset($data['cta_keys']) ? $data['cta_keys'] : array()
        );
        $this->schemaTypes = $this->stringList(
            isset($data['schema_types']) ? $data['schema_types'] : array()
        );
        $this->primaryTopic = isset($data['primary_topic']) ? (string) $data['primary_topic'] : '';
        $this->mustIncludeTerms = $this->stringList(
            isset($data['must_include_terms']) ? $data['must_include_terms'] : array()
        );
        $this->avoidTerms = $this->stringList(
            isset($data['avoid_terms']) ? $data['avoid_terms'] : array()
        );
        $this->metaDescriptionRequired = isset($data['meta_description_required'])
            && $data['meta_description_required'] === true;
        $this->validationRuleIds = $this->stringList(
            isset($data['validation_rule_ids']) ? $data['validation_rule_ids'] : array()
        );
    }

    /** @return string */
    public function blueprintId()
    {
        return $this->blueprintId;
    }

    /** @return string */
    public function blueprintRevision()
    {
        return $this->blueprintRevision;
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

    /**
     * @return array<int, string>
     */
    public function titleCandidates()
    {
        return $this->titleCandidates;
    }

    /**
     * @return array<int, string>
     */
    public function sectionKeys()
    {
        return $this->sectionKeys;
    }

    /**
     * @return array<int, string>
     */
    public function headingKeys()
    {
        return $this->headingKeys;
    }

    /**
     * @return array<string, mixed>
     */
    public function targetLength()
    {
        return $this->targetLength;
    }

    /** @return bool */
    public function faqEnabled()
    {
        return $this->faqEnabled;
    }

    /**
     * @return array<int, string>
     */
    public function ctaKeys()
    {
        return $this->ctaKeys;
    }

    /**
     * @return array<int, string>
     */
    public function schemaTypes()
    {
        return $this->schemaTypes;
    }

    /** @return string */
    public function primaryTopic()
    {
        return $this->primaryTopic;
    }

    /**
     * @return array<int, string>
     */
    public function mustIncludeTerms()
    {
        return $this->mustIncludeTerms;
    }

    /**
     * @return array<int, string>
     */
    public function avoidTerms()
    {
        return $this->avoidTerms;
    }

    /** @return bool */
    public function metaDescriptionRequired()
    {
        return $this->metaDescriptionRequired;
    }

    /**
     * @return array<int, string>
     */
    public function validationRuleIds()
    {
        return $this->validationRuleIds;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray()
    {
        return array(
            'blueprint_id' => $this->blueprintId,
            'blueprint_revision' => $this->blueprintRevision,
            'article_type' => $this->articleType,
            'target_audience' => $this->targetAudience,
            'language' => $this->language,
            'tone' => $this->tone,
            'title_candidates' => $this->titleCandidates,
            'section_keys' => $this->sectionKeys,
            'heading_keys' => $this->headingKeys,
            'target_length' => $this->targetLength,
            'faq_enabled' => $this->faqEnabled,
            'cta_keys' => $this->ctaKeys,
            'schema_types' => $this->schemaTypes,
            'primary_topic' => $this->primaryTopic,
            'must_include_terms' => $this->mustIncludeTerms,
            'avoid_terms' => $this->avoidTerms,
            'meta_description_required' => $this->metaDescriptionRequired,
            'validation_rule_ids' => $this->validationRuleIds,
        );
    }

    /**
     * @param mixed $value
     * @return array<int, string>
     */
    private function stringList($value)
    {
        $out = array();

        if (!is_array($value)) {
            return $out;
        }

        foreach ($value as $item) {
            if (is_scalar($item) && trim((string) $item) !== '') {
                $out[] = trim((string) $item);
            }
        }

        return $out;
    }
}
